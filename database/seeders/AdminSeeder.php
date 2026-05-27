<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password123'),
                'role' => 'super_admin',
                'unit_id' => null,
                'status' => 'aktif'
            ]
        );

        Admin::firstOrCreate(
            ['email' => 'adminunit1@example.com'],
            [
                'name' => 'Admin Unit 1',
                'password' => Hash::make('password123'),
                'role' => 'admin_unit',
                'unit_id' => 1,
                'status' => 'aktif'
            ]
        );

        Admin::firstOrCreate(
            ['email' => 'monitoring@example.com'],
            [
                'name' => 'Monitoring',
                'password' => Hash::make('password123'),
                'role' => 'monitoring',
                'unit_id' => null,
                'status' => 'aktif'
            ]
        );
    }
}
