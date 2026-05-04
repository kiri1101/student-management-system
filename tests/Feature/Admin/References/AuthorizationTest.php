<?php

use App\Enums\DegreeProgram;
use App\Enums\RoleName;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\LevelCredentialRequirement;
use App\Models\ProgramOffering;
use App\Models\User;

dataset('non_admin_roles', function () {
    foreach (RoleName::cases() as $role) {
        if ($role === RoleName::Admin) {
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
dataset('unbound_endpoints', function () {
    yield 'GET hub' => ['get', '/admin/references'];

    foreach (['departments', 'program-offerings', 'document-types', 'level-requirements'] as $resource) {
        yield "GET {$resource} index" => ['get', "/admin/references/{$resource}"];
        yield "POST {$resource} store" => ['post', "/admin/references/{$resource}"];
    }
});

/**
 * Seed one row of each reference type and return verb/url pairs for the
 * bound endpoints. SubstituteBindings runs before role middleware (it is
 * part of the `web` group), so these need real ids to reach the 403 path.
 *
 * @return array<int, array{verb: string, url: string}>
 */
function boundReferenceEndpoints(): array
{
    $department = Department::create(['name' => 'CS', 'code' => 'CS']);
    $offering = ProgramOffering::create([
        'department_id' => $department->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 4,
    ]);
    $documentType = DocumentType::create(['name' => 'GCE A/L', 'code' => 'GCE_AL']);
    $requirement = LevelCredentialRequirement::create([
        'program_offering_id' => $offering->id,
        'level' => 1,
        'document_type_id' => $documentType->id,
        'required' => true,
    ]);

    return [
        ['verb' => 'patch', 'url' => "/admin/references/departments/{$department->id}"],
        ['verb' => 'delete', 'url' => "/admin/references/departments/{$department->id}"],
        ['verb' => 'patch', 'url' => "/admin/references/program-offerings/{$offering->id}"],
        ['verb' => 'delete', 'url' => "/admin/references/program-offerings/{$offering->id}"],
        ['verb' => 'patch', 'url' => "/admin/references/document-types/{$documentType->id}"],
        ['verb' => 'delete', 'url' => "/admin/references/document-types/{$documentType->id}"],
        ['verb' => 'patch', 'url' => "/admin/references/level-requirements/{$requirement->id}"],
        ['verb' => 'delete', 'url' => "/admin/references/level-requirements/{$requirement->id}"],
    ];
}

it('redirects guests to login on unbound reference endpoints', function (string $verb, string $url) {
    $response = $this->{$verb}($url);

    $response->assertRedirect(route('login', absolute: false));
})->with('unbound_endpoints');

it('forbids non-admin roles on unbound reference endpoints', function (RoleName $role, string $verb, string $url) {
    $user = userWithRole($role);

    $response = $this->actingAs($user)->{$verb}($url);

    $response->assertForbidden();
})
    ->with('non_admin_roles')
    ->with('unbound_endpoints');

it('forbids roleless verified users on unbound reference endpoints', function (string $verb, string $url) {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->{$verb}($url);

    $response->assertForbidden();
})->with('unbound_endpoints');

it('redirects guests to login on bound reference endpoints', function () {
    foreach (boundReferenceEndpoints() as ['verb' => $verb, 'url' => $url]) {
        $this->{$verb}($url)->assertRedirect(route('login', absolute: false));
    }
});

it('forbids non-admin roles on bound reference endpoints', function (RoleName $role) {
    $user = userWithRole($role);

    foreach (boundReferenceEndpoints() as ['verb' => $verb, 'url' => $url]) {
        $this->actingAs($user)->{$verb}($url)->assertForbidden();
    }
})->with('non_admin_roles');

it('forbids roleless verified users on bound reference endpoints', function () {
    $user = User::factory()->create();

    foreach (boundReferenceEndpoints() as ['verb' => $verb, 'url' => $url]) {
        $this->actingAs($user)->{$verb}($url)->assertForbidden();
    }
});
