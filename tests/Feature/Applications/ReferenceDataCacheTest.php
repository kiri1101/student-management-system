<?php

use App\Enums\DegreeProgram;
use App\Enums\RoleName;
use App\Models\Department;
use App\Models\User;
use App\Services\ReferenceDataCache;
use Database\Seeders\DemoReferencesSeeder;
use Database\Seeders\DocumentTypesSeeder;
use Illuminate\Support\Facades\Cache;

it('serves reference reads from cache until explicitly flushed', function () {
    $cache = app(ReferenceDataCache::class);

    Department::create(['name' => 'Alpha', 'code' => 'ALP']);

    expect($cache->departments())->toHaveCount(1);

    // A second row inserted out of band (no controller → no flush) stays hidden
    // behind the cached copy, proving the first read was actually cached.
    Department::create(['name' => 'Beta', 'code' => 'BET']);
    expect($cache->departments())->toHaveCount(1);

    $cache->flush();

    expect($cache->departments())->toHaveCount(2);
});

it('flushes every owned cache key', function () {
    $this->seed(DocumentTypesSeeder::class);
    $this->seed(DemoReferencesSeeder::class);
    $cache = app(ReferenceDataCache::class);

    $cache->departments();
    $cache->offerings();
    $cache->protectedDocumentTypes();
    $cache->levelRequirements();

    expect(Cache::has('reference.departments'))->toBeTrue()
        ->and(Cache::has('reference.offerings'))->toBeTrue()
        ->and(Cache::has('reference.document_types.protected'))->toBeTrue()
        ->and(Cache::has('reference.level_requirements'))->toBeTrue();

    $cache->flush();

    expect(Cache::has('reference.departments'))->toBeFalse()
        ->and(Cache::has('reference.offerings'))->toBeFalse()
        ->and(Cache::has('reference.document_types.protected'))->toBeFalse()
        ->and(Cache::has('reference.level_requirements'))->toBeFalse();
});

it('invalidates the applicant offerings cache when an admin adds an offering', function () {
    $this->seed(DocumentTypesSeeder::class);
    $this->seed(DemoReferencesSeeder::class);

    $applicant = User::factory()->create();

    // Prime the cache through the applicant-facing cascading endpoint.
    $before = $this->actingAs($applicant)
        ->getJson(route('api.v1.program-offerings.index'))
        ->assertOk()
        ->json();

    // An admin adds a brand-new department + offering through the controller.
    $admin = userWithRole(RoleName::Admin);
    $department = Department::create(['name' => 'Law', 'code' => 'LAW']);

    $this->actingAs($admin)
        ->post(route('admin.references.program-offerings.store'), [
            'department_id' => $department->id,
            'degree_program' => DegreeProgram::Bachelors->value,
            'min_level' => 1,
            'max_level' => 3,
        ])
        ->assertRedirect();

    // The next applicant read reflects the new offering — the store flushed.
    $after = $this->actingAs($applicant)
        ->getJson(route('api.v1.program-offerings.index'))
        ->assertOk()
        ->json();

    expect($after)->toHaveCount(count($before) + 1);
});

it('invalidates the applicant create form when an admin adds a department', function () {
    $this->seed(DocumentTypesSeeder::class);
    $this->seed(DemoReferencesSeeder::class);

    $applicant = User::factory()->create();

    // Prime: the form shows the single seeded department (CS).
    $this->actingAs($applicant)
        ->get(route('application.create'))
        ->assertInertia(fn ($page) => $page->has('departments', 1));

    // An admin adds another department through the controller.
    $admin = userWithRole(RoleName::Admin);
    $this->actingAs($admin)
        ->post(route('admin.references.departments.store'), [
            'name' => 'Law',
            'code' => 'LAW',
            'description' => 'Faculty of Law',
        ])
        ->assertRedirect();

    // The form now shows both — the store flushed the cache.
    $this->actingAs($applicant)
        ->get(route('application.create'))
        ->assertInertia(fn ($page) => $page->has('departments', 2));
});
