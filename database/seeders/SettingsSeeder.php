<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    /**
     * Seed the application's settings.
     */
    public function run(): void
    {
        $settings = [
            ['key' => 'maintenance_mode', 'value' => 'false'],
            ['key' => 'announcement_enabled', 'value' => 'false'],
            ['key' => 'announcement_message', 'value' => '⚡ WINTER SALE: 50% OFF ⚡'],
            ['key' => 'sold_out_title', 'value' => 'Sold Out'],
            ['key' => 'sold_out_subtitle', 'value' => 'Last Drop'],
            ['key' => 'sold_out_title_shop', 'value' => 'Sold Out'],
            ['key' => 'sold_out_subtitle_shop', 'value' => 'Last Drop'],
            ['key' => 'sold_out_image_home', 'value' => 'https://images.unsplash.com/photo-1643387848945-da63360662f4?w=800&q=80'],
            ['key' => 'sold_out_image_shop', 'value' => 'https://images.unsplash.com/photo-1643387848945-da63360662f4?w=800&q=80'],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->insert([
                'key' => $setting['key'],
                'value' => $setting['value'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
