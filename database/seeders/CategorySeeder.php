<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    /**
     * Seed the application's categories.
     */
    public function run(): void
    {
        $categories = [
            [
                'id' => 1,
                'name' => 'Hoodies',
                'slug' => 'hoodies',
                'description' => 'Premium heavy weight cotton hoodies.',
                'sort_order' => 0,
                'is_active' => true,
            ],
            [
                'id' => 2,
                'name' => 'T-Shirts',
                'slug' => 't-shirts',
                'description' => 'Boxy fit oversized tees.',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'id' => 3,
                'name' => 'Accessories',
                'slug' => 'accessories',
                'description' => 'Hats, bags, and more.',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'id' => 4,
                'name' => 'Pants',
                'slug' => 'pants',
                'description' => 'Cargo and sweatpants.',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'id' => 5,
                'name' => 'Outerwear',
                'slug' => 'outerwear',
                'description' => 'Jackets and coats.',
                'sort_order' => 4,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            DB::table('categories')->insert([
                'id' => $category['id'],
                'name' => $category['name'],
                'slug' => $category['slug'],
                'description' => $category['description'],
                'sort_order' => $category['sort_order'],
                'is_active' => $category['is_active'],
                'created_at' => '2023-10-01 12:00:00',
                'updated_at' => '2023-10-01 12:00:00',
            ]);
        }
    }
}
