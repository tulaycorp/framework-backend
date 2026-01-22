<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CouponSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing coupons to avoid duplicates
        DB::table('coupons')->delete();

        $coupons = [
            [
                'code' => 'WELCOME10',
                'description' => '10% off for new customers',
                'discount_type' => 'percentage',
                'discount_value' => 10.00,
                'min_order_amount' => 50.00,
                'is_active' => true,
            ],
            [
                'code' => 'SAVE20',
                'description' => '$20 off on orders over $100',
                'discount_type' => 'fixed',
                'discount_value' => 20.00,
                'min_order_amount' => 100.00,
                'is_active' => true,
            ],
            [
                'code' => 'FREESHIP',
                'description' => 'Free shipping code (Logic handled by frontend/total logic normally, but here as placeholder)',
                'discount_type' => 'fixed',
                'discount_value' => 0.00, // Or handled specially
                'min_order_amount' => 0.00,
                'is_active' => false, // Inactive example
            ],
        ];

        foreach ($coupons as $coupon) {
            Coupon::create($coupon);
        }
    }
}
