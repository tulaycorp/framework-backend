<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Seed the application's database with admin users.
     */
    public function run(): void
    {
        $admins = [
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => 'admin@example.com',
                'password_hash' => Hash::make('password'),
            ],
            // Add more admin users here as needed:
            // [
            //     'first_name' => 'Super',
            //     'last_name' => 'Admin',
            //     'email' => 'superadmin@email.com',
            //     'password_hash' => Hash::make('securepassword'),
            // ],
        ];

        foreach ($admins as $admin) {
            Admin::create($admin);
        }
    }
}
