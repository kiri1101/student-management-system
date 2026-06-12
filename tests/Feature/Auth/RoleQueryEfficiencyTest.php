<?php

use App\Enums\RoleName;
use App\Models\StudentProfile;
use Illuminate\Support\Facades\DB;

it('answers all role checks from a single roles query per request', function () {
    $user = userWithRole(RoleName::Student);
    StudentProfile::factory()->for($user)->create();

    $this->actingAs($user);

    DB::enableQueryLog();

    $this->get(route('student.dashboard'))->assertOk();

    // The Inertia share eager-loads roles once; the role middleware, gates,
    // and controller must all reuse that relation (AUDIT.md AUD-018).
    $roleQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], 'role_user'))
        ->values();

    expect($roleQueries)->toHaveCount(1);
});

it('reuses a loaded roles relation for hasRole and refreshes it on assignment', function () {
    $user = userWithRole(RoleName::Applicant);
    $user->load('roles');

    DB::enableQueryLog();

    expect($user->hasRole(RoleName::Applicant))->toBeTrue()
        ->and($user->hasRole(RoleName::Admin))->toBeFalse()
        ->and(DB::getQueryLog())->toBeEmpty();

    // Mutating roles must invalidate the cached relation so checks never
    // answer from stale data.
    $user->assignRole(RoleName::Student);

    expect($user->hasRole(RoleName::Student))->toBeTrue();

    $user->removeRole(RoleName::Student);

    expect($user->hasRole(RoleName::Student))->toBeFalse();
});
