<?php

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('registering with a soft-deleted email is refused and leaves the row untouched', function () {
    $original = User::factory()->create([
        'email' => 'returning@example.com',
        'name' => 'Old Name',
        'password' => Hash::make('old-password'),
    ]);
    $original->assignRole(RoleName::Student);
    $original->delete();

    $response = $this->post(route('register.store'), [
        'name' => 'Anonymous Claimer',
        'email' => 'returning@example.com',
        'password' => 'hijack-password',
        'password_confirmation' => 'hijack-password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();

    $row = User::withTrashed()->where('email', 'returning@example.com')->sole();
    expect($row->trashed())->toBeTrue()
        ->and($row->name)->toBe('Old Name')
        ->and(Hash::check('old-password', $row->password))->toBeTrue()
        ->and($row->roles()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditAction::Restored->value)->exists())->toBeFalse();
});

test('registration rejects active and soft-deleted emails identically', function () {
    User::factory()->create(['email' => 'active@example.com']);
    $trashed = User::factory()->create(['email' => 'gone@example.com']);
    $trashed->delete();

    $payload = fn (string $email): array => [
        'name' => 'Prober',
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ];

    $this->post(route('register.store'), $payload('active@example.com'))
        ->assertSessionHasErrors('email');
    $activeMessage = session('errors')->first('email');

    $this->post(route('register.store'), $payload('gone@example.com'))
        ->assertSessionHasErrors('email');
    $trashedMessage = session('errors')->first('email');

    expect($trashedMessage)->toBe($activeMessage);
    $this->assertGuest();
});

test('a duplicate insert that races past validation still returns the standard 422', function () {
    User::creating(function (User $user): void {
        // Simulate a concurrent request winning the race between the unique
        // validation and the INSERT (AUDIT.md AUD-017).
        User::withoutEvents(fn (): User => User::factory()->create(['email' => $user->email]));
    });

    $response = $this->post(route('register.store'), [
        'name' => 'Racer',
        'email' => 'race@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
    expect(User::where('email', 'race@example.com')->count())->toBe(1);
});

test('re-registering with an active existing email returns 422', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->post(route('register.store'), [
        'name' => 'Imposter',
        'email' => 'taken@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});
