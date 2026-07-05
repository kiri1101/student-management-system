<?php

use App\Enums\ApplicationDocumentStatus;
use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\DocumentType;

/**
 * A document of the given type code attached to the application. Distinct
 * codes matter: (application_id, document_type_id) is unique.
 */
function reviewDocOfType(Application $application, string $code): ApplicationDocument
{
    $type = DocumentType::firstOrCreate(['code' => $code], ['name' => $code.' document']);

    return ApplicationDocument::factory()->create([
        'application_id' => $application->id,
        'document_type_id' => $type->id,
    ]);
}

it('accepts a document and records reviewer metadata and an audit row', function () {
    $application = Application::factory()->submitted()->create();
    $document = reviewDocOfType($application, 'NID');
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)
        ->post(route('sao.applications.documents.accept', [$application, $document]))
        ->assertRedirect();

    $fresh = $document->fresh();
    expect($fresh->status)->toBe(ApplicationDocumentStatus::Accepted)
        ->and($fresh->review_notes)->toBeNull()
        ->and($fresh->reviewed_by)->toBe($sao->id)
        ->and($fresh->reviewed_at)->not->toBeNull();

    AuditLog::query()
        ->where('subject_type', $document->getMorphClass())
        ->where('subject_id', $document->id)
        ->where('action', AuditAction::DocumentAccepted->value)
        ->sole();
});

it('rejects a document with a reason and an audit row', function () {
    $application = Application::factory()->submitted()->create();
    $document = reviewDocOfType($application, 'NID');
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)
        ->post(route('sao.applications.documents.reject', [$application, $document]), [
            'notes' => 'The scan is cropped — edges are missing.',
        ])
        ->assertRedirect();

    $fresh = $document->fresh();
    expect($fresh->status)->toBe(ApplicationDocumentStatus::Rejected)
        ->and($fresh->review_notes)->toBe('The scan is cropped — edges are missing.')
        ->and($fresh->reviewed_by)->toBe($sao->id);

    $log = AuditLog::query()
        ->where('subject_type', $document->getMorphClass())
        ->where('subject_id', $document->id)
        ->where('action', AuditAction::DocumentRejected->value)
        ->sole();
    expect($log->changes['notes'])->toBe('The scan is cropped — edges are missing.');
});

it('requires notes when rejecting', function () {
    $application = Application::factory()->submitted()->create();
    $document = reviewDocOfType($application, 'NID');
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)
        ->post(route('sao.applications.documents.reject', [$application, $document]))
        ->assertSessionHasErrors('notes');

    expect($document->fresh()->status)->toBe(ApplicationDocumentStatus::Pending);
});

it('forbids non-SAO roles from reviewing documents', function () {
    $application = Application::factory()->submitted()->create();
    $document = reviewDocOfType($application, 'NID');
    $lecturer = userWithRole(RoleName::Lecturer);

    $this->actingAs($lecturer)
        ->post(route('sao.applications.documents.accept', [$application, $document]))
        ->assertForbidden();
});

it('refuses review on a terminal application', function () {
    $application = Application::factory()->state([
        'status' => ApplicationStatus::Admitted,
        'submitted_at' => now()->subDay(),
        'decided_at' => now(),
    ])->create();
    $document = reviewDocOfType($application, 'NID');
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)
        ->post(route('sao.applications.documents.accept', [$application, $document]))
        ->assertSessionHasErrors('status');

    expect($document->fresh()->status)->toBe(ApplicationDocumentStatus::Pending);
});

it('scopes the document to its application', function () {
    $application = Application::factory()->submitted()->create();
    $foreign = reviewDocOfType(Application::factory()->submitted()->create(), 'NID');
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)
        ->post(route('sao.applications.documents.accept', [$application, $foreign]))
        ->assertNotFound();
});

it('flips a DocumentsRequested application to Submitted when accepting the last rejected document', function () {
    $application = Application::factory()->state([
        'status' => ApplicationStatus::DocumentsRequested,
        'submitted_at' => now()->subDay(),
    ])->create();
    $rejected = reviewDocOfType($application, 'NID');
    $rejected->update(['status' => ApplicationDocumentStatus::Rejected->value, 'review_notes' => 'Blurry.']);
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)
        ->post(route('sao.applications.documents.accept', [$application, $rejected]))
        ->assertRedirect();

    expect($application->fresh()->status)->toBe(ApplicationStatus::Submitted);

    $log = AuditLog::query()
        ->where('subject_type', $application->getMorphClass())
        ->where('subject_id', $application->id)
        ->where('action', AuditAction::StatusChanged->value)
        ->sole();
    expect($log->changes)->toBe(['before' => 'documents_requested', 'after' => 'submitted'])
        ->and($log->user_id)->toBe($sao->id);
});

it('does not flip while another rejected document remains', function () {
    $application = Application::factory()->state([
        'status' => ApplicationStatus::DocumentsRequested,
        'submitted_at' => now()->subDay(),
    ])->create();
    $first = reviewDocOfType($application, 'NID');
    $second = reviewDocOfType($application, 'BIRTH');
    $first->update(['status' => ApplicationDocumentStatus::Rejected->value]);
    $second->update(['status' => ApplicationDocumentStatus::Rejected->value]);
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)
        ->post(route('sao.applications.documents.accept', [$application, $first]))
        ->assertRedirect();

    expect($application->fresh()->status)->toBe(ApplicationStatus::DocumentsRequested);
});
