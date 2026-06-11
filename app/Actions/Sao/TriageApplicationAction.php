<?php

namespace App\Actions\Sao;

use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TriageApplicationAction
{
    /**
     * Move an application between the interim states (Submitted ↔ UnderReview ↔
     * DocumentsRequested). Refuses any transition out of a terminal state and
     * any non-interim target — those go through DecideApplicationAction.
     *
     * @throws ValidationException
     */
    public function execute(Application $application, ApplicationStatus $next, ?string $notes, User $sao): Application
    {
        return DB::transaction(function () use ($application, $next, $notes, $sao): Application {
            // Re-fetch under lock so a concurrent decision can't slip past a
            // stale status check (AUDIT.md AUD-001).
            $application = Application::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $application->canTransitionTo($next)) {
                throw ValidationException::withMessages([
                    'status' => __('This application cannot transition to the requested status.'),
                ]);
            }

            $previous = $application->status;

            $application->fill([
                'status' => $next,
                'decision_notes' => $notes,
            ])->saveQuietly();

            AuditLog::record(
                AuditAction::StatusChanged,
                $application,
                ['before' => $previous->value, 'after' => $next->value],
                userId: $sao->id,
            );

            return $application;
        });
    }
}
