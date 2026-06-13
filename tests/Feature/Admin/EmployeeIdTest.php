<?php

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->admin = userWithRole(RoleName::Admin);
});

it('creates a staff user with an employee_id they can immediately log in with', function () {
    $this->actingAs($this->admin)->post('/admin/users', [
        'name' => 'Badge Holder',
        'email' => 'badge@example.com',
        'employee_id' => '  EMP-0042 ',
        'role' => RoleName::Sao->value,
        'profile' => ['scope' => 'Admissions'],
    ])->assertRedirect(route('admin.users.index'));

    $user = User::query()->where('email', 'badge@example.com')->firstOrFail();
    expect($user->employee_id)->toBe('emp-0042');

    // The invite link is the real first-login path; shortcut it so the test
    // can exercise the employee_id login leg end-to-end.
    $user->forceFill(['password' => Hash::make('secret-password-123')])->save();

    $this->post(route('logout'));

    $this->post(route('login.store'), [
        'email' => 'EMP-0042',
        'password' => 'secret-password-123',
    ]);

    $this->assertAuthenticatedAs($user->fresh());
});

it('rejects a duplicate employee_id with a 422', function () {
    User::factory()->staff('emp-0042')->create();

    $response = $this->actingAs($this->admin)->post('/admin/users', [
        'name' => 'Copycat',
        'email' => 'copycat@example.com',
        'employee_id' => 'EMP-0042',
        'role' => RoleName::Sao->value,
        'profile' => ['scope' => 'Admissions'],
    ]);

    $response->assertSessionHasErrors('employee_id');
    expect(User::query()->where('email', 'copycat@example.com')->exists())->toBeFalse();
});

it('rejects employee ids that could shadow the email or matricule namespaces', function (string $bad) {
    $response = $this->actingAs($this->admin)->post('/admin/users', [
        'name' => 'Bad Badge',
        'email' => 'bad.badge@example.com',
        'employee_id' => $bad,
        'role' => RoleName::Sao->value,
        'profile' => ['scope' => 'Admissions'],
    ]);

    $response->assertSessionHasErrors('employee_id');
})->with([
    'contains @' => 'looks@like.email',
    'matricule prefix' => 'stm-2026-0001',
]);

it('updates and clears the employee_id from the edit flow', function () {
    $user = User::factory()->staff('emp-1111')->create();
    $user->assignRole(RoleName::Sao);

    $this->actingAs($this->admin)->patch(route('admin.users.update', $user), [
        'name' => $user->name,
        'employee_id' => 'EMP-2222',
    ])->assertSessionDoesntHaveErrors();

    expect($user->fresh()->employee_id)->toBe('emp-2222');

    $this->actingAs($this->admin)->patch(route('admin.users.update', $user), [
        'name' => $user->name,
        'employee_id' => null,
    ])->assertSessionDoesntHaveErrors();

    expect($user->fresh()->employee_id)->toBeNull();
});

it('keeps the same employee_id valid when re-saving the user', function () {
    $user = User::factory()->staff('emp-3333')->create();
    $user->assignRole(RoleName::Sao);

    $this->actingAs($this->admin)->patch(route('admin.users.update', $user), [
        'name' => 'Renamed',
        'employee_id' => 'emp-3333',
    ])->assertSessionDoesntHaveErrors();

    expect($user->fresh()->name)->toBe('Renamed')
        ->and($user->fresh()->employee_id)->toBe('emp-3333');
});
