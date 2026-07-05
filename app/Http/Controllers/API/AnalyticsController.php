<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Google\Analytics\Data\V1beta\RunRealtimeReportRequest;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\FilterExpression;
use Google\Analytics\Data\V1beta\Filter;
use Google\Analytics\Data\V1beta\Filter\StringFilter;
use Google\Analytics\Data\V1beta\Filter\StringFilter\MatchType;

class AnalyticsController extends Controller
{
    public function getReport(Request $request): JsonResponse
    {
        $user = $request->user();
        $portfolio = $user->portfolio;

        if (!$portfolio || !$portfolio->slug) {
            return response()->json($this->fullEmptyResponse());
        }

        $period = $request->query('period', '15d');
        $force = $request->boolean('force', false);

        $data = $this->resolveAnalytics($portfolio->slug, $period, $force, $portfolio);

        return response()->json($data);
    }

    public function getGlobalReport(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $period = $request->query('period', '15d');
        $force = $request->boolean('force', false);

        $data = $this->resolveAnalytics(null, $period, $force, null);

        return response()->json($data);
    }

    public function getStudentReport(string $studentId, Request $request): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $student = \App\Models\User::find($studentId);
        if (!$student || !$student->portfolio || !$student->portfolio->slug) {
            return response()->json($this->fullEmptyResponse());
        }

        $period = $request->query('period', '15d');
        $force = $request->boolean('force', false);

        $data = $this->resolveAnalytics($student->portfolio->slug, $period, $force, $student->portfolio);

        return response()->json($data);
    }

    private function resolveAnalytics(?string $slug, string $period, bool $force, $portfolio): array
    {
        if ($period === 'live') {
            $cacheKey = 'analytics_live_' . ($slug ?? 'global');
            if ($force) {
                Cache::forget($cacheKey);
            }
            return Cache::remember($cacheKey, 60, function () use ($slug, $portfolio) {
                return $this->fetchRealtime($slug, $portfolio);
            });
        }

        $days = $period === '30d' ? 30 : 15;

        if ($slug && $portfolio) {
            $existing = $portfolio->analytics_data;

            if (!$force && $existing && isset($existing[$period])) {
                $cached = $this->deepMergeHistorical($this->emptyHistoricalResponse(), $existing[$period]);
                $cached = $this->normalizeHistoricalData($cached);
                $cached['last_updated_at'] = $portfolio->last_analytics_updated_at?->toIso8601String();
                return ['historical' => $cached];
            }

            $fresh = $this->fetchHistorical($slug, $days);
            $portfolio->analytics_data = array_merge($existing ?? [], [$period => $fresh]);
            $portfolio->last_analytics_updated_at = now();
            $portfolio->save();

            $fresh['last_updated_at'] = $portfolio->last_analytics_updated_at->toIso8601String();
            return ['historical' => $fresh];
        }

        $settingKey = 'global_analytics_' . $period;
        $setting = AppSetting::find($settingKey);

        if (!$force && $setting && $setting->value) {
            $val = $this->deepMergeHistorical($this->emptyHistoricalResponse(), $setting->value);
            $val = $this->normalizeHistoricalData($val);
            $val['last_updated_at'] = $setting->updated_at?->toIso8601String();
            return ['historical' => $val];
        }

        $fresh = $this->fetchHistorical(null, $days);
        AppSetting::updateOrCreate(
            ['key' => $settingKey],
            ['value' => $fresh, 'description' => "GA4 global analytics for last {$days} days"]
        );

        $fresh['last_updated_at'] = now()->toIso8601String();
        return ['historical' => $fresh];
    }

    private function fetchHistorical(?string $slug, int $days): array
    {
        $propertyId = config('analytics.property_id');

        if (!$propertyId) {
            Log::warning('GA4_PROPERTY_ID no está configurado.');
            return $this->emptyHistoricalResponse();
        }

        try {
            $client = new BetaAnalyticsDataClient([
                'credentials' => $this->resolveCredentials(),
            ]);

            $property = 'properties/' . $propertyId;
            $dateRange = new DateRange(['start_date' => "{$days}daysAgo", 'end_date' => 'today']);
            $pageFilter = $slug ? $this->buildPageFilter($slug) : null;

            // 1. Daily breakdown + totals
            $dailyRes = $client->runReport(new RunReportRequest([
                'property' => $property,
                'date_ranges' => [$dateRange],
                'dimensions' => [new Dimension(['name' => 'date'])],
                'metrics' => [
                    new Metric(['name' => 'activeUsers']),
                    new Metric(['name' => 'screenPageViews']),
                    new Metric(['name' => 'bounceRate']),
                    new Metric(['name' => 'newUsers']),
                    new Metric(['name' => 'averageSessionDuration']),
                ],
                'dimension_filter' => $pageFilter,
            ]));

            $daily = [];
            foreach ($dailyRes->getRows() as $row) {
                $dims = $row->getDimensionValues();
                $met = $row->getMetricValues();
                $daily[] = [
                    'date' => date('m-d', strtotime((string) $dims[0]->getValue())),
                    'users' => (int) $met[0]->getValue(),
                    'views' => (int) $met[1]->getValue(),
                    'newUsers' => (int) $met[3]->getValue(),
                    'avgDuration' => round((float) $met[4]->getValue(), 1),
                ];
            }

            $totals = $this->extractTotals($dailyRes, $daily);

            // 2. Devices
            $devices = [];
            $devRes = $client->runReport(new RunReportRequest([
                'property' => $property,
                'date_ranges' => [$dateRange],
                'dimensions' => [new Dimension(['name' => 'deviceCategory'])],
                'metrics' => [new Metric(['name' => 'activeUsers'])],
                'dimension_filter' => $pageFilter,
            ]));
            foreach ($devRes->getRows() as $row) {
                $devices[] = [
                    'name' => $row->getDimensionValues()[0]->getValue() ?: 'Desconocido',
                    'value' => (int) $row->getMetricValues()[0]->getValue(),
                ];
            }

            // 3. Countries — use countryId (ISO alpha-2), filter out (not set)
            $countries = [];
            $cntRes = $client->runReport(new RunReportRequest([
                'property' => $property,
                'date_ranges' => [$dateRange],
                'dimensions' => [new Dimension(['name' => 'countryId'])],
                'metrics' => [new Metric(['name' => 'activeUsers'])],
                'dimension_filter' => $pageFilter,
            ]));
            foreach ($cntRes->getRows() as $row) {
                $code = $row->getDimensionValues()[0]->getValue() ?: '';
                $users = (int) $row->getMetricValues()[0]->getValue();
                if ($code === '(not set)' || $code === '') continue;
                $countries[] = [
                    'code' => $code,
                    'users' => $users,
                ];
            }

            // 5. Top pages
            $topPages = [];
            $pagesRes = $client->runReport(new RunReportRequest([
                'property' => $property,
                'date_ranges' => [$dateRange],
                'dimensions' => [new Dimension(['name' => 'pagePath'])],
                'metrics' => [new Metric(['name' => 'screenPageViews'])],
                'dimension_filter' => $pageFilter,
                'limit' => 10,
            ]));
            foreach ($pagesRes->getRows() as $row) {
                $topPages[] = [
                    'path' => $row->getDimensionValues()[0]->getValue() ?: '/',
                    'views' => (int) $row->getMetricValues()[0]->getValue(),
                ];
            }

            // 6. New vs Returning
            $newVsReturning = [];
            $nvrRes = $client->runReport(new RunReportRequest([
                'property' => $property,
                'date_ranges' => [$dateRange],
                'dimensions' => [new Dimension(['name' => 'newVsReturning'])],
                'metrics' => [new Metric(['name' => 'activeUsers'])],
                'dimension_filter' => $pageFilter,
            ]));
            foreach ($nvrRes->getRows() as $row) {
                $newVsReturning[] = [
                    'type' => $row->getDimensionValues()[0]->getValue() ?: 'unknown',
                    'users' => (int) $row->getMetricValues()[0]->getValue(),
                ];
            }

            // 7. Landing pages (top 5)
            $landingPages = [];
            $lpRes = $client->runReport(new RunReportRequest([
                'property' => $property,
                'date_ranges' => [$dateRange],
                'dimensions' => [new Dimension(['name' => 'landingPage'])],
                'metrics' => [new Metric(['name' => 'sessions'])],
                'dimension_filter' => $pageFilter,
                'limit' => 5,
            ]));
            foreach ($lpRes->getRows() as $row) {
                $landingPages[] = [
                    'path' => $row->getDimensionValues()[0]->getValue() ?: '/',
                    'sessions' => (int) $row->getMetricValues()[0]->getValue(),
                ];
            }

            // 8. Historical events (top 10)
            $events = [];
            $evtRes = $client->runReport(new RunReportRequest([
                'property' => $property,
                'date_ranges' => [$dateRange],
                'dimensions' => [new Dimension(['name' => 'eventName'])],
                'metrics' => [new Metric(['name' => 'eventCount'])],
                'dimension_filter' => $pageFilter,
                'limit' => 10,
            ]));
            foreach ($evtRes->getRows() as $row) {
                $events[] = [
                    'name' => $row->getDimensionValues()[0]->getValue() ?: 'unknown',
                    'count' => (int) $row->getMetricValues()[0]->getValue(),
                ];
            }

            // 9. Engagement rate
            $engagementRate = '0%';
            $engRes = $client->runReport(new RunReportRequest([
                'property' => $property,
                'date_ranges' => [$dateRange],
                'metrics' => [new Metric(['name' => 'engagementRate'])],
                'dimension_filter' => $pageFilter,
            ]));
            foreach ($engRes->getRows() as $row) {
                $val = (float) $row->getMetricValues()[0]->getValue();
                $engagementRate = $val > 1
                    ? number_format($val, 1) . '%'
                    : number_format($val * 100, 1) . '%';
            }

            return [
                'totals' => $totals,
                'daily' => $daily,
                'devices' => $devices,
                'countries' => $countries,
                'topPages' => $topPages,
                'newVsReturning' => $newVsReturning,
                'landingPages' => $landingPages,
                'events' => $events,
                'engagementRate' => $engagementRate,
            ];
        } catch (\Throwable $e) {
            Log::error('Error en GA4 Historical API: ' . $e->getMessage(), [
                'slug' => $slug,
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->deepMergeHistorical($this->emptyHistoricalResponse(), [
                'debug_error' => $e->getMessage()
            ]);
        }
    }

    private function fetchRealtime(?string $slug, $portfolio): array
    {
        $propertyId = config('analytics.property_id');

        if (!$propertyId) {
            return $this->emptyRealtimeResponse('GA4_PROPERTY_ID no configurado');
        }

        try {
            $client = new BetaAnalyticsDataClient([
                'credentials' => $this->resolveCredentials(),
            ]);

            $property = 'properties/' . $propertyId;
            $rtFilter = ($slug && $portfolio) ? $this->buildRealtimeFilter($portfolio) : null;

            // 1. Fetch active users totals
            $totalsRequest = new RunRealtimeReportRequest([
                'property' => $property,
                'metrics' => [new Metric(['name' => 'activeUsers'])],
                'dimension_filter' => $rtFilter,
            ]);
            $totalsRes = $client->runRealtimeReport($totalsRequest);
            $activeUsers = 0;
            foreach ($totalsRes->getRows() as $row) {
                $activeUsers += (int) $row->getMetricValues()[0]->getValue();
            }

            // 2. Fetch realtime devices
            $devicesRequest = new RunRealtimeReportRequest([
                'property' => $property,
                'dimensions' => [new Dimension(['name' => 'deviceCategory'])],
                'metrics' => [new Metric(['name' => 'activeUsers'])],
                'dimension_filter' => $rtFilter,
            ]);
            $devicesRes = $client->runRealtimeReport($devicesRequest);
            $devices = [];
            foreach ($devicesRes->getRows() as $row) {
                $devices[] = [
                    'name' => $row->getDimensionValues()[0]->getValue() ?: 'Desconocido',
                    'value' => (int) $row->getMetricValues()[0]->getValue(),
                ];
            }

            // 3. Fetch realtime countries — use countryId, filter (not set)
            $countriesRequest = new RunRealtimeReportRequest([
                'property' => $property,
                'dimensions' => [new Dimension(['name' => 'countryId'])],
                'metrics' => [new Metric(['name' => 'activeUsers'])],
                'dimension_filter' => $rtFilter,
            ]);
            $countriesRes = $client->runRealtimeReport($countriesRequest);
            $countries = [];
            foreach ($countriesRes->getRows() as $row) {
                $code = $row->getDimensionValues()[0]->getValue() ?: '';
                $users = (int) $row->getMetricValues()[0]->getValue();
                if ($code === '(not set)' || $code === '') continue;
                $countries[] = [
                    'code' => $code,
                    'users' => $users,
                ];
            }

            // 4. Fetch realtime events (recent interactions)
            // Note: eventName + eventCount is incompatible with unifiedScreenName filter
            // for students. Fall back gracefully.
            $events = [];
            try {
                $eventsRequest = new RunRealtimeReportRequest([
                    'property' => $property,
                    'dimensions' => [new Dimension(['name' => 'eventName'])],
                    'metrics' => [new Metric(['name' => 'eventCount'])],
                    'dimension_filter' => $rtFilter,
                ]);
                $eventsRes = $client->runRealtimeReport($eventsRequest);
                foreach ($eventsRes->getRows() as $row) {
                    $events[] = [
                        'name' => $row->getDimensionValues()[0]->getValue() ?: 'unknown_event',
                        'count' => (int) $row->getMetricValues()[0]->getValue(),
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('GA4 Realtime events query failed (non-fatal): ' . $e->getMessage());
            }

            // 5. Fetch realtime activity recency (minutesAgo)
            $recency = [];
            try {
                $recencyRequest = new RunRealtimeReportRequest([
                    'property' => $property,
                    'dimensions' => [new Dimension(['name' => 'minutesAgo'])],
                    'metrics' => [new Metric(['name' => 'activeUsers'])],
                    'dimension_filter' => $rtFilter,
                ]);
                $recencyRes = $client->runRealtimeReport($recencyRequest);
                foreach ($recencyRes->getRows() as $row) {
                    $recency[] = [
                        'minutesAgo' => (int) $row->getDimensionValues()[0]->getValue(),
                        'activeUsers' => (int) $row->getMetricValues()[0]->getValue(),
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('GA4 Realtime recency query failed (non-fatal): ' . $e->getMessage());
            }

            return [
                'realtime' => [
                    'activeUsers' => $activeUsers,
                    'devices' => $devices,
                    'countries' => $countries,
                    'events' => $events,
                    'recency' => $recency,
                ]
            ];
        } catch (\Throwable $e) {
            Log::error('Error en GA4 Realtime API: ' . $e->getMessage(), [
                'slug' => $slug,
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->emptyRealtimeResponse($e->getMessage());
        }
    }

    private function buildPageFilter(string $slug): FilterExpression
    {
        return new FilterExpression([
            'filter' => new Filter([
                'field_name' => 'pagePath',
                'string_filter' => new StringFilter([
                    'match_type' => MatchType::CONTAINS,
                    'value' => "/p/{$slug}",
                ]),
            ]),
        ]);
    }

    private function buildRealtimeFilter($portfolio): FilterExpression
    {
        return new FilterExpression([
            'filter' => new Filter([
                'field_name' => 'unifiedScreenName',
                'string_filter' => new StringFilter([
                    'match_type' => MatchType::CONTAINS,
                    'value' => $portfolio->title,
                ]),
            ]),
        ]);
    }

    private function extractTotals($response, array $dailyRows): array
    {
        // Attempt to read totals from GA4 response first
        $totalsRows = $response->getTotals();
        if ($totalsRows && $totalsRows->count() > 0) {
            $m = $totalsRows[0]->getMetricValues();
            $activeUsers = (int) $m[0]->getValue();
            $screenPageViews = (int) $m[1]->getValue();
            $bounceRate = $this->formatBounceRate((float) $m[2]->getValue());
            $newUsers = (int) $m[3]->getValue();
            $averageSessionDuration = round((float) $m[4]->getValue(), 1);

            // If totals look correct, use them
            if ($activeUsers > 0 || $screenPageViews > 0) {
                return compact('activeUsers', 'screenPageViews', 'bounceRate', 'newUsers', 'averageSessionDuration');
            }
        }

        // Fallback: compute totals from daily rows
        $activeUsers = 0;
        $screenPageViews = 0;
        $newUsers = 0;
        $totalDuration = 0;
        $dailyCount = count($dailyRows);

        foreach ($dailyRows as $row) {
            $activeUsers += $row['users'];
            $screenPageViews += $row['views'];
            $newUsers += $row['newUsers'];
            $totalDuration += $row['avgDuration'];
        }

        $averageSessionDuration = $dailyCount > 0 ? round($totalDuration / $dailyCount, 1) : 0;

        return [
            'activeUsers' => $activeUsers,
            'screenPageViews' => $screenPageViews,
            'bounceRate' => '—',
            'newUsers' => $newUsers,
            'averageSessionDuration' => $averageSessionDuration,
        ];
    }

    private function resolveCredentials(): array|string
    {
        $json = config('analytics.credentials_json');

        if ($json) {
            $decoded = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            Log::warning('GOOGLE_CREDENTIALS_JSON no es JSON válido, usando archivo.');
        }

        return config('analytics.credentials');
    }

    private function formatBounceRate(float $value): string
    {
        if ($value > 1) {
            return number_format($value, 1) . '%';
        }
        return number_format($value * 100, 1) . '%';
    }

    private function deepMergeHistorical(array $defaults, array $cached): array
    {
        foreach ($defaults as $key => $default) {
            if (array_key_exists($key, $cached)) {
                if (is_array($default) && is_array($cached[$key])) {
                    // Deep merge nested arrays (e.g. totals)
                    $cached[$key] = array_merge($default, $cached[$key]);
                }
            } else {
                $cached[$key] = $default;
            }
        }
        return $cached;
    }

    private function normalizeHistoricalData(array $data): array
    {
        // Convert old country format { name, users } to new format { code, users }
        if (isset($data['countries']) && is_array($data['countries'])) {
            $data['countries'] = array_map(function ($c) {
                if (isset($c['code'])) return $c;
                if (isset($c['name'])) {
                    return ['code' => $c['name'], 'users' => $c['users'] ?? 0];
                }
                return $c;
            }, $data['countries']);
        }
        return $data;
    }

    private function emptyHistoricalResponse(): array
    {
        return [
            'totals' => [
                'activeUsers' => 0,
                'screenPageViews' => 0,
                'bounceRate' => '0%',
                'newUsers' => 0,
                'averageSessionDuration' => 0,
            ],
            'daily' => [],
            'devices' => [],
            'countries' => [],
            'topPages' => [],
            'newVsReturning' => [],
            'landingPages' => [],
            'events' => [],
            'engagementRate' => '0%',
        ];
    }

    private function emptyRealtimeResponse(string $errorMessage): array
    {
        return [
            'realtime' => [
                'activeUsers' => 0,
                'devices' => [],
                'countries' => [],
                'events' => [],
                'recency' => [],
            ],
            'debug_error' => $errorMessage,
        ];
    }

    private function fullEmptyResponse(): array
    {
        return [
            'realtime' => [
                'activeUsers' => 0,
                'devices' => [],
                'countries' => [],
                'events' => [],
                'recency' => [],
            ],
            'historical' => $this->emptyHistoricalResponse(),
        ];
    }
}
