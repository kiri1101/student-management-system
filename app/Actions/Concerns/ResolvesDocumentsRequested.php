<?php

namespace App\Actions\Concerns;

use App\Enums\ApplicationDocumentStatus;
use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\User;

trait ResolvesDocumentsRequested
{
    /**
     * Flip a DocumentsRequested application back to Submitted once no rejected
     * document remains. Callers invoke this inside their transaction while the
     * application row is locked; it is a no-op in any other status or while a
     * rejected document is still outstanding.
     */
    protected function flipToSubmittedWhenResolved(Application $application, User $actor): void
    {
        if ($application->status !== ApplicationStatus::DocumentsRequested) {
            return;
        }

        $hasRejected = $application->documents()
            ->where('status', ApplicationDocumentStatus::Rejected->value)
            ->exists();

        if ($hasRejected) {
            return;
        }

        $application->fill(['status' => ApplicationStatus::Submitted])->saveQuietly();

        AuditLog::record(
            AuditAction::StatusChanged,
            $application,
            [
                'before' => ApplicationStatus::DocumentsRequested->value,
                'after' => ApplicationStatus::Submitted->value,
            ],
            userId: $actor->id,
        );
    }
}
