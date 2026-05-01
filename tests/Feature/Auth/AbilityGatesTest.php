<?php

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

/**
 * Authoritative gate map mirroring AppServiceProvider::ABILITIES.
 * If you add or change a gate there, mirror it here.
 *
 * @return array<string, array<int, RoleName>>
 */
function abilityRoleMap(): array
{
    return [
        'process-admission' => [RoleName::Sao, RoleName::Admin],
        'decide-application' => [RoleName::Sao, RoleName::Admin],
        'validate-payment' => [RoleName::Accountant, RoleName::Admin],
        'publish-results' => [RoleName::Sao, RoleName::Admin],
        'approve-course-plan' => [RoleName::Sao, RoleName::Admin],
        'mark-attendance' => [RoleName::Lecturer, RoleName::Admin],
        'view-audit-log' => [RoleName::Admin],
    ];
}

function userWithRole(RoleName $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

dataset('ability_role_pairs', function () {
    foreach (abilityRoleMap() as $ability => $allowed) {
        foreach (RoleName::cases() as $role) {
            yield "{$ability} / {$role->value}" => [$ability, $role, in_array($role, $allowed, true)];
        }
    }
});

it('resolves each gate per role correctly', function (string $ability, RoleName $role, bool $expected) {
    $user = userWithRole($role);

    expect(Gate::forUser($user)->allows($ability))->toBe($expected);
})->with('ability_role_pairs');

it('grants every defined ability to admin', function () {
    $admin = userWithRole(RoleName::Admin);

    foreach (array_keys(abilityRoleMap()) as $ability) {
        expect(Gate::forUser($admin)->allows($ability))->toBeTrue();
    }
});

it('denies every defined ability to a roleless user', function () {
    $user = User::factory()->create();

    foreach (array_keys(abilityRoleMap()) as $ability) {
        expect(Gate::forUser($user)->allows($ability))->toBeFalse();
    }
});
