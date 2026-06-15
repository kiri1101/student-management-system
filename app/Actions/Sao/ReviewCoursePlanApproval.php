<?php

namespace App\Actions\Sao;

use App\Enums\AuditAction;
use App\Enums\CoursePlanStatus;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewCoursePlanApproval
{
    /**
     * Approve a submitted course plan. Mirrors ReviewDeferralAction: a re-fetch
     * under lock + status re-guard inside a transaction so a concurrent review
     * can't double-decide, with an audit row on the outcome. No mail required.
     *
     * @throws ValidationException
     */
    public function approve(Course $course, User $reviewer): Course
    {
        return DB::transaction(function () use ($course, $reviewer): Course {
            $course = $this->lockAndGuard($course);

            $course->fill([
                'plan_status' => CoursePlanStatus::Approved,
                'plan_reviewed_at' => now(),
                'plan_reviewed_by' => $reviewer->id,
                'plan_review_notes' => null,
            ])->saveQuietly();

            AuditLog::record(
                AuditAction::CoursePlanApproved,
                $course,
                ['plan_status' => CoursePlanStatus::Approved->value],
                userId: $reviewer->id,
            );

            return $course;
        });
    }

    /**
     * Reject a submitted course plan, returning it to the lecturer for revision.
     * The notes (rejection reason) are persisted and shown to the lecturer.
     *
     * @throws ValidationException
     */
    public function reject(Course $course, User $reviewer, string $notes): Course
    {
        return DB::transaction(function () use ($course, $reviewer, $notes): Course {
            $course = $this->lockAndGuard($course);

            $course->fill([
                'plan_status' => CoursePlanStatus::Rejected,
                'plan_reviewed_at' => now(),
                'plan_reviewed_by' => $reviewer->id,
                'plan_review_notes' => $notes,
            ])->saveQuietly();

            AuditLog::record(
                AuditAction::CoursePlanRejected,
                $course,
                ['plan_status' => CoursePlanStatus::Rejected->value, 'notes' => $notes],
                userId: $reviewer->id,
            );

            return $course;
        });
    }

    /**
     * Re-fetch the course under a row lock and re-guard that it is still awaiting
     * review. Only a `Submitted` plan is actionable — a concurrent decision (or a
     * plan that was never submitted) is refused.
     *
     * @throws ValidationException
     */
    private function lockAndGuard(Course $course): Course
    {
        $course = Course::query()
            ->whereKey($course->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($course->plan_status !== CoursePlanStatus::Submitted) {
            throw ValidationException::withMessages([
                'plan_status' => __('This course plan is not awaiting review.'),
            ]);
        }

        return $course;
    }
}
