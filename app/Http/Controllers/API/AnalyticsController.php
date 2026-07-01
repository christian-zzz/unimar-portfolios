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
                $existing[$period]['last_updated_at'] = $portfolio->last_analytics_updated_at?->toIso8601String();
                return ['historical' => $existing[$period]];
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
            $val = $setting->value;
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

            $totals = $this->extractTotals($dailyRes);

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

            $sources = [];
            $srcRes = $client->runReport(new RunReportRequest([
                'property' => $property,
                'date_ranges' => [$dateRange],
                'dimensions' => [new Dimension(['name' => 'sessionSource'])],
                'metrics' => [new Metric(['name' => 'activeUsers'])],
                'dimension_filter' => $pageFilter,
            ]));
            foreach ($srcRes->getRows() as $row) {
                $sources[] = [
                    'name' => $row->getDimensionValues()[0]->getValue() ?: 'direct',
                    'users' => (int) $row->getMetricValues()[0]->getValue(),
                ];
            }

            $countries = [];
            $cntRes = $client->runReport(new RunReportRequest([
                'property' => $property,
                'date_ranges' => [$dateRange],
                'dimensions' => [new Dimension(['name' => 'country'])],
                'metrics' => [new Metric(['name' => 'activeUsers'])],
                'dimension_filter' => $pageFilter,
            ]));
            foreach ($cntRes->getRows() as $row) {
                $countries[] = [
                    'name' => $row->getDimensionValues()[0]->getValue() ?: 'Desconocido',
                    'users' => (int) $row->getMetricValues()[0]->getValue(),
                ];
            }

            return [
                'totals' => $totals,
                'daily' => $daily,
                'devices' => $devices,
                'sources' => $sources,
                'countries' => $countries,
            ];
        } catch (\Throwable $e) {
            Log::error('Error en GA4 Historical API: ' . $e->getMessage(), [
                'slug' => $slug,
                'trace' => $e->getTraceAsString(),
            ]);
            return array_merge($this->emptyHistoricalResponse(), [
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

            // 3. Fetch realtime countries
            $countriesRequest = new RunRealtimeReportRequest([
                'property' => $property,
                'dimensions' => [new Dimension(['name' => 'country'])],
                'metrics' => [new Metric(['name' => 'activeUsers'])],
                'dimension_filter' => $rtFilter,
            ]);
            $countriesRes = $client->runRealtimeReport($countriesRequest);
            $countries = [];
            foreach ($countriesRes->getRows() as $row) {
                $countries[] = [
                    'name' => $row->getDimensionValues()[0]->getValue() ?: 'Desconocido',
                    'users' => (int) $row->getMetricValues()[0]->getValue(),
                ];
            }

            // 4. Fetch realtime events (recent interactions)
            $eventsRequest = new RunRealtimeReportRequest([
                'property' => $property,
                'dimensions' => [new Dimension(['name' => 'eventName'])],
                'metrics' => [new Metric(['name' => 'eventCount'])],
                'dimension_filter' => $rtFilter,
            ]);
            $eventsRes = $client->runRealtimeReport($eventsRequest);
            $events = [];
            foreach ($eventsRes->getRows() as $row) {
                $events[] = [
                    'name' => $row->getDimensionValues()[0]->getValue() ?: 'unknown_event',
                    'count' => (int) $row->getMetricValues()[0]->getValue(),
                ];
            }

            return [
                'realtime' => [
                    'activeUsers' => $activeUsers,
                    'devices' => $devices,
                    'countries' => $countries,
                    'events' => $events,
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

    private function extractTotals($response): array
    {
        $totalsRows = $response->getTotals();
        if ($totalsRows && $totalsRows->count() > 0) {
            $m = $totalsRows[0]->getMetricValues();
            return [
                'activeUsers' => (int) $m[0]->getValue(),
                'screenPageViews' => (int) $m[1]->getValue(),
                'bounceRate' => $this->formatBounceRate((float) $m[2]->getValue()),
                'newUsers' => (int) $m[3]->getValue(),
                'averageSessionDuration' => round((float) $m[4]->getValue(), 1),
            ];
        }

        return [
            'activeUsers' => 0,
            'screenPageViews' => 0,
            'bounceRate' => '0%',
            'newUsers' => 0,
            'averageSessionDuration' => 0,
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
            'sources' => [],
            'countries' => [],
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
            ],
            'historical' => $this->emptyHistoricalResponse(),
        ];
    }
}
