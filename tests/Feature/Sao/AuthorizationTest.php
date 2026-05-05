<?php

use App\Enums\RoleName;
use App\Models\Application;
use App\Models\User;

dataset('non_sao_or_admin_roles', function () {
    foreach (RoleName::cases() as $role) {
        if ($role === RoleName::Sao || $role === RoleName::Admin) {
            continue;
        }

        yield $role->value => [$role];
    }
});

/**
 * Endpoints that don't take a route-bound model. Safe to assert against
 * before any rows are seeded.
 *
 * @return iterable<string, array{0: string, 1: string}>
 */
dataset('unbound_sao_endpoints', function () {
    yield 'GET dashboard' => ['get', '/sao/dashboard'];
    yield 'GET applications index' => ['get', '/sao/applications'];
});

/**
 * Seed one application and return verb/url pairs for the bound endpoints.
 * SubstituteBindings runs before role middleware (it is part of the `web`
 * group), so these need real ids to reach the 403 path.
 *
 * @return array<int, array{verb: string, url: string}>
 */
function boundSaoEndpoints(): array
{
    $application = Application::factory()->submitted()->create();

    return [
        ['verb' => 'get', 'url' => "/sao/applications/{$application->id}"],
        ['verb' => 'post', 'url' => "/sao/applications/{$application->id}/triage"],
        ['verb' => 'post', 'url' => "/sao/applications/{$application->id}/decide"],
        ['verb' => 'post', 'url' => "/sao/applications/{$application->id}/restore-prior"],
    ];
}

it('redirects guests to login on unbound SAO endpoints', function (string $verb, string $url) {
    $response = $this->{$verb}($url);

    $response->assertRedirect(route('login', absolute: false));
})->with('unbound_sao_endpoints');

it('forbids non-SAO/admin roles on unbound SAO endpoints', function (RoleName $role, string $verb, string $url) {
    $user = userWithRole($role);

    $response = $this->actingAs($user)->{$verb}($url);

    $response->assertForbidden();
})
    ->with('non_sao_or_admin_roles')
    ->with('unbound_sao_endpoints');

it('forbids roleless verified users on unbound SAO endpoints', function (string $verb, string $url) {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->{$verb}($url);

    $response->assertForbidden();
})->with('unbound_sao_endpoints');

it('redirects guests to login on bound SAO endpoints', function () {
    foreach (boundSaoEndpoints() as ['verb' => $verb, 'url' => $url]) {
        $this->{$verb}($url)->assertRedirect(route('login', absolute: false));
    }
});

it('forbids non-SAO/admin roles on bound SAO endpoints', function (RoleName $role) {
    $user = userWithRole($role);

    foreach (boundSaoEndpoints() as ['verb' => $verb, 'url' => $url]) {
        $this->actingAs($user)->{$verb}($url)->assertForbidden();
    }
})->with('non_sao_or_admin_roles');

it('allows admin to reach the SAO dashboard alongside SAOs', function () {
    $admin = userWithRole(RoleName::Admin);

    $this->actingAs($admin)->get('/sao/dashboard')->assertOk();
});

it('allows SAO to reach the SAO dashboard', function () {
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)->get('/sao/dashboard')->assertOk();
});
