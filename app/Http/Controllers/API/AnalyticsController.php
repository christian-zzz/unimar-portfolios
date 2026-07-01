<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
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

        return response()->json($this->resolveAnalytics($portfolio->slug));
    }

    public function getGlobalReport(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        return response()->json($this->resolveAnalytics(null));
    }

    private function resolveAnalytics(?string $slug): array
    {
        $cacheKey = $slug ?? 'global';
        $histKey = "analytics_hist_{$cacheKey}";
        $rtKey = "analytics_realtime_{$cacheKey}";

        $historical = Cache::remember($histKey, 900, function () use ($slug) {
            return $this->fetchHistorical($slug);
        });

        $realtime = Cache::remember($rtKey, 60, function () use ($slug) {
            return $this->fetchRealtime($slug);
        });

        return [
            'realtime' => $realtime,
            'historical' => $historical,
        ];
    }

    private function fetchHistorical(?string $slug): array
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
            $dateRange = new DateRange(['start_date' => '15daysAgo', 'end_date' => 'today']);
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
            return $this->emptyHistoricalResponse();
        }
    }

    private function fetchRealtime(?string $slug): array
    {
        $propertyId = config('analytics.property_id');

        if (!$propertyId) {
            return ['activeUsers' => 0];
        }

        try {
            $client = new BetaAnalyticsDataClient([
                'credentials' => $this->resolveCredentials(),
            ]);

            $request = new RunRealtimeReportRequest([
                'property' => 'properties/' . $propertyId,
                'metrics' => [new Metric(['name' => 'activeUsers'])],
            ]);

            if ($slug) {
                $request->setDimensionFilter($this->buildPageFilter($slug));
            }

            $response = $client->runRealtimeReport($request);

            $users = 0;
            foreach ($response->getRows() as $row) {
                $users += (int) $row->getMetricValues()[0]->getValue();
            }

            return ['activeUsers' => $users];
        } catch (\Throwable $e) {
            Log::error('Error en GA4 Realtime API: ' . $e->getMessage(), [
                'slug' => $slug,
                'trace' => $e->getTraceAsString(),
            ]);
            return ['activeUsers' => 0];
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

    private function fullEmptyResponse(): array
    {
        return [
            'realtime' => ['activeUsers' => 0],
            'historical' => $this->emptyHistoricalResponse(),
        ];
    }
}
