<?php

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesSeeder;

// This file lives at the top level of tests/Feature, which Pest.php's role
// auto-seeding does not cover (only specific subdirectories), so seed here.
beforeEach(fn () => $this->seed(RolesSeeder::class));

it('shares an empty roles array for a roleless authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('applicant.dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.roles', []));
});

it('shares the role name in auth.roles so the sidebar can build role-aware nav', function (RoleName $role, string $route) {
    $user = userWithRole($role);

    $this->actingAs($user)
        ->get(route($route))
        ->assertInertia(fn ($page) => $page->where('auth.roles', [$role->value]));
})->with([
    'admin' => [RoleName::Admin, 'admin.dashboard'],
    'sao' => [RoleName::Sao, 'sao.dashboard'],
    'lecturer' => [RoleName::Lecturer, 'lecturer.dashboard'],
    'accountant' => [RoleName::Accountant, 'accountant.dashboard'],
    'student' => [RoleName::Student, 'student.dashboard'],
    'applicant' => [RoleName::Applicant, 'applicant.dashboard'],
]);

it('shares every held role for a multi-role user', function () {
    $user = userWithRole(RoleName::Sao);
    $user->assignRole(RoleName::Admin);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.roles', fn ($roles): bool => collect($roles)->contains(RoleName::Sao->value)
                && collect($roles)->contains(RoleName::Admin->value)));
});

it('exposes the routed nav destinations each role links to', function (RoleName $role, array $routes) {
    $user = userWithRole($role);

    foreach ($routes as $route) {
        $this->actingAs($user)->get(route($route))->assertOk();
    }
})->with([
    'admin nav targets' => [
        RoleName::Admin,
        ['admin.users.index', 'admin.references.index', 'admin.audit-logs.index', 'sao.applications.index'],
    ],
    'sao nav targets' => [
        RoleName::Sao,
        ['sao.applications.index'],
    ],
    'applicant nav targets' => [
        RoleName::Applicant,
        ['application.create'],
    ],
]);
