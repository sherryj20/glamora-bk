<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Quita/ comenta cualquier factory
        // \App\Models\User::factory(10)->create();
        // \App\Models\User::factory()->create([...]);

        $this->call([
            UserSeeder::class,  // ← usa tu seeder con phone/role
        ]);
    }
}
