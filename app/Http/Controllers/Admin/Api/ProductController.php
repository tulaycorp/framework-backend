<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of products.
     * Cached per unique query for 2 minutes.
     */
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'admin.products.' . md5(json_encode($request->all()));
        
        $result = cache()->remember($cacheKey, 120, function () use ($request) {
            $query = Product::with('categoryRelation');
            
            // Search
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('category', 'like', "%{$search}%");
                });
            }
            
            // Filter by category (supports both category name and category_id)
            if ($categoryId = $request->get('category_id')) {
                $query->where('category_id', $categoryId);
            } elseif ($category = $request->get('category')) {
                // Support legacy string-based filtering
                $query->where('category', $category);
            }
            
            // Filter by featured
            if ($request->has('featured')) {
                $query->where('featured', $request->boolean('featured'));
            }
            
            // Filter by status
            if ($status = $request->get('status')) {
                $query->where('status', $status);
            }
            
            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDir = $request->get('sort_dir', 'desc');
            $query->orderBy($sortBy, $sortDir);
            
            // Pagination
            $perPage = min($request->get('per_page', 15), 100);
            $products = $query->paginate($perPage);
            
            return [
                'data' => $products->items(),
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Basic info
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            
            // Pricing
            'price' => 'required|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'cost_per_item' => 'nullable|numeric|min:0',
            
            // Organization
            'category_id' => 'nullable|exists:categories,id',
            'category' => 'nullable|string|max:255',
            'vendor' => 'nullable|string|max:255',
            'product_type' => 'nullable|string|max:255',
            'tags' => 'nullable|string|max:1000',
            'featured' => 'boolean',
            'status' => 'nullable|in:active,draft,archived',
            
            // Inventory
            'sku' => 'nullable|string|max:255|unique:products,sku',
            'barcode' => 'nullable|string|max:255',
            'stock_quantity' => 'required|integer|min:0',
            'track_inventory' => 'boolean',
            'continue_selling_when_out_of_stock' => 'boolean',
            
            // Media
            'image_url' => 'nullable|string|max:500',
            'images' => 'nullable|array|max:9',
            'images.*' => 'nullable|string|max:500',
            
            // Shipping
            'weight' => 'nullable|numeric|min:0',
            'weight_unit' => 'nullable|in:kg,g,lb,oz',
            'requires_shipping' => 'boolean',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'dimension_unit' => 'nullable|in:cm,in,m',
            
            // Tax
            'taxable' => 'boolean',
            'tax_code' => 'nullable|string|max:100',
            
            // SEO
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            
            // Custom metafields
            'metafields' => 'nullable|array',
            'metafields.*.key' => 'required_with:metafields|string|max:255',
            'metafields.*.value' => 'required_with:metafields',
            'metafields.*.type' => 'nullable|string|in:string,integer,boolean,json,url,date',
        ]);
        
        // Generate ID from name if not provided
        $validated['id'] = $request->get('id', Str::slug($validated['name']) . '-' . Str::random(4));
        $validated['featured'] = $validated['featured'] ?? false;
        $validated['status'] = $validated['status'] ?? 'active';
        $validated['track_inventory'] = $validated['track_inventory'] ?? true;
        $validated['continue_selling_when_out_of_stock'] = $validated['continue_selling_when_out_of_stock'] ?? false;
        $validated['requires_shipping'] = $validated['requires_shipping'] ?? true;
        $validated['taxable'] = $validated['taxable'] ?? true;
        $validated['weight_unit'] = $validated['weight_unit'] ?? 'kg';
        $validated['dimension_unit'] = $validated['dimension_unit'] ?? 'cm';
        
        // If category_id is provided, also set the category name for backward compatibility
        if (!empty($validated['category_id'])) {
            $category = Category::find($validated['category_id']);
            if ($category) {
                $validated['category'] = $category->name;
            }
        }
        
        $product = Product::create($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product->toApiArray(),
        ], 201);
    }

    /**
     * Display the specified product.
     */
    public function show(string $id): JsonResponse
    {
        $product = Product::with('categoryRelation')->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $product->toApiArray(),
        ]);
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        
        $validated = $request->validate([
            // Basic info
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            
            // Pricing
            'price' => 'sometimes|required|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'cost_per_item' => 'nullable|numeric|min:0',
            
            // Organization
            'category_id' => 'nullable|exists:categories,id',
            'category' => 'nullable|string|max:255',
            'vendor' => 'nullable|string|max:255',
            'product_type' => 'nullable|string|max:255',
            'tags' => 'nullable|string|max:1000',
            'featured' => 'boolean',
            'status' => 'nullable|in:active,draft,archived',
            
            // Inventory
            'sku' => 'nullable|string|max:255|unique:products,sku,' . $product->id,
            'barcode' => 'nullable|string|max:255',
            'stock_quantity' => 'sometimes|required|integer|min:0',
            'track_inventory' => 'boolean',
            'continue_selling_when_out_of_stock' => 'boolean',
            
            // Media
            'image_url' => 'nullable|string|max:500',
            'images' => 'nullable|array|max:9',
            'images.*' => 'nullable|string|max:500',
            
            // Shipping
            'weight' => 'nullable|numeric|min:0',
            'weight_unit' => 'nullable|in:kg,g,lb,oz',
            'requires_shipping' => 'boolean',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'dimension_unit' => 'nullable|in:cm,in,m',
            
            // Tax
            'taxable' => 'boolean',
            'tax_code' => 'nullable|string|max:100',
            
            // SEO
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            
            // Custom metafields
            'metafields' => 'nullable|array',
            'metafields.*.key' => 'required_with:metafields|string|max:255',
            'metafields.*.value' => 'required_with:metafields',
            'metafields.*.type' => 'nullable|string|in:string,integer,boolean,json,url,date',
        ]);
        
        // If category_id is provided, also update the category name for backward compatibility
        if (isset($validated['category_id'])) {
            if ($validated['category_id']) {
                $category = Category::find($validated['category_id']);
                if ($category) {
                    $validated['category'] = $category->name;
                }
            } else {
                // category_id is null, clear both
                $validated['category'] = null;
            }
        }
        
        $product->update($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product->fresh()->load('categoryRelation')->toApiArray(),
        ]);
    }

    /**
     * Remove the specified product.
     */
    public function destroy(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ]);
    }

    /**
     * Get all categories from Category model.
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
            ]);
        
        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Generate a presigned URL for direct upload to Cloudflare R2.
     */
    public function getPresignedUploadUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fileName' => 'required|string|max:255',
            'fileType' => 'required|string|max:100',
        ]);
        
        // Generate unique file key
        $fileKey = 'uploads/' . Str::uuid() . '-' . $validated['fileName'];
        
        try {
            // Create S3 client directly for Cloudflare R2
            $s3Config = [
                'version' => 'latest',
                'region' => 'auto',
                'endpoint' => env('CLOUDFLARE_R2_ENDPOINT'),
                'credentials' => [
                    'key' => env('CLOUDFLARE_R2_ACCESS_KEY_ID'),
                    'secret' => env('CLOUDFLARE_R2_SECRET_ACCESS_KEY'),
                ],
                'use_path_style_endpoint' => false,
            ];
            
            $s3Client = new \Aws\S3\S3Client($s3Config);
            
            // Create PutObject command
            $command = $s3Client->getCommand('PutObject', [
                'Bucket' => env('CLOUDFLARE_R2_BUCKET'),
                'Key' => $fileKey,
                'ContentType' => $validated['fileType'],
            ]);
            
            // Generate presigned URL valid for 60 seconds
            $presignedRequest = $s3Client->createPresignedRequest($command, '+60 seconds');
            $uploadUrl = (string) $presignedRequest->getUri();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'uploadUrl' => $uploadUrl,
                    'fileKey' => $fileKey,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate presigned URL: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload a product image to Cloudflare R2.
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,gif,webp|max:5120', // 5MB max
        ]);
        
        $file = $request->file('image');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = 'products/' . $filename;
        
        try {
            // Upload to R2
            $disk = \Illuminate\Support\Facades\Storage::disk('r2');
            $disk->put($path, file_get_contents($file), 'public');
            
            // Get the public URL
            $url = config('filesystems.disks.r2.url') . '/' . $path;
            
            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => [
                    'url' => $url,
                    'path' => $path,
                    'filename' => $filename,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image: ' . $e->getMessage(),
            ], 500);
        }
    }
}
