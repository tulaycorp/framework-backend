<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Get all products or filter by category.
     * Supports both category name (legacy) and category_id (new system).
     * Only returns active products (excludes draft/archived).
     */
    public function list(Request $request): JsonResponse
    {
        $category = $request->query('category', '');
        $categoryId = $request->query('category_id');

        $query = Product::query()->visibleInStore()->with('categoryRelation');

        if (!empty($categoryId)) {
            // Filter by category_id (new system - linked to Category model)
            $query->byCategoryId((int) $categoryId)->orderBy('name');
        } elseif (!empty($category)) {
            // Filter by category name or slug (supports both legacy string and new slug)
            $categoryModel = Category::where('name', $category)
                ->orWhere('slug', $category)
                ->first();
            
            if ($categoryModel) {
                // If we find a matching category, use category_id for products that have it
                $query->where(function ($q) use ($category, $categoryModel) {
                    $q->where('category_id', $categoryModel->id)
                      ->orWhere('category', $category);
                })->orderBy('name');
            } else {
                // Fallback to legacy string matching
                $query->byCategory($category)->orderBy('name');
            }
        } else {
            $query->orderBy('category')->orderBy('name');
        }

        $products = $query->get()->map(fn($product) => $product->toApiArray());

        return response()->json([
            'success' => true,
            'products' => $products,
        ]);
    }

    /**
     * Get a single product by ID.
     */
    public function get(string $id): JsonResponse
    {
        $product = Product::visibleInStore()->find($id);

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        return response()->json([
            'success' => true,
            'product' => $product->toApiArray(),
        ]);
    }

    /**
     * Get featured products.
     */
    public function featured(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 3);

        $products = Product::visibleInStore()->featured()
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn($product) => $product->toApiArray());

        return response()->json([
            'success' => true,
            'products' => $products,
        ]);
    }

    /**
     * Get all product categories.
     * Returns categories from the Category model (managed via admin panel).
     */
    public function categories(): JsonResponse
    {
        $categories = Category::active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'description' => $cat->description,
                'image_url' => $cat->image_url,
                'parent_id' => $cat->parent_id,
                'sort_order' => $cat->sort_order,
            ]);

        return response()->json([
            'success' => true,
            'categories' => $categories,
        ]);
    }
}
