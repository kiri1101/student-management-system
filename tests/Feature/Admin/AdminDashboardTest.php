<?php

use App\Enums\ApplicationStatus;
use App\Enums\RoleName;
use App\Models\Application;
use App\Models\StudentProfile;
use App\Models\User;

it('renders the admin dashboard with role + status totals', function () {
    $admin = userWithRole(RoleName::Admin);
    userWithRole(RoleName::Sao);
    userWithRole(RoleName::Student);
    userWithRole(RoleName::Student);

    Application::factory()->submitted()->count(3)->create();
    Application::factory()->state(['status' => ApplicationStatus::Admitted])->create();

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboards/Admin')
            ->where('totals.applications', 4)
            ->has('usersByRole', 6)
            ->has('applicationsByStatus', count(ApplicationStatus::cases())));

    $usersByRole = collect($response->viewData('page')['props']['usersByRole'])
        ->keyBy('role')
        ->all();

    expect($usersByRole['admin']['count'])->toBe(1)
        ->and($usersByRole['sao']['count'])->toBe(1)
        ->and($usersByRole['student']['count'])->toBe(2)
        ->and($usersByRole['lecturer']['count'])->toBe(0);

    $applicationsByStatus = collect($response->viewData('page')['props']['applicationsByStatus'])
        ->keyBy('status')
        ->all();

    expect($applicationsByStatus['submitted']['count'])->toBe(3)
        ->and($applicationsByStatus['admitted']['count'])->toBe(1)
        ->and($applicationsByStatus['rejected']['count'])->toBe(0);
});

it('lists the most recent admissions limited to five entries', function () {
    $admin = userWithRole(RoleName::Admin);

    StudentProfile::factory()->count(7)->create();

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->has('recentAdmissions', 5));
});

it('exposes a count for every RoleName and ApplicationStatus case', function () {
    $admin = userWithRole(RoleName::Admin);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk();
    $props = $response->viewData('page')['props'];

    expect(collect($props['usersByRole'])->pluck('role')->all())
        ->toEqualCanonicalizing(array_column(RoleName::cases(), 'value'));

    expect(collect($props['applicationsByStatus'])->pluck('status')->all())
        ->toEqualCanonicalizing(array_column(ApplicationStatus::cases(), 'value'));
});

it('reports zero student profiles when none exist', function () {
    $admin = userWithRole(RoleName::Admin);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('totals.student_profiles', 0)
            ->has('recentAdmissions', 0));
});

it('excludes soft-deleted users from totals.users', function () {
    $admin = userWithRole(RoleName::Admin);

    $trashed = User::factory()->create();
    $trashed->delete();

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->where('totals.users', 1));
});
