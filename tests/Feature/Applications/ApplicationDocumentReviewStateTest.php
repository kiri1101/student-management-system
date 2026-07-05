<?php

use App\Enums\ApplicationDocumentStatus;
use App\Models\ApplicationDocument;
use App\Models\User;

it('defaults a new document to pending with empty review metadata', function () {
    $document = ApplicationDocument::factory()->create();

    expect($document->status)->toBe(ApplicationDocumentStatus::Pending)
        ->and($document->review_notes)->toBeNull()
        ->and($document->reviewed_by)->toBeNull()
        ->and($document->reviewed_at)->toBeNull();
});

it('produces accepted and rejected documents through the factory states', function () {
    $accepted = ApplicationDocument::factory()->accepted()->create();
    $rejected = ApplicationDocument::factory()->rejected('Scan is blurry.')->create();

    expect($accepted->status)->toBe(ApplicationDocumentStatus::Accepted)
        ->and($accepted->reviewedBy)->toBeInstanceOf(User::class)
        ->and($accepted->reviewed_at)->not->toBeNull()
        ->and($rejected->status)->toBe(ApplicationDocumentStatus::Rejected)
        ->and($rejected->review_notes)->toBe('Scan is blurry.');
});
