<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    /**
     * Fetch a simulated 15-day report of traffic for a student portfolio.
     */
    public function getReport(Request $request): JsonResponse
    {
        $daily = [];
        for ($i = 14; $i >= 0; $i--) {
            // Generates date representation formatted as "MM-DD", e.g. "05-20"
            $dateStr = date('m-d', strtotime("-$i days"));
            $daily[] = [
                'date' => $dateStr,
                'users' => rand(5, 20),
                'views' => rand(20, 60),
            ];
        }

        $activeUsers = array_sum(array_column($daily, 'users'));
        $screenPageViews = array_sum(array_column($daily, 'views'));
        // Simulated bounce rate with realistic variance
        $bounceRate = number_format(rand(380, 480) / 10, 1) . '%';

        return response()->json([
            'totals' => [
                'activeUsers' => $activeUsers,
                'screenPageViews' => $screenPageViews,
                'bounceRate' => $bounceRate,
            ],
            'daily' => $daily,
        ], 200);
    }
}
