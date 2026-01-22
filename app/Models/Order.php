<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'subtotal',
        'tax',
        'shipping',
        'total',
        'coupon_code',
        'discount',
        'shipping_first_name',
        'shipping_last_name',
        'shipping_email',
        'shipping_phone',
        'shipping_address1',
        'shipping_address2',
        'shipping_city',
        'shipping_state',
        'shipping_zip',
        'shipping_country',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'float',
            'tax' => 'float',
            'shipping' => 'float',
            'total' => 'float',
            'discount' => 'float',
        ];
    }

    /**
     * Status options.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get all status options.
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_SHIPPED => 'Shipped',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /**
     * Get the user that owns the order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the order items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Generate a unique order number.
     */
    public static function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $timestamp = now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -4));
        return "{$prefix}-{$timestamp}-{$random}";
    }

    /**
     * Get the customer's full name.
     */
    public function getCustomerNameAttribute(): string
    {
        return trim("{$this->shipping_first_name} {$this->shipping_last_name}");
    }

    /**
     * Get the formatted shipping address.
     */
    public function getShippingAddressAttribute(): string
    {
        $parts = array_filter([
            $this->shipping_address1,
            $this->shipping_address2,
            $this->shipping_city,
            $this->shipping_state,
            $this->shipping_zip,
            $this->shipping_country,
        ]);
        
        return implode(', ', $parts);
    }

    /**
     * Get the human-readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::statuses()[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Return order data for API response.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'shipping' => $this->shipping,
            'total' => $this->total,
            'customer_name' => $this->customer_name,
            'shipping_address' => $this->shipping_address,
            'shipping_email' => $this->shipping_email,
            'items_count' => $this->items_count ?? $this->items->count(),
            'items' => $this->items->map(fn($item) => $item->toApiArray()),
            'created_at' => $this->created_at->toISOString(),
            'created_at_formatted' => $this->created_at->format('M d, Y g:i A'),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
