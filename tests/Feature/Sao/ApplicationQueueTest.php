<?php

use App\Enums\ApplicationStatus;
use App\Enums\RoleName;
use App\Models\Application;

it('lists applications filtered to actionable statuses by default', function () {
    Application::factory()->submitted()->create();
    Application::factory()->state(['status' => ApplicationStatus::UnderReview])->create();
    Application::factory()->state(['status' => ApplicationStatus::DocumentsRequested])->create();
    Application::factory()->state([
        'status' => ApplicationStatus::Admitted,
        'submitted_at' => now()->subDay(),
        'decided_at' => now(),
    ])->create();

    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->get(route('sao.applications.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sao/applications/Index')
            ->where('applications.total', 3)
            ->where('filters.status', ['submitted', 'under_review', 'documents_requested']));
});

it('honors a status filter from the query string', function () {
    Application::factory()->submitted()->create();
    Application::factory()->state([
        'status' => ApplicationStatus::Admitted,
        'submitted_at' => now()->subDay(),
        'decided_at' => now(),
    ])->create();

    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->get(route('sao.applications.index', ['status' => ['admitted']]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('applications.total', 1)
            ->where('applications.data.0.status', 'admitted')
            ->where('filters.status', ['admitted']));
});

it('rejects an unknown status filter with a validation error', function () {
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->get(route('sao.applications.index', ['status' => ['nope']]));

    $response->assertSessionHasErrors('status.0');
});

it('rejects a non-whitelisted sort field', function () {
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->get(route('sao.applications.index', ['sort_field' => 'first_name']));

    $response->assertSessionHasErrors('sort_field');
});

it('paginates with the requested rows-per-page', function () {
    Application::factory()->submitted()->count(7)->create();
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->get(route('sao.applications.index', ['rows' => 5]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('applications.per_page', 5)
            ->where('applications.total', 7)
            ->where('applications.last_page', 2));
});

it('ships the full list of status options for the filter UI', function () {
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->get(route('sao.applications.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('statusOptions', count(ApplicationStatus::cases())));
});
