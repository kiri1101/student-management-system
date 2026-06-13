<?php

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

test('users can authenticate using their phone number', function () {
    $user = User::factory()->withPhone('+237612345678')->create();

    $this->post(route('login.store'), [
        'email' => '+237612345678',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
});

test('phone login normalizes spaced and punctuated input to the stored value', function () {
    $user = User::factory()->withPhone('+237612345678')->create();

    $this->post(route('login.store'), [
        'email' => '+237 6 12-34.56 78',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
});

test('phone login fails with the wrong password', function () {
    User::factory()->withPhone('+237612345678')->create();

    $this->post(route('login.store'), [
        'email' => '+237612345678',
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('an unknown phone number does not authenticate', function () {
    User::factory()->withPhone('+237612345678')->create();

    $this->post(route('login.store'), [
        'email' => '+237600000000',
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('the login resolver still issues a single users query when a phone is submitted', function () {
    DB::enableQueryLog();

    $this->post(route('login.store'), [
        'email' => '+237600000000',
        'password' => 'password',
    ]);

    $userQueries = collect(DB::getQueryLog())
        ->filter(fn (array $entry): bool => str_starts_with($entry['query'], 'select') && str_contains($entry['query'], 'users'))
        ->count();

    expect($userQueries)->toBe(1);
    $this->assertGuest();
});

test('phone numbers are unique across users', function () {
    User::factory()->withPhone('+237612345678')->create();

    expect(fn () => User::factory()->withPhone('+237612345678')->create())
        ->toThrow(UniqueConstraintViolationException::class);
});
