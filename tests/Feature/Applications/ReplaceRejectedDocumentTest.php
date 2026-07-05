<?php

use App\Enums\ApplicationDocumentStatus;
use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake();
});

/** An application parked in DocumentsRequested. */
function docsRequestedApplication(): Application
{
    return Application::factory()->state([
        'status' => ApplicationStatus::DocumentsRequested,
        'submitted_at' => now()->subDay(),
    ])->create();
}

/**
 * A rejected document of the given type code on the application. Distinct
 * codes matter: (application_id, document_type_id) is unique.
 */
function rejectedDocOfType(Application $application, string $code): ApplicationDocument
{
    $type = DocumentType::firstOrCreate(['code' => $code], ['name' => $code.' document']);

    return ApplicationDocument::factory()->rejected('Please provide a readable scan.')->create([
        'application_id' => $application->id,
        'document_type_id' => $type->id,
    ]);
}

it('forbids replacing a document on another user\'s application', function () {
    $application = docsRequestedApplication();
    $document = rejectedDocOfType($application, 'NID');
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->post(route('application.documents.replace', [$application, $document]), [
            'document' => UploadedFile::fake()->create('scan.pdf', 512, 'application/pdf'),
        ])
        ->assertForbidden();
});

it('refuses when the application is not in DocumentsRequested', function () {
    $application = Application::factory()->submitted()->create();
    $document = rejectedDocOfType($application, 'NID');

    $this->actingAs($application->applicant)
        ->post(route('application.documents.replace', [$application, $document]), [
            'document' => UploadedFile::fake()->create('scan.pdf', 512, 'application/pdf'),
        ])
        ->assertSessionHasErrors('document');

    expect($document->fresh()->status)->toBe(ApplicationDocumentStatus::Rejected);
});

it('refuses when the document is not rejected', function () {
    $application = docsRequestedApplication();
    rejectedDocOfType($application, 'BIRTH');
    $pendingType = DocumentType::firstOrCreate(['code' => 'NID'], ['name' => 'National Identity']);
    $pending = ApplicationDocument::factory()->create([
        'application_id' => $application->id,
        'document_type_id' => $pendingType->id,
    ]);

    $this->actingAs($application->applicant)
        ->post(route('application.documents.replace', [$application, $pending]), [
            'document' => UploadedFile::fake()->create('scan.pdf', 512, 'application/pdf'),
        ])
        ->assertSessionHasErrors('document');
});

it('validates the upload mime and size', function () {
    $application = docsRequestedApplication();
    $document = rejectedDocOfType($application, 'NID');

    $this->actingAs($application->applicant)
        ->post(route('application.documents.replace', [$application, $document]), [
            'document' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])
        ->assertSessionHasErrors('document');

    $this->actingAs($application->applicant)
        ->post(route('application.documents.replace', [$application, $document]), [
            'document' => UploadedFile::fake()->create('huge.pdf', 9000, 'application/pdf'),
        ])
        ->assertSessionHasErrors('document');
});

it('replaces the file in place and resets the review state', function () {
    $application = docsRequestedApplication();
    rejectedDocOfType($application, 'BIRTH'); // second outstanding doc — no flip yet
    $document = rejectedDocOfType($application, 'NID');
    $oldPath = $document->file_path;
    Storage::put($oldPath, 'old-content');

    $this->actingAs($application->applicant)
        ->post(route('application.documents.replace', [$application, $document]), [
            'document' => UploadedFile::fake()->create('nid-rescan.pdf', 512, 'application/pdf'),
        ])
        ->assertRedirect();

    $fresh = $document->fresh();
    expect($fresh->status)->toBe(ApplicationDocumentStatus::Pending)
        ->and($fresh->original_filename)->toBe('nid-rescan.pdf')
        ->and($fresh->review_notes)->toBeNull()
        ->and($fresh->reviewed_by)->toBeNull()
        ->and($fresh->reviewed_at)->toBeNull()
        ->and($fresh->file_path)->not->toBe($oldPath);

    Storage::assertMissing($oldPath);
    Storage::assertExists($fresh->file_path);

    AuditLog::query()
        ->where('subject_type', $document->getMorphClass())
        ->where('subject_id', $document->id)
        ->where('action', AuditAction::DocumentResubmitted->value)
        ->sole();

    // One rejected document remains — the application must not flip yet.
    expect($application->fresh()->status)->toBe(ApplicationStatus::DocumentsRequested);
});

it('flips to Submitted when the last rejected document is replaced', function () {
    $application = docsRequestedApplication();
    $originalSubmittedAt = $application->submitted_at;
    $document = rejectedDocOfType($application, 'NID');

    $this->actingAs($application->applicant)
        ->post(route('application.documents.replace', [$application, $document]), [
            'document' => UploadedFile::fake()->create('nid-rescan.pdf', 512, 'application/pdf'),
        ])
        ->assertRedirect();

    $fresh = $application->fresh();
    expect($fresh->status)->toBe(ApplicationStatus::Submitted)
        ->and($fresh->submitted_at->equalTo($originalSubmittedAt))->toBeTrue();

    $log = AuditLog::query()
        ->where('subject_type', $application->getMorphClass())
        ->where('subject_id', $application->id)
        ->where('action', AuditAction::StatusChanged->value)
        ->sole();
    expect($log->changes)->toBe(['before' => 'documents_requested', 'after' => 'submitted'])
        ->and($log->user_id)->toBe($application->user_id);
});

it('scopes the document to its application', function () {
    $application = docsRequestedApplication();
    $foreign = rejectedDocOfType(docsRequestedApplication(), 'NID');

    $this->actingAs($application->applicant)
        ->post(route('application.documents.replace', [$application, $foreign]), [
            'document' => UploadedFile::fake()->create('scan.pdf', 512, 'application/pdf'),
        ])
        ->assertNotFound();
});
