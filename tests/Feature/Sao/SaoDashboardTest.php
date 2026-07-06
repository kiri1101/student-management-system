<?php

use App\Enums\ApplicationStatus;
use App\Enums\RoleName;
use App\Models\Application;

it('renders the SAO dashboard with status counts', function () {
    Application::factory()->submitted()->count(2)->create();
    Application::factory()->state(['status' => ApplicationStatus::UnderReview])->create();
    Application::factory()->state([
        'status' => ApplicationStatus::Admitted,
        'submitted_at' => now()->subDay(),
        'decided_at' => now(),
    ])->create();

    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->get(route('sao.dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboards/Sao')
            ->where('statusCounts.submitted', 2)
            ->where('statusCounts.under_review', 1)
            ->where('statusCounts.admitted', 1)
            ->where('statusCounts.rejected', 0));
});

it('exposes a count for every ApplicationStatus case', function () {
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->get(route('sao.dashboard'));

    $response->assertOk();
    $statusCounts = $response->viewData('page')['props']['statusCounts'];

    foreach (ApplicationStatus::cases() as $status) {
        expect($statusCounts)->toHaveKey($status->value);
    }
});
