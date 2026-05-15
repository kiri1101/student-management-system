<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            DocumentTypesSeeder::class,
            DemoReferencesSeeder::class,
            LocalStaffSeeder::class,
        ]);

        // `User::factory()` triggers `fake()` in the factory's `definition()`,
        // which is unavailable on environments installed with `--no-dev`.
        // Use `firstOrCreate` so the seed path stays faker-free.
        User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $admin->assignRole(RoleName::Admin);
    }
}
