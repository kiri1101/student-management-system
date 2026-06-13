<?php

use App\Enums\RoleName;
use App\Models\AccountantProfile;
use App\Models\Department;
use App\Models\LecturerProfile;
use App\Models\SaoProfile;
use App\Models\User;
use Database\Seeders\RolesSeeder;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->withoutVite();
});

it('shows a lecturer their own lecturer profile fields', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Lecturer);
    $department = Department::firstOrCreate(['code' => 'CS'], ['name' => 'Computer Science']);
    LecturerProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'specialization' => 'Networks',
    ]);

    $this->actingAs($user)
        ->get(route('staff-profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/StaffProfile')
            ->where('role', 'lecturer')
            ->where('profile.specialization', 'Networks')
            ->where('profile.department_id', $department->id)
        );
});

it('lets a lecturer update their own lecturer fields', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Lecturer);
    $original = Department::firstOrCreate(['code' => 'CS'], ['name' => 'Computer Science']);
    LecturerProfile::factory()->create(['user_id' => $user->id, 'department_id' => $original->id]);
    $target = Department::firstOrCreate(['code' => 'MA'], ['name' => 'Mathematics']);

    $this->actingAs($user)
        ->patch(route('staff-profile.update'), [
            'department_id' => $target->id,
            'specialization' => 'Algebra',
            'hired_at' => '2020-01-15',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('staff-profile.edit'));

    $profile = $user->lecturerProfile()->first();
    expect($profile->department_id)->toBe($target->id);
    expect($profile->specialization)->toBe('Algebra');
    expect($profile->hired_at->toDateString())->toBe('2020-01-15');
});

it('creates the lecturer profile row if it does not exist yet', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Lecturer);
    // A lecturer profile requires a department (NOT NULL FK), so a valid
    // self-service creation must include one.
    $department = Department::firstOrCreate(['code' => 'CS'], ['name' => 'Computer Science']);

    $this->actingAs($user)
        ->patch(route('staff-profile.update'), [
            'department_id' => $department->id,
            'specialization' => 'Robotics',
        ])
        ->assertSessionHasNoErrors();

    $profile = $user->lecturerProfile()->first();
    expect($profile?->specialization)->toBe('Robotics');
    expect($profile?->department_id)->toBe($department->id);
});

it('lets an accountant update their own accountant fields', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Accountant);
    AccountantProfile::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patch(route('staff-profile.update'), [
            'bank_desk' => 'SCB',
            'cashier_window' => 'B7',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('staff-profile.edit'));

    $profile = $user->accountantProfile()->first();
    expect($profile->bank_desk)->toBe('SCB');
    expect($profile->cashier_window)->toBe('B7');
});

it('lets an SAO update their own scope', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Sao);
    SaoProfile::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patch(route('staff-profile.update'), ['scope' => 'Admissions'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('staff-profile.edit'));

    expect($user->saoProfile()->first()->scope)->toBe('Admissions');
});

it('rejects invalid input', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Lecturer);
    LecturerProfile::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->from(route('staff-profile.edit'))
        ->patch(route('staff-profile.update'), [
            'department_id' => 999999,
            'hired_at' => now()->addYear()->toDateString(),
        ])
        ->assertSessionHasErrors(['department_id', 'hired_at']);
});

it('does not let a lecturer write fields outside their role', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Lecturer);
    LecturerProfile::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patch(route('staff-profile.update'), [
            'specialization' => 'Physics',
            // Accountant/SAO keys must be ignored, not persisted anywhere.
            'bank_desk' => 'UBA',
            'scope' => 'Records',
        ])
        ->assertSessionHasNoErrors();

    expect($user->accountantProfile()->withTrashed()->exists())->toBeFalse();
    expect($user->saoProfile()->withTrashed()->exists())->toBeFalse();
    expect($user->lecturerProfile()->first()->specialization)->toBe('Physics');
});

it('never changes the user role', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Sao);
    SaoProfile::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patch(route('staff-profile.update'), [
            'scope' => 'Records',
            'role' => 'admin',
        ])
        ->assertSessionHasNoErrors();

    $fresh = $user->fresh();
    expect($fresh->hasRole(RoleName::Sao))->toBeTrue();
    expect($fresh->hasRole(RoleName::Admin))->toBeFalse();
});

it('forbids an applicant from viewing or updating the staff profile', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Applicant);

    $this->actingAs($user)->get(route('staff-profile.edit'))->assertForbidden();
    $this->actingAs($user)->patch(route('staff-profile.update'), ['scope' => 'x'])->assertForbidden();
});

it('forbids a student from the staff profile', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Student);

    $this->actingAs($user)->get(route('staff-profile.edit'))->assertForbidden();
});

it('forbids a plain admin with no staff profile', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin);

    $this->actingAs($user)->get(route('staff-profile.edit'))->assertForbidden();
    $this->actingAs($user)->patch(route('staff-profile.update'), [])->assertForbidden();
});

it('requires authentication', function () {
    $this->get(route('staff-profile.edit'))->assertRedirect(route('login'));
});
