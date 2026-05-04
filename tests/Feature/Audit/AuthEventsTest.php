<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

test('a successful login fires the LoggedIn audit row tied to the user', function () {
    $user = User::factory()->create();

    Event::dispatch(new Login('web', $user, false));

    $log = AuditLog::query()
        ->where('action', AuditAction::LoggedIn->value)
        ->where('subject_type', $user->getMorphClass())
        ->where('subject_id', $user->id)
        ->sole();

    expect($log->user_id)->toBe($user->id)
        ->and($log->context)->toHaveKey('ip');
});

test('a failed login fires the LoginFailed audit row', function () {
    Event::dispatch(new Failed('web', null, ['email' => 'ghost@example.com']));

    $log = AuditLog::query()
        ->where('action', AuditAction::LoginFailed->value)
        ->sole();

    expect($log->user_id)->toBeNull()
        ->and($log->subject_id)->toBeNull()
        ->and($log->context)->toHaveKey('ip');
});

test('a logout fires the LoggedOut audit row tied to the user', function () {
    $user = User::factory()->create();

    Event::dispatch(new Logout('web', $user));

    $log = AuditLog::query()
        ->where('action', AuditAction::LoggedOut->value)
        ->where('subject_id', $user->id)
        ->sole();

    expect($log->user_id)->toBe($user->id);
});

test('logging in via the HTTP route writes a LoggedIn audit row', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect();

    expect(AuditLog::query()
        ->where('action', AuditAction::LoggedIn->value)
        ->where('subject_id', $user->id)
        ->exists())->toBeTrue();
});

test('an HTTP login attempt with an unknown identifier writes a LoginFailed audit row', function () {
    $this->post(route('login.store'), [
        'email' => 'ghost@example.com',
        'password' => 'password',
    ]);

    expect(AuditLog::query()
        ->where('action', AuditAction::LoginFailed->value)
        ->exists())->toBeTrue();
});

test('logging out via the HTTP route writes a LoggedOut audit row', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'));

    expect(Auth::check())->toBeFalse()
        ->and(AuditLog::query()
            ->where('action', AuditAction::LoggedOut->value)
            ->where('subject_id', $user->id)
            ->exists())->toBeTrue();
});
