<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

test('registration is rate limited per IP', function () {
    RateLimiter::increment(md5('register127.0.0.1'), amount: 5);

    $response = $this->post(route('register.store'), [
        'name' => 'Throttled User',
        'email' => 'throttled@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertTooManyRequests();
    expect(User::where('email', 'throttled@example.com')->exists())->toBeFalse();
});

test('reset link requests are rate limited per email and IP', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('forgot-password'.$user->email.'|127.0.0.1'), amount: 3);

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertTooManyRequests();
});

test('password resets are rate limited per email and IP', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('forgot-password'.$user->email.'|127.0.0.1'), amount: 3);

    $this->post(route('password.update'), [
        'token' => 'whatever',
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertTooManyRequests();
});

test('verification notifications are rate limited per user', function () {
    $user = User::factory()->unverified()->create();

    RateLimiter::increment(md5('verification'.$user->id), amount: 3);

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertTooManyRequests();
});
