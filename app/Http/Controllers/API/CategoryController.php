<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource for public feed.
     */
    public function indexPublic(): JsonResponse
    {
        $categories = Category::orderBy('name')->get();
        return response()->json($categories);
    }

    /**
     * Display a listing of the resource (Admin).
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $categories = Category::orderBy('name')->paginate(15);
        return response()->json($categories);
    }

    /**
     * Store a newly created resource in storage (Admin).
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'slug' => 'nullable|string|max:255|unique:categories,slug',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        } else {
            $validated['slug'] = Str::slug($validated['slug']);
        }

        $category = Category::create($validated);

        return response()->json([
            'message' => 'Categoría creada exitosamente.',
            'category' => $category,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        return response()->json($category);
    }

    /**
     * Update the specified resource in storage (Admin).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
            'slug' => 'nullable|string|max:255|unique:categories,slug,' . $category->id,
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        } else {
            $validated['slug'] = Str::slug($validated['slug']);
        }

        $category->update($validated);

        return response()->json([
            'message' => 'Categoría actualizada exitosamente.',
            'category' => $category,
        ]);
    }

    /**
     * Remove the specified resource from storage (Admin).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $category = Category::findOrFail($id);
        
        // Detach related portfolios if any
        $category->portfolios()->detach();
        $category->delete();

        return response()->json([
            'message' => 'Categoría eliminada exitosamente.',
        ]);
    }
}
