<?php

namespace App\Actions\Sao;

use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Events\ApplicationDecided;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestorePriorEnrollment
{
    /**
     * Re-attach a returning applicant to their prior soft-deleted StudentProfile
     * and Withdraw the current application as merged. Per §13.4 of the project
     * plan: profile->restore + Student role + current Application->Withdrawn,
     * all in one transaction. Emits Restored (auto), RoleAssigned (manual),
     * StatusChanged (manual) and ApplicationDecided (manual w/ merge context)
     * audit rows, plus a post-commit ApplicationDecided event.
     *
     * @throws ValidationException
     */
    public function execute(
        User $applicant,
        StudentProfile $prior,
        Application $current,
        User $sao,
        ?string $notes = null,
    ): Application {
        if ($prior->user_id !== $applicant->id || $current->user_id !== $applicant->id) {
            throw ValidationException::withMessages([
                'prior_profile_id' => __('The selected prior enrollment does not belong to this applicant.'),
            ]);
        }

        $mergeNote = trim(sprintf(
            'Merged into prior enrollment #%d.%s',
            $prior->id,
            $notes !== null && $notes !== '' ? ' '.$notes : '',
        ));

        return DB::transaction(function () use ($applicant, $prior, $current, $sao, $mergeNote): Application {
            // Re-fetch both rows under lock so a concurrent decision or
            // restore can't slip past stale checks (AUDIT.md AUD-001).
            $current = Application::query()
                ->whereKey($current->id)
                ->lockForUpdate()
                ->firstOrFail();

            $prior = StudentProfile::withTrashed()
                ->whereKey($prior->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $prior->trashed()) {
                throw ValidationException::withMessages([
                    'prior_profile_id' => __('The selected prior enrollment is not archived.'),
                ]);
            }

            if ($current->isTerminal()) {
                throw ValidationException::withMessages([
                    'status' => __('This application has already been finalized.'),
                ]);
            }

            if (! $current->canTransitionTo(ApplicationStatus::Withdrawn)) {
                throw ValidationException::withMessages([
                    'status' => __('This application cannot transition to the requested status.'),
                ]);
            }

            $prior->restore();

            $applicant->assignRole(RoleName::Student);
            AuditLog::record(
                AuditAction::RoleAssigned,
                $applicant,
                ['role' => RoleName::Student->value],
                userId: $sao->id,
            );

            $previous = $current->status;
            $current->fill([
                'status' => ApplicationStatus::Withdrawn,
                'decision_notes' => $mergeNote,
                'decided_at' => now(),
                'decided_by_user_id' => $sao->id,
            ])->saveQuietly();

            AuditLog::record(
                AuditAction::StatusChanged,
                $current,
                ['before' => $previous->value, 'after' => ApplicationStatus::Withdrawn->value],
                userId: $sao->id,
            );

            AuditLog::record(
                AuditAction::ApplicationDecided,
                $current,
                ['decision' => ApplicationStatus::Withdrawn->value, 'notes' => $mergeNote],
                context: [
                    'merged_into_prior' => true,
                    'prior_profile_id' => $prior->id,
                ],
                userId: $sao->id,
            );

            DB::afterCommit(function () use ($current, $sao): void {
                event(new ApplicationDecided($current->fresh(), $sao));
            });

            return $current;
        });
    }
}
