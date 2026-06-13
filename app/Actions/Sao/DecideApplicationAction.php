<?php

namespace App\Actions\Sao;

use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Events\ApplicationDecided;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DecideApplicationAction
{
    /**
     * Terminal decisions an SAO is allowed to set. Withdrawn is reserved for
     * the §13.4 reactivation merge handled by RestorePriorEnrollment.
     *
     * @var list<ApplicationStatus>
     */
    private const ALLOWED_DECISIONS = [
        ApplicationStatus::Admitted,
        ApplicationStatus::Rejected,
        ApplicationStatus::Waitlisted,
    ];

    /**
     * Apply a terminal decision to an application. On Admit, atomically creates
     * a StudentProfile (with a year-scoped matricule) and assigns the Student
     * role. Always emits an ApplicationDecided audit row + post-commit event.
     *
     * @param  array<string, mixed>  $extraContext
     *
     * @throws ValidationException
     */
    public function execute(
        Application $application,
        ApplicationStatus $decision,
        ?string $notes,
        User $sao,
        array $extraContext = [],
    ): Application {
        if (! in_array($decision, self::ALLOWED_DECISIONS, strict: true)) {
            throw ValidationException::withMessages([
                'status' => __('The selected decision is not allowed.'),
            ]);
        }

        return DB::transaction(function () use ($application, $decision, $notes, $sao, $extraContext): Application {
            // Re-fetch under lock so a concurrent decision can't slip past a
            // stale status check (AUDIT.md AUD-001).
            $application = Application::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($application->isTerminal()) {
                throw ValidationException::withMessages([
                    'status' => __('This application has already been finalized.'),
                ]);
            }

            if (! $application->canTransitionTo($decision)) {
                throw ValidationException::withMessages([
                    'status' => __('This application cannot transition to the requested status.'),
                ]);
            }

            $application->fill([
                'status' => $decision,
                'decision_notes' => $notes,
                'decided_at' => now(),
                'decided_by_user_id' => $sao->id,
            ])->saveQuietly();

            if ($decision === ApplicationStatus::Admitted) {
                $this->promoteToStudent($application, $sao);
            }

            AuditLog::record(
                AuditAction::ApplicationDecided,
                $application,
                ['decision' => $decision->value, 'notes' => $notes],
                context: $extraContext,
                userId: $sao->id,
            );

            DB::afterCommit(function () use ($application, $sao): void {
                event(new ApplicationDecided($application->fresh(), $sao));
            });

            return $application;
        });
    }

    private function promoteToStudent(Application $application, User $sao): void
    {
        $year = (int) now()->year;

        $enrollment = [
            'program_offering_id' => $application->program_offering_id,
            'level' => $application->level,
            'academic_year' => (string) $year,
            'enrolled_at' => now()->toDateString(),
            'status' => StudentStatus::Active->value,
        ];

        // `student_profiles.user_id` is UNIQUE including trashed rows, so a
        // returning applicant must reuse their slot (AUDIT.md AUD-003). A
        // trashed profile is "admit as new student": restore the row but issue
        // a fresh matricule — keeping the old one is RestorePriorEnrollment's
        // job. An active profile keeps its matricule (a student's login
        // identifier never changes silently); only the enrollment moves.
        $profile = StudentProfile::withTrashed()
            ->where('user_id', $application->user_id)
            ->lockForUpdate()
            ->first();

        if ($profile === null) {
            StudentProfile::create([
                'user_id' => $application->user_id,
                'matricule' => StudentProfile::nextMatriculeForYear($year),
                ...$enrollment,
            ]);
        } else {
            if ($profile->trashed()) {
                $profile->restore();
                $enrollment['matricule'] = StudentProfile::nextMatriculeForYear($year);
            }

            $profile->fill($enrollment)->save();
        }

        $applicant = $application->applicant;

        if (! $applicant->hasRole(RoleName::Student)) {
            $applicant->assignRole(RoleName::Student);

            AuditLog::record(
                AuditAction::RoleAssigned,
                $applicant,
                ['role' => RoleName::Student->value],
                userId: $sao->id,
            );
        }
    }
}
