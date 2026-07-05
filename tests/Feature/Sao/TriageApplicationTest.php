<?php

use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Mail\ApplicationDocumentsRequestedMail;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Mail;

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

it('refuses DocumentsRequested when no document is rejected', function () {
    $application = Application::factory()->submitted()->create();
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->post(route('sao.applications.triage', $application), [
        'status' => 'documents_requested',
    ]);

    $response->assertSessionHasErrors('status');
    expect($application->fresh()->status)->toBe(ApplicationStatus::Submitted);
});

it('persists notes alongside the status change', function () {
    $application = Application::factory()->submitted()->create();
    ApplicationDocument::factory()->rejected()->create(['application_id' => $application->id]);
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)->post(route('sao.applications.triage', $application), [
        'status' => 'documents_requested',
        'notes' => 'Please re-upload the GCE A/L scan.',
    ])->assertRedirect();

    $fresh = $application->fresh();
    expect($fresh->status)->toBe(ApplicationStatus::DocumentsRequested)
        ->and($fresh->decision_notes)->toBe('Please re-upload the GCE A/L scan.');
});

it('refuses to triage a draft application', function () {
    $application = Application::factory()->create();
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->post(route('sao.applications.triage', $application), [
        'status' => 'under_review',
    ]);

    $response->assertSessionHasErrors('status');
    expect($application->fresh()->status)->toBe(ApplicationStatus::Draft);
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

it('allows DocumentsRequested without notes once a document is rejected', function () {
    $application = Application::factory()->submitted()->create();
    ApplicationDocument::factory()->rejected()->create(['application_id' => $application->id]);
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)->post(route('sao.applications.triage', $application), [
        'status' => 'documents_requested',
    ])->assertRedirect()->assertSessionDoesntHaveErrors();

    expect($application->fresh()->status)->toBe(ApplicationStatus::DocumentsRequested);
});

it('still allows other interim transitions when no document is rejected', function () {
    $application = Application::factory()->submitted()->create();
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)->post(route('sao.applications.triage', $application), [
        'status' => 'under_review',
    ])->assertRedirect()->assertSessionDoesntHaveErrors();

    expect($application->fresh()->status)->toBe(ApplicationStatus::UnderReview);
});

it('emails the applicant when documents are requested — and again on re-entry', function () {
    Mail::fake();
    $application = Application::factory()->submitted()->create();
    ApplicationDocument::factory()->rejected('Scan is blurry.')->create(['application_id' => $application->id]);
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)->post(route('sao.applications.triage', $application), [
        'status' => 'documents_requested',
    ])->assertRedirect();

    Mail::assertSent(
        ApplicationDocumentsRequestedMail::class,
        fn (ApplicationDocumentsRequestedMail $mail): bool => $mail->hasTo($application->contact_email),
    );

    // Leave and re-enter the status: the mail must fire once per entry.
    $this->actingAs($sao)->post(route('sao.applications.triage', $application), ['status' => 'under_review']);
    $this->actingAs($sao)->post(route('sao.applications.triage', $application), ['status' => 'documents_requested']);

    Mail::assertSentCount(2);
});

it('does not re-email when re-triaged from DocumentsRequested to DocumentsRequested', function () {
    Mail::fake();
    $application = Application::factory()->state([
        'status' => ApplicationStatus::DocumentsRequested,
        'submitted_at' => now()->subDay(),
    ])->create();
    ApplicationDocument::factory()->rejected('Scan is blurry.')->create(['application_id' => $application->id]);
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)->post(route('sao.applications.triage', $application), [
        'status' => 'documents_requested',
        'notes' => 'Still waiting on the applicant.',
    ])->assertRedirect()->assertSessionDoesntHaveErrors();

    expect($application->fresh()->status)->toBe(ApplicationStatus::DocumentsRequested);
    Mail::assertNotSent(ApplicationDocumentsRequestedMail::class);
});

it('does not email on other interim transitions', function () {
    Mail::fake();
    $application = Application::factory()->submitted()->create();
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)->post(route('sao.applications.triage', $application), [
        'status' => 'under_review',
    ])->assertRedirect();

    Mail::assertNothingSent();
});
