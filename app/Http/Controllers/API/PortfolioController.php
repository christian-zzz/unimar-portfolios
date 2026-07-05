<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PortfolioController extends Controller
{
    /**
     * Get or create the authenticated user's portfolio.
     */
    public function getCurrent(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Find or create a portfolio for the current user (1:1 relation)
        $portfolio = $user->portfolio()->with('categories:id')->first();
        
        if (!$portfolio) {
            $baseSlug = Str::slug($user->name);
            $slug = $baseSlug;
            
            // Guarantee unique slug
            $counter = 1;
            while (Portfolio::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . rand(1000, 9999);
                $counter++;
            }
            
            $portfolio = Portfolio::create([
                'user_id' => $user->id,
                'title' => 'Portafolio de ' . $user->name,
                'slug' => $slug,
                'draft_content' => null,
                'published_content' => null,
                'settings' => [
                    'theme' => [
                        'colors' => [
                            'primary' => '#2563eb',
                            'secondary' => '#1d4ed8',
                            'background' => '#ffffff',
                            'text' => '#171717',
                        ],
                        'typography' => [
                            'headingFont' => 'Inter',
                            'bodyFont' => 'Inter',
                        ]
                    ]
                ],
                'is_published' => false,
            ]);
            $portfolio->load('categories:id');
        }
        
        return response()->json($portfolio);
    }

    /**
     * Save the editor draft state.
     */
    public function saveDraft(Request $request): JsonResponse
    {
        $portfolio = $request->user()->portfolio;
        
        if (!$portfolio) {
            return response()->json([
                'message' => 'No se encontró un portafolio para este usuario.',
            ], 404);
        }

        $validated = $request->validate([
            'draft_content' => ['required', 'array'],
            'settings' => ['nullable', 'array'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $portfolio->update([
            'draft_content' => $validated['draft_content'],
            'settings' => $validated['settings'] ?? $portfolio->settings,
            'title' => $validated['title'] ?? $portfolio->title,
        ]);

        return response()->json([
            'message' => 'Borrador guardado exitosamente.',
            'portfolio' => $portfolio,
        ]);
    }

    /**
     * Update the portfolio metadata (title and slug).
     */
    public function updateMeta(Request $request): JsonResponse
    {
        $portfolio = $request->user()->portfolio;
        
        if (!$portfolio) {
            return response()->json([
                'message' => 'No se encontró un portafolio para este usuario.',
            ], 404);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:portfolios,slug,' . $portfolio->id],
        ]);

        $validated['slug'] = Str::slug($validated['slug']);

        $portfolio->update($validated);

        return response()->json([
            'message' => 'Metadatos del portafolio actualizados exitosamente.',
            'portfolio' => $portfolio,
        ]);
    }

    /**
     * Publish the current draft to production.
     */
    public function publish(Request $request): JsonResponse
    {
        try {
            $portfolio = $request->user()->portfolio;

            if (!$portfolio) {
                return response()->json([
                    'message' => 'No se encontró un portafolio para este usuario.',
                ], 404);
            }

            $request->validate([
                'thumbnail' => ['nullable', 'image', 'max:5120'], // Max 5MB
                'categories' => ['nullable', 'string'],
                'remove_thumbnail' => ['nullable', 'string', 'in:true,false,1,0'],
            ]);

            $thumbnailPath = $portfolio->thumbnail_path;
            $disk = env('FILESYSTEM_DISK_IMAGES', 'cloudinary');

            if ($request->input('remove_thumbnail') === 'true' || $request->input('remove_thumbnail') === '1') {
                if ($thumbnailPath) {
                    try {
                        if (str_starts_with($thumbnailPath, 'http')) {
                            $filename = basename($thumbnailPath);
                            if (\Illuminate\Support\Facades\Storage::disk($disk)->exists($filename)) {
                                \Illuminate\Support\Facades\Storage::disk($disk)->delete($filename);
                            }
                        } else {
                            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($thumbnailPath)) {
                                \Illuminate\Support\Facades\Storage::disk('public')->delete($thumbnailPath);
                            }
                        }
                    } catch (Throwable $e) {
                        Log::warning('Failed to delete old thumbnail: ' . $e->getMessage());
                    }
                }
                $thumbnailPath = null;
            } elseif ($request->hasFile('thumbnail')) {
                $file = $request->file('thumbnail');

                // Delete old thumbnail if exists
                if ($thumbnailPath) {
                    try {
                        if (str_starts_with($thumbnailPath, 'http')) {
                            $filename = basename($thumbnailPath);
                            if (\Illuminate\Support\Facades\Storage::disk($disk)->exists($filename)) {
                                \Illuminate\Support\Facades\Storage::disk($disk)->delete($filename);
                            }
                        } else {
                            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($thumbnailPath)) {
                                \Illuminate\Support\Facades\Storage::disk('public')->delete($thumbnailPath);
                            }
                        }
                    } catch (Throwable $e) {
                        Log::warning('Failed to delete old thumbnail: ' . $e->getMessage());
                    }
                }

                // Upload new thumbnail
                try {
                    $path = $file->store('thumbnails', $disk);
                    $thumbnailPath = \Illuminate\Support\Facades\Storage::disk($disk)->url($path);
                } catch (Throwable $e) {
                    Log::error('Failed to upload new thumbnail: ' . $e->getMessage());
                    $thumbnailPath = $portfolio->thumbnail_path;
                }

                // Sync with media library
                if ($thumbnailPath !== $portfolio->thumbnail_path) {
                    try {
                        $media = new \App\Models\Media();
                        $media->user_id = $request->user()->id;
                        $media->portfolio_id = $portfolio->id;
                        $media->file_name = $file->getClientOriginalName();
                        $media->file_path = $path ?? $file->getClientOriginalName();
                        $media->mime_type = $file->getMimeType();
                        $media->size = $file->getSize();
                        $media->disk = $disk;
                        $media->save();
                    } catch (Throwable $e) {
                        Log::warning('Could not sync cover image to media library: ' . $e->getMessage());
                    }
                }
            }

            // Copy draft_content to published_content and toggle live state
            $portfolio->update([
                'published_content' => $portfolio->draft_content,
                'is_published' => true,
                'thumbnail_path' => $thumbnailPath,
            ]);

            // Sync categories
            $categoryIds = [];
            if ($request->has('categories')) {
                $categoriesVal = $request->input('categories');
                if (is_array($categoriesVal)) {
                    $categoryIds = $categoriesVal;
                } elseif (is_string($categoriesVal) && trim($categoriesVal) !== '') {
                    $categoryIds = array_filter(explode(',', $categoriesVal));
                }
            }
            $portfolio->categories()->sync($categoryIds);

            $portfolio->load('categories:id');

            return response()->json([
                'message' => 'Portafolio publicado exitosamente en vivo.',
                'portfolio' => $portfolio,
            ]);
        } catch (Throwable $e) {
            Log::error('Portfolio publish failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al publicar el portafolio. Intente de nuevo.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Unpublish the portfolio (taking it offline).
     */
    public function unpublish(Request $request): JsonResponse
    {
        $portfolio = $request->user()->portfolio;
        
        if (!$portfolio) {
            return response()->json([
                'message' => 'No se encontró un portafolio para este usuario.',
            ], 404);
        }

        $portfolio->update([
            'is_published' => false,
        ]);

        return response()->json([
            'message' => 'Portafolio retirado del aire exitosamente.',
            'portfolio' => $portfolio,
        ]);
    }

    /**
     * Retrieve all published portfolios (Public Endpoint).
     */
    public function indexPublic(Request $request): JsonResponse
    {
        $query = Portfolio::with(['user:id,name,avatar_url', 'categories:id,name,slug'])
            ->where('is_published', true);

        // Search by title or user name
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by tags/categories slugs
        if ($request->filled('tags')) {
            $tags = explode(',', $request->input('tags'));
            $query->whereHas('categories', function ($cq) use ($tags) {
                $cq->whereIn('slug', $tags);
            });
        }

        $portfolios = $query->latest('updated_at')
            ->paginate(12);

        return response()->json($portfolios);
    }

    /**
     * Retrieve a published portfolio by slug (Public Endpoint).
     */
    public function showPublic(string $slug): JsonResponse
    {
        $portfolio = Portfolio::with('user:id,name,email,avatar_url')
            ->where('slug', $slug)
            ->first();

        if (!$portfolio) {
            return response()->json([
                'message' => 'Portafolio no encontrado.',
            ], 404);
        }

        if (!$portfolio->is_published) {
            return response()->json([
                'message' => 'Este portafolio no está publicado actualmente.',
            ], 403);
        }

        return response()->json([
            'title' => $portfolio->title,
            'slug' => $portfolio->slug,
            'published_content' => $portfolio->published_content,
            'settings' => $portfolio->settings,
            'author' => [
                'name' => $portfolio->user->name,
                'email' => $portfolio->user->email,
                'avatar_url' => $portfolio->user->avatar_url,
            ],
            'last_updated' => $portfolio->updated_at->toIso8601String(),
        ]);
    }
}
