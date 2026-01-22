<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * List all categories.
     * Cached for 5 minutes.
     */
    public function index(Request $request): JsonResponse
    {
        $activeOnly = $request->has('active_only');
        $cacheKey = 'admin.categories.' . ($activeOnly ? 'active' : 'all');
        
        $categories = cache()->remember($cacheKey, 300, function () use ($request) {
            $query = Category::query();

            // Filter by active status
            if ($request->has('active_only')) {
                $query->active();
            }

            // Eager load product counts
            return $query->withCount('products')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn($cat) => $cat->toApiArray())
                ->toArray();
        });

        return response()->json([
            'success' => true,
            'categories' => $categories,
        ]);
    }

    /**
     * Get a single category.
     */
    public function show(int $id): JsonResponse
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json(['error' => 'Category not found'], 404);
        }

        return response()->json([
            'success' => true,
            'category' => $category->toApiArray(),
        ]);
    }

    /**
     * Create a new category.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',

            'description' => 'nullable|string',


            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $category = Category::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'category' => $category->toApiArray(),
        ], 201);
    }

    /**
     * Update a category.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json(['error' => 'Category not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',

            'description' => 'nullable|string',


            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        // Prevent category from being its own parent


        $category->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'category' => $category->fresh()->toApiArray(),
        ]);
    }

    /**
     * Delete a category.
     */
    public function destroy(int $id): JsonResponse
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json(['error' => 'Category not found'], 404);
        }

        // Check for products in this category
        $productCount = $category->products()->count();
        if ($productCount > 0) {
            return response()->json([
                'error' => "Cannot delete category with {$productCount} products. Move products first.",
            ], 422);
        }



        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ]);
    }
}
