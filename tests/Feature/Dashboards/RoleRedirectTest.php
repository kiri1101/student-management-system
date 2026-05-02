<?php

use App\Enums\RoleName;
use App\Models\User;
use App\Services\RoleDashboardResolver;

test('a roleless authenticated user is redirected to the applicant dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('applicant.dashboard', absolute: false));
});

test('each role is redirected to its own dashboard from /dashboard', function (RoleName $role, string $expectedPath) {
    $user = userWithRole($role);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect($expectedPath);
})->with(function () {
    foreach (RoleDashboardResolver::PRIORITY as [$role, $path]) {
        yield $role->value => [$role, $path];
    }
});

test('a multi-role user lands on the highest-priority dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Student);
    $user->assignRole(RoleName::Sao);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('sao.dashboard', absolute: false));
});

test('the role middleware blocks users without the required role', function () {
    $applicant = userWithRole(RoleName::Applicant);

    $response = $this->actingAs($applicant)->get(route('admin.dashboard'));

    $response->assertForbidden();
});
