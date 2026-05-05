<?php

use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\Application;
use App\Models\AuditLog;

it('moves a Submitted application to UnderReview and writes a StatusChanged audit', function () {
    $application = Application::factory()->submitted()->create();
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->post(route('sao.applications.triage', $application), [
        'status' => 'under_review',
    ]);

    $response->assertRedirect();
    expect($application->fresh()->status)->toBe(ApplicationStatus::UnderReview);

    $log = AuditLog::query()
        ->where('subject_type', $application->getMorphClass())
        ->where('subject_id', $application->id)
        ->where('action', AuditAction::StatusChanged->value)
        ->sole();

    expect($log->changes)->toBe(['before' => 'submitted', 'after' => 'under_review'])
        ->and($log->user_id)->toBe($sao->id);
});

it('requires notes when triaging into DocumentsRequested', function () {
    $application = Application::factory()->submitted()->create();
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->post(route('sao.applications.triage', $application), [
        'status' => 'documents_requested',
    ]);

    $response->assertSessionHasErrors('notes');
    expect($application->fresh()->status)->toBe(ApplicationStatus::Submitted);
});

it('persists notes alongside the status change', function () {
    $application = Application::factory()->submitted()->create();
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)->post(route('sao.applications.triage', $application), [
        'status' => 'documents_requested',
        'notes' => 'Please re-upload the GCE A/L scan.',
    ])->assertRedirect();

    $fresh = $application->fresh();
    expect($fresh->status)->toBe(ApplicationStatus::DocumentsRequested)
        ->and($fresh->decision_notes)->toBe('Please re-upload the GCE A/L scan.');
});

it('refuses to triage a terminal application', function () {
    $application = Application::factory()->state([
        'status' => ApplicationStatus::Admitted,
        'submitted_at' => now()->subDay(),
        'decided_at' => now(),
    ])->create();
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->post(route('sao.applications.triage', $application), [
        'status' => 'under_review',
    ]);

    $response->assertSessionHasErrors('status');
    expect($application->fresh()->status)->toBe(ApplicationStatus::Admitted);
});

it('rejects a terminal status submitted to triage', function () {
    $application = Application::factory()->submitted()->create();
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->post(route('sao.applications.triage', $application), [
        'status' => 'admitted',
    ]);

    $response->assertSessionHasErrors('status');
    expect($application->fresh()->status)->toBe(ApplicationStatus::Submitted);
});
