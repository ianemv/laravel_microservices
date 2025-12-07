<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create default user matching Python service seed data
        User::firstOrCreate(
            ['email' => 'georgio@email.com'],
            [
                'name' => 'Georgio',
                'password' => Hash::make('Admin123'),
            ]
        );
    }
}
