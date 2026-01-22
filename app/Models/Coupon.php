<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'min_order_amount',
        'max_discount_amount',
        'usage_limit',
        'usage_count',
        'is_active',
        'starts_at',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_value' => 'float',
            'min_order_amount' => 'float',
            'max_discount_amount' => 'float',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Discount type constants.
     */
    const TYPE_PERCENTAGE = 'percentage';
    const TYPE_FIXED = 'fixed';

    /**
     * Get the coupon usages.
     */
    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    /**
     * Normalize code to uppercase when setting.
     */
    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = strtoupper($value);
    }

    /**
     * Check if the coupon is valid for use.
     */
    public function isValid(?float $subtotal = null): array
    {
        // Check if active
        if (!$this->is_active) {
            return ['valid' => false, 'message' => 'This coupon is not active.'];
        }

        // Check start date
        if ($this->starts_at && now()->lt($this->starts_at)) {
            return ['valid' => false, 'message' => 'This coupon is not yet available.'];
        }

        // Check expiration
        if ($this->expires_at && now()->gt($this->expires_at)) {
            return ['valid' => false, 'message' => 'This coupon has expired.'];
        }

        // Check usage limit
        if ($this->usage_limit !== null && $this->usage_count >= $this->usage_limit) {
            return ['valid' => false, 'message' => 'This coupon has reached its usage limit.'];
        }

        // Check minimum order amount
        if ($subtotal !== null && $this->min_order_amount !== null && $subtotal < $this->min_order_amount) {
            return [
                'valid' => false,
                'message' => "Minimum order amount of $" . number_format($this->min_order_amount, 2) . " required."
            ];
        }

        return ['valid' => true, 'message' => 'Coupon is valid.'];
    }

    /**
     * Calculate the discount amount for a given subtotal.
     */
    public function calculateDiscount(float $subtotal): float
    {
        if ($this->discount_type === self::TYPE_PERCENTAGE) {
            $discount = $subtotal * ($this->discount_value / 100);
            
            // Apply max discount cap if set
            if ($this->max_discount_amount !== null) {
                $discount = min($discount, $this->max_discount_amount);
            }
            
            return round($discount, 2);
        } else {
            // Fixed amount discount
            return min($this->discount_value, $subtotal);
        }
    }

    /**
     * Increment the usage count.
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
        
        // Auto-deactivate if limit reached
        if ($this->usage_limit !== null && $this->usage_count >= $this->usage_limit) {
            $this->is_active = false;
            $this->save();
        }
    }

    /**
     * Get remaining usage count.
     */
    public function getRemainingUsageAttribute(): ?int
    {
        if ($this->usage_limit === null) {
            return null; // Unlimited
        }
        
        return max(0, $this->usage_limit - $this->usage_count);
    }

    /**
     * Check if coupon has unlimited usage.
     */
    public function hasUnlimitedUsageAttribute(): bool
    {
        return $this->usage_limit === null;
    }
}
