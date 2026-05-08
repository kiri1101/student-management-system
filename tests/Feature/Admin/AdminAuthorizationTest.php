<?php

use App\Enums\RoleName;
use App\Models\User;

dataset('non_admin_roles', function () {
    foreach (RoleName::cases() as $role) {
        if ($role === RoleName::Admin) {
            continue;
        }

        yield $role->value => [$role];
    }
});

dataset('admin_endpoints', function () {
    yield 'GET admin/dashboard' => ['get', '/admin/dashboard'];
    yield 'GET admin/audit-logs' => ['get', '/admin/audit-logs'];
});

it('redirects guests to login on admin dashboard + audit endpoints', function (string $verb, string $url) {
    $response = $this->{$verb}($url);

    $response->assertRedirect(route('login', absolute: false));
})->with('admin_endpoints');

it('forbids non-admin roles on admin dashboard + audit endpoints', function (RoleName $role, string $verb, string $url) {
    $user = userWithRole($role);

    $response = $this->actingAs($user)->{$verb}($url);

    $response->assertForbidden();
})
    ->with('non_admin_roles')
    ->with('admin_endpoints');

it('forbids roleless verified users on admin dashboard + audit endpoints', function (string $verb, string $url) {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->{$verb}($url);

    $response->assertForbidden();
})->with('admin_endpoints');
