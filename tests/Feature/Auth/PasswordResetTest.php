<?php

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('password cannot be reset with invalid token', function () {
    $user = User::factory()->create();

    $response = $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('email');
});

test('resetting the password reactivates a soft-deleted non-staff account', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'returning@example.com',
        'name' => 'Returning Student',
    ]);
    $user->assignRole(RoleName::Student);
    $user->assignRole(RoleName::Applicant);
    $user->delete();

    $this->post(route('password.email'), ['email' => 'returning@example.com']);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => 'returning@example.com',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertSessionHasNoErrors();

        return true;
    });

    $reactivated = User::where('email', 'returning@example.com')->sole();
    expect($reactivated->trashed())->toBeFalse()
        ->and($reactivated->name)->toBe('Returning Student')
        ->and(Hash::check('brand-new-password', $reactivated->password))->toBeTrue()
        ->and($reactivated->roles()->count())->toBe(0);

    $revoked = AuditLog::query()
        ->where('action', AuditAction::RoleRevoked->value)
        ->where('subject_type', $reactivated->getMorphClass())
        ->where('subject_id', $reactivated->id)
        ->get();
    expect($revoked)->toHaveCount(2)
        ->and($revoked->every(fn (AuditLog $log): bool => ($log->context['reactivated'] ?? null) === true))->toBeTrue()
        ->and($revoked->pluck('changes.role')->sort()->values()->all())->toBe([
            RoleName::Applicant->value,
            RoleName::Student->value,
        ]);

    $restored = AuditLog::query()
        ->where('action', AuditAction::Restored->value)
        ->where('subject_type', $reactivated->getMorphClass())
        ->where('subject_id', $reactivated->id)
        ->sole();
    expect($restored->context['reactivated'] ?? null)->toBeTrue();
});

test('soft-deleted staff accounts cannot request a reset link', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'former-sao@example.com']);
    $user->assignRole(RoleName::Sao);
    $user->delete();

    $this->post(route('password.email'), ['email' => 'former-sao@example.com']);

    Notification::assertNothingSent();
    expect(User::withTrashed()->where('email', 'former-sao@example.com')->sole()->trashed())->toBeTrue();
});

test('soft-deleted staff accounts cannot reset even with a forged token', function () {
    $user = User::factory()->create(['email' => 'former-admin@example.com']);
    $user->assignRole(RoleName::Admin);
    $user->delete();

    $token = Password::broker()->getRepository()->create($user);

    $response = $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'former-admin@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertSessionHasErrors('email');

    $fresh = User::withTrashed()->where('email', 'former-admin@example.com')->sole();
    expect($fresh->trashed())->toBeTrue()
        ->and($fresh->roles()->count())->toBe(1);
});

test('a reactivated account can authenticate with the new password', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'comeback@example.com']);
    $user->delete();

    $this->post(route('password.email'), ['email' => 'comeback@example.com']);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => 'comeback@example.com',
            'password' => 'back-in-action-1',
            'password_confirmation' => 'back-in-action-1',
        ]);

        return true;
    });

    $this->post(route('login.store'), [
        'email' => 'comeback@example.com',
        'password' => 'back-in-action-1',
    ]);

    $this->assertAuthenticated();
});
