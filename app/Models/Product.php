<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Product extends Model
{
    /**
     * The primary key type is string (varchar ID like 'chair-1').
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'id',
        'sku',
        'barcode',
        'name',
        'description',
        'price',
        'compare_at_price',
        'cost_per_item',
        'category',
        'category_id',
        'vendor',
        'product_type',
        'tags',
        'featured',
        'status',
        'stock_quantity',
        'track_inventory',
        'continue_selling_when_out_of_stock',
        'image_url',
        'images',
        'weight',
        'weight_unit',
        'requires_shipping',
        'length',
        'width',
        'height',
        'dimension_unit',
        'taxable',
        'tax_code',
        'seo_title',
        'seo_description',
        'metafields',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'price' => 'float',
            'compare_at_price' => 'float',
            'cost_per_item' => 'float',
            'featured' => 'boolean',
            'stock_quantity' => 'integer',
            'track_inventory' => 'boolean',
            'continue_selling_when_out_of_stock' => 'boolean',
            'weight' => 'float',
            'length' => 'float',
            'width' => 'float',
            'height' => 'float',
            'requires_shipping' => 'boolean',
            'taxable' => 'boolean',
            'metafields' => 'array',
            'images' => 'array',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            if (empty($product->id)) {
                $product->id = Str::slug($product->name) . '-' . Str::random(5);
            }
            if (empty($product->sku)) {
                $product->sku = strtoupper(Str::random(8));
            }
        });
    }

    /**
     * Category relationship.
     */
    public function categoryRelation(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Scope a query to only include featured products.
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('featured', true);
    }

    /**
     * Scope a query to only include active products.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to filter by category.
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Scope a query to filter by category ID.
     */
    public function scopeByCategoryId(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope a query to include only products visible in the store.
     * Checks status and inventory/visibility settings.
     */
    public function scopeVisibleInStore(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->where('track_inventory', false)
                  ->orWhere('stock_quantity', '>', 0)
                  ->orWhere('continue_selling_when_out_of_stock', true);
            });
    }

    /**
     * Check if product is on sale.
     */
    public function getOnSaleAttribute(): bool
    {
        return $this->compare_at_price && $this->compare_at_price > $this->price;
    }

    /**
     * Get discount percentage.
     */
    public function getDiscountPercentAttribute(): ?int
    {
        if (!$this->on_sale) return null;
        return (int) round((1 - ($this->price / $this->compare_at_price)) * 100);
    }

    /**
     * Check if in stock.
     */
    public function getInStockAttribute(): bool
    {
        if (!$this->track_inventory) return true;
        // Old logic: if ($this->continue_selling_when_out_of_stock) return true;
        // New logic: Only true if actually in stock. The flag now controls visibility, not stock status.
        return $this->stock_quantity > 0;
    }

    /**
     * Get image URL with alias for frontend compatibility.
     */
    public function getImgAttribute(): ?string
    {
        return $this->image_url;
    }

    /**
     * Get tags as array.
     */
    public function getTagsArrayAttribute(): array
    {
        if (empty($this->tags)) return [];
        return array_map('trim', explode(',', $this->tags));
    }

    /**
     * Return product data for API response.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
            'cost_per_item' => $this->cost_per_item,
            'on_sale' => $this->on_sale,
            'discount_percent' => $this->discount_percent,
            'category' => $this->category,
            'category_id' => $this->category_id,
            'category_name' => $this->categoryRelation?->name,
            'vendor' => $this->vendor,
            'product_type' => $this->product_type,
            'tags' => $this->tags,
            'tags_array' => $this->tags_array,
            'featured' => $this->featured,
            'status' => $this->status,
            'stock_quantity' => $this->stock_quantity,
            'track_inventory' => $this->track_inventory,
            'continue_selling_when_out_of_stock' => $this->continue_selling_when_out_of_stock,
            'show_when_out_of_stock' => $this->continue_selling_when_out_of_stock, // Alias
            'in_stock' => $this->in_stock,
            'image_url' => $this->image_url,
            'images' => $this->images ?? [],
            'img' => $this->image_url,
            // Shipping information
            'weight' => $this->weight,
            'weight_unit' => $this->weight_unit,
            'requires_shipping' => $this->requires_shipping,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'dimension_unit' => $this->dimension_unit,
            // Tax settings
            'taxable' => $this->taxable,
            'tax_code' => $this->tax_code,
            // SEO
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            // Custom metafields
            'metafields' => $this->metafields ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
