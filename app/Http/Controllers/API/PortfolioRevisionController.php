<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PortfolioRevisionController extends Controller
{
    /**
     * List all revisions for the auth user's portfolio.
     */
    public function index(Request $request): JsonResponse
    {
        $portfolio = $request->user()->portfolio;

        if (!$portfolio) {
            return response()->json([
                'message' => 'No se encontró un portafolio para este usuario.',
            ], 404);
        }

        $revisions = $portfolio->revisions()
            ->latest('created_at')
            ->get(['id', 'portfolio_id', 'label', 'created_at']);

        return response()->json([
            'revisions' => $revisions,
        ]);
    }

    /**
     * Store a new manual revision.
     */
    public function store(Request $request): JsonResponse
    {
        $portfolio = $request->user()->portfolio;

        if (!$portfolio) {
            return response()->json([
                'message' => 'No se encontró un portafolio para este usuario.',
            ], 404);
        }

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
        ]);

        $revisionCount = $portfolio->revisions()->count();
        $label = $validated['label'] ?? ('Revisión #' . ($revisionCount + 1));

        $revision = $portfolio->revisions()->create([
            'content' => $portfolio->draft_content,
            'label' => $label,
        ]);

        // Enforce 20-revision cap
        if ($revisionCount + 1 > 20) {
            $portfolio->revisions()
                ->oldest('created_at')
                ->take(($revisionCount + 1) - 20)
                ->delete();
        }

        return response()->json([
            'message' => 'Snapshot guardado exitosamente.',
            'revision' => [
                'id' => $revision->id,
                'portfolio_id' => $revision->portfolio_id,
                'label' => $revision->label,
                'created_at' => $revision->created_at,
            ],
        ]);
    }

    /**
     * Update the label of a revision.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $portfolio = $request->user()->portfolio;

        if (!$portfolio) {
            return response()->json([
                'message' => 'No se encontró un portafolio para este usuario.',
            ], 404);
        }

        $revision = $portfolio->revisions()->where('id', $id)->first();

        if (!$revision) {
            return response()->json([
                'message' => 'Revisión no encontrada.',
            ], 404);
        }

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
        ]);

        // Disable updated_at handling if it's implicitly trying to update it, though model says UPDATED_AT = null
        $revision->label = $validated['label'];
        $revision->save();

        return response()->json([
            'message' => 'Nombre de la revisión actualizado.',
            'revision' => [
                'id' => $revision->id,
                'portfolio_id' => $revision->portfolio_id,
                'label' => $revision->label,
                'created_at' => $revision->created_at,
            ],
        ]);
    }

    /**
     * Restore a revision to the portfolio's draft_content.
     */
    public function restore(Request $request, string $id): JsonResponse
    {
        $portfolio = $request->user()->portfolio;

        if (!$portfolio) {
            return response()->json([
                'message' => 'No se encontró un portafolio para este usuario.',
            ], 404);
        }

        $revision = $portfolio->revisions()->where('id', $id)->first();

        if (!$revision) {
            return response()->json([
                'message' => 'Revisión no encontrada.',
            ], 404);
        }

        $portfolio->update([
            'draft_content' => $revision->content,
        ]);

        return response()->json([
            'message' => 'Portafolio restaurado a la versión seleccionada.',
            'portfolio' => $portfolio,
        ]);
    }

    /**
     * Delete a revision.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $portfolio = $request->user()->portfolio;

        if (!$portfolio) {
            return response()->json([
                'message' => 'No se encontró un portafolio para este usuario.',
            ], 404);
        }

        $revision = $portfolio->revisions()->where('id', $id)->first();

        if (!$revision) {
            return response()->json([
                'message' => 'Revisión no encontrada.',
            ], 404);
        }

        $revision->delete();

        return response()->json([
            'message' => 'Revisión eliminada exitosamente.',
        ]);
    }
}
