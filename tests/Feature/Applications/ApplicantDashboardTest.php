<?php

use App\Enums\ApplicationStatus;
use App\Enums\DegreeProgram;
use App\Models\Application;
use App\Models\Department;
use App\Models\ProgramOffering;
use App\Models\User;

beforeEach(function (): void {
    $this->department = Department::firstOrCreate(
        ['code' => 'CS'],
        ['name' => 'Computer Science'],
    );

    $this->offering = ProgramOffering::firstOrCreate(
        ['department_id' => $this->department->id, 'degree_program' => DegreeProgram::Bachelors->value],
        ['min_level' => 1, 'max_level' => 4],
    );
});

it('renders the applicant dashboard with the user own applications', function () {
    $user = User::factory()->create();
    Application::factory()->for($user, 'applicant')->for($this->offering, 'programOffering')->submitted()->create([
        'level' => 2,
    ]);

    $response = $this->actingAs($user)->get(route('applicant.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('dashboards/Applicant')
        ->has('applications', 1)
        ->where('applications.0.status', ApplicationStatus::Submitted->value)
        ->where('applications.0.level', 2)
        ->where('applications.0.program_offering.department.code', 'CS'));
});

it('only shows applications belonging to the authenticated user', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Application::factory()->for($alice, 'applicant')->for($this->offering, 'programOffering')->submitted()->create();
    Application::factory()->for($bob, 'applicant')->for($this->offering, 'programOffering')->submitted()->create();

    $response = $this->actingAs($alice)->get(route('applicant.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('dashboards/Applicant')
        ->has('applications', 1));
});

it('blocks guests from the applicant dashboard', function () {
    $response = $this->get(route('applicant.dashboard'));

    $response->assertRedirect(route('login'));
});

it('still renders applications whose offering was soft-deleted', function () {
    $user = User::factory()->create();
    Application::factory()->for($user, 'applicant')->for($this->offering, 'programOffering')->submitted()->create();

    // Bypass the controller guard: simulate an offering trashed in the DB
    // (drifted data) — historical applications must keep rendering (AUD-013).
    $this->offering->delete();

    $response = $this->actingAs($user)->get(route('applicant.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('dashboards/Applicant')
        ->has('applications', 1)
        ->where('applications.0.program_offering.department.code', 'CS'));
});
