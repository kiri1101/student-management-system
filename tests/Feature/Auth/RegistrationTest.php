<?php

use App\Enums\RoleName;
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

test('re-registering with a soft-deleted email reactivates the same user row', function () {
    $original = User::factory()->create(['email' => 'returning@example.com']);
    $originalId = $original->id;
    $original->delete();

    $this->post(route('register.store'), [
        'name' => 'Returning Student',
        'email' => 'returning@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertRedirect();

    $reactivated = User::withTrashed()->where('email', 'returning@example.com')->sole();

    expect($reactivated->id)->toBe($originalId)
        ->and($reactivated->trashed())->toBeFalse();
});

test('reactivation overwrites name and password and clears email verification', function () {
    $original = User::factory()->create([
        'email' => 'returning@example.com',
        'name' => 'Old Name',
        'password' => Hash::make('old-password'),
    ]);
    $original->delete();

    $this->post(route('register.store'), [
        'name' => 'New Name',
        'email' => 'returning@example.com',
        'password' => 'fresh-password',
        'password_confirmation' => 'fresh-password',
    ]);

    $user = User::where('email', 'returning@example.com')->sole();

    expect($user->name)->toBe('New Name')
        ->and(Hash::check('fresh-password', $user->password))->toBeTrue()
        ->and(Hash::check('old-password', $user->password))->toBeFalse()
        ->and($user->email_verified_at)->toBeNull();
});

test('reactivation detaches all prior role assignments', function () {
    $original = User::factory()->create(['email' => 'returning@example.com']);
    $original->assignRole(RoleName::Student);
    $original->assignRole(RoleName::Applicant);
    expect($original->roles()->count())->toBe(2);

    $original->delete();

    $this->post(route('register.store'), [
        'name' => 'Returning Student',
        'email' => 'returning@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $reactivated = User::where('email', 'returning@example.com')->sole();

    expect($reactivated->roles()->count())->toBe(0);
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
