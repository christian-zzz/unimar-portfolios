<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LighthouseController extends Controller
{
    /**
     * Run a Google PageSpeed Insights (Lighthouse) audit on a given URL for a specific category.
     */
    public function runAudit(Request $request): JsonResponse
    {
        // Extend PHP max execution time
        set_time_limit(120);

        $validated = $request->validate([
            'url' => ['required', 'url'],
            'category' => ['required', 'string', 'in:performance,accessibility,best-practices,seo'],
        ]);

        $url = $validated['url'];
        $category = $validated['category'];
        $apiKey = env('GOOGLE_PAGESPEED_API_KEY');

        // Target Endpoint for PageSpeed v5
        $endpoint = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

        // Build the URL manually to prevent Guzzle array parameter mapping issues
        $apiUrl = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url='.urlencode($url).'&category='.$category.'&locale=es';

        if ($apiKey) {
            $apiUrl .= '&key='.$apiKey;
        }

        try {
            // High timeout for Google API
            $response = Http::timeout(60)->get($apiUrl);

            if ($response->failed()) {
                return response()->json([
                    'message' => "La API de Google PageSpeed falló para la categoría: {$category}.",
                    'details' => $response->json() ?? $response->body(),
                ], 502);
            }

            $categories = $response->json('lighthouseResult.categories');
            $audits = $response->json('lighthouseResult.audits');

            // Google uses hyphenated key for best-practices
            $googleKey = $category;

            if (! $categories || ! isset($categories[$googleKey])) {
                return response()->json([
                    'message' => "No se pudieron extraer los resultados para la categoría: {$category}.",
                    'details' => $response->json(),
                ], 500);
            }

            // Extract category score
            $score = isset($categories[$googleKey]['score']) ? (int) round($categories[$googleKey]['score'] * 100) : null;

            // Extract category audits and failed recommendations
            $recommendations = [];
            if ($audits && is_array($audits) && isset($categories[$googleKey]['auditRefs'])) {
                $auditRefs = collect($categories[$googleKey]['auditRefs'])->pluck('id')->toArray();

                foreach ($auditRefs as $auditId) {
                    if (isset($audits[$auditId])) {
                        $audit = $audits[$auditId];

                        if (isset($audit['score']) && $audit['score'] !== null && $audit['score'] < 1) {
                            $recommendations[] = [
                                'id' => $auditId,
                                'title' => $audit['title'] ?? '',
                                'description' => $audit['description'] ?? '',
                                'score' => $audit['score'],
                            ];
                        }
                    }
                }

                // Sort recommendations by score ascending
                usort($recommendations, function ($a, $b) {
                    return $a['score'] <=> $b['score'];
                });

                // Take top 4 most critical issues
                $recommendations = array_slice($recommendations, 0, 4);
            }

            return response()->json([
                'category' => $category,
                'score' => $score,
                'recommendations' => $recommendations,
                'raw' => $response->json(),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error de conexión con la API de Google PageSpeed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save the compiled Lighthouse audit scores in the database.
     */
    public function saveResults(Request $request): JsonResponse
    {
        $portfolio = $request->user()->portfolio;

        if (!$portfolio) {
            return response()->json([
                'message' => 'No se encontró un portafolio para este usuario.',
            ], 404);
        }

        $validated = $request->validate([
            'scores' => ['required', 'array'],
        ]);

        $portfolio->update([
            'lighthouse_scores' => $validated['scores'],
            'last_audited_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Métricas de auditoría guardadas correctamente.',
            'portfolio' => $portfolio,
        ], 200);
    }
}
