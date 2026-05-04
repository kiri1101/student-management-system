<?php

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesSeeder;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

it('allows admins through the view-audit-log gate', function () {
    expect(userWithRole(RoleName::Admin)->can('view-audit-log'))->toBeTrue();
});

it('denies every other role', function () {
    foreach ([RoleName::Sao, RoleName::Accountant, RoleName::Lecturer, RoleName::Student, RoleName::Applicant] as $role) {
        expect(userWithRole($role)->can('view-audit-log'))->toBeFalse();
    }
});

it('denies a roleless user', function () {
    expect(User::factory()->create()->can('view-audit-log'))->toBeFalse();
});
