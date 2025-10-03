<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Optioneel: test user (kan je weghalen als je hem niet nodig hebt)
        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        // 👇 Dit roept jouw TeamsTableSeeder aan
        $this->call([
            TeamsTableSeeder::class,
        ]);
    }
}
