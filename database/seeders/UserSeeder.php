<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->insert([
            [
                'name' => 'Admin User',
                'email' => 'admin@gmail.com',
                'password' => Hash::make('123456'),
                'phone' => '111111',
                'role' => 1, // 1 = admin
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Cliente User',
                'email' => 'cliente@gmail.com',
                'password' => Hash::make('123456'),
                'phone' => '222222',
                'role' => 0, // 0 = customer
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
