<?php

namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;


class TeamsTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('teams')->insert([
            [
                'name' => 'Admin',
                'password' => Hash::make('admin123'), // kies je wachtwoord
                'role' => 'admin',
                'members' => 'Admin User',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
