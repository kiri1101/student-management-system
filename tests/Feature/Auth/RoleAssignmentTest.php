<?php

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

it('assigns and removes a role', function () {
    $user = User::factory()->create();

    $user->assignRole(RoleName::Student);
    expect($user->hasRole(RoleName::Student))->toBeTrue();

    $user->removeRole(RoleName::Student);
    expect($user->hasRole(RoleName::Student))->toBeFalse();
});

it('assignRole is idempotent', function () {
    $user = User::factory()->create();

    $user->assignRole(RoleName::Lecturer);
    $user->assignRole(RoleName::Lecturer);

    expect($user->roles()->count())->toBe(1);
});

it('hasAnyRole returns true when any role matches', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Lecturer);
    $user->assignRole(RoleName::Sao);

    expect($user->hasAnyRole([RoleName::Sao, RoleName::Admin]))->toBeTrue();
});

it('hasAnyRole returns false when no role matches', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Applicant);

    expect($user->hasAnyRole([RoleName::Admin]))->toBeFalse();
});

it('hasAnyRole returns false for an empty role list', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Admin);

    expect($user->hasAnyRole([]))->toBeFalse();
});

it('seeds all six roles', function () {
    expect(Role::count())->toBe(count(RoleName::cases()));

    foreach (RoleName::cases() as $case) {
        expect(Role::query()->where('name', $case->value)->exists())->toBeTrue();
    }
});
