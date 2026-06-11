<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

it('provisions the known-credential convenience accounts in local/testing', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'admin@example.com')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'test@example.com')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'sao@example.com')->exists())->toBeTrue();
});

it('creates no user accounts when seeded in a production environment', function () {
    $this->app['env'] = 'production';

    // `--force` skips the production confirmation prompt, mirroring how a
    // deploy pipeline would invoke the seeder.
    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])
        ->assertSuccessful();

    expect(User::count())->toBe(0);
});
