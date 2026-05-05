<?php

use App\Enums\DegreeProgram;
use App\Models\ProgramOffering;
use App\Models\User;
use Database\Seeders\DemoReferencesSeeder;
use Database\Seeders\DocumentTypesSeeder;

beforeEach(function (): void {
    $this->seed(DocumentTypesSeeder::class);
    $this->seed(DemoReferencesSeeder::class);
});

it('returns offerings filtered by degree program', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson(route('api.v1.program-offerings.index', [
        'degree_program' => DegreeProgram::Bachelors->value,
    ]));

    $response->assertOk();
    $payload = $response->json();
    expect($payload)->toHaveCount(1);
    expect($payload[0]['degree_program'])->toBe(DegreeProgram::Bachelors->value);
    expect($payload[0]['department']['code'])->toBe('CS');
});

it('returns all offerings when no filter is supplied', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson(route('api.v1.program-offerings.index'));

    $response->assertOk();
    expect($response->json())->toHaveCount(3);
});

it('returns level requirements for a given offering and level', function () {
    $user = User::factory()->create();
    $offering = ProgramOffering::query()
        ->where('degree_program', DegreeProgram::Bachelors->value)
        ->sole();

    $response = $this->actingAs($user)->getJson(route('api.v1.level-requirements.index', [
        'offering' => $offering->id,
        'level' => 1,
    ]));

    $response->assertOk();
    $payload = $response->json();
    expect($payload)->toHaveCount(1);
    expect($payload[0]['document_type']['code'])->toBe('GCE_AL');
});

it('returns an empty list when no rules match the level', function () {
    $user = User::factory()->create();
    $offering = ProgramOffering::query()
        ->where('degree_program', DegreeProgram::Bachelors->value)
        ->sole();

    $response = $this->actingAs($user)->getJson(route('api.v1.level-requirements.index', [
        'offering' => $offering->id,
        'level' => 2,
    ]));

    $response->assertOk();
    expect($response->json())->toBe([]);
});

it('rejects level requirement queries without an offering', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson(route('api.v1.level-requirements.index', [
        'level' => 1,
    ]));

    $response->assertStatus(422);
});

it('blocks guests from the cascading endpoints', function () {
    $offeringResponse = $this->getJson(route('api.v1.program-offerings.index'));
    $levelResponse = $this->getJson(route('api.v1.level-requirements.index', [
        'offering' => 1,
        'level' => 1,
    ]));

    expect($offeringResponse->status())->toBe(401);
    expect($levelResponse->status())->toBe(401);
});
