<?php

namespace App\Actions\Sao;

use App\Actions\Concerns\ResolvesDocumentsRequested;
use App\Enums\ApplicationDocumentStatus;
use App\Enums\AuditAction;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ReviewApplicationDocument
{
    use ResolvesDocumentsRequested;

    /**
     * Accept or reject a single application document. Rejection carries the
     * reason shown to the applicant; acceptance clears any prior reason. When
     * an acceptance resolves the last rejected document of a DocumentsRequested
     * application, the application flips back to Submitted (shared concern).
     *
     * @throws ValidationException
     */
    public function execute(ApplicationDocument $document, ApplicationDocumentStatus $decision, ?string $notes, User $reviewer): ApplicationDocument
    {
        if ($decision === ApplicationDocumentStatus::Pending) {
            throw new InvalidArgumentException('A document review decision must be accepted or rejected.');
        }

        return DB::transaction(function () use ($document, $decision, $notes, $reviewer): ApplicationDocument {
            // Re-fetch both rows under lock so a concurrent decision or replace
            // can't slip past a stale status check (AUDIT.md AUD-001 pattern).
            $application = Application::query()
                ->whereKey($document->application_id)
                ->lockForUpdate()
                ->firstOrFail();

            $document = ApplicationDocument::query()
                ->whereKey($document->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($application->isTerminal()) {
                throw ValidationException::withMessages([
                    'status' => __('Documents on a decided application can no longer be reviewed.'),
                ]);
            }

            $document->fill([
                'status' => $decision,
                'review_notes' => $decision === ApplicationDocumentStatus::Rejected ? $notes : null,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ])->saveQuietly();

            AuditLog::record(
                $decision === ApplicationDocumentStatus::Accepted
                    ? AuditAction::DocumentAccepted
                    : AuditAction::DocumentRejected,
                $document,
                ['status' => $decision->value, 'notes' => $notes],
                userId: $reviewer->id,
            );

            if ($decision === ApplicationDocumentStatus::Accepted) {
                $this->flipToSubmittedWhenResolved($application, $reviewer);
            }

            return $document;
        });
    }
}
