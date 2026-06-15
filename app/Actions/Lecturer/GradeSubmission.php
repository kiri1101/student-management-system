<?php

namespace App\Actions\Lecturer;

use App\Enums\AssignmentSubmissionStatus;
use App\Enums\AuditAction;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GradeSubmission
{
    /**
     * Record a lecturer's grade for a submission. The submission is re-fetched
     * under a row lock inside the transaction so a concurrent grade can't race,
     * then stamped with the score, feedback and grader. The score bound is
     * enforced by GradeSubmissionRequest against the assignment's max_score.
     */
    public function grade(AssignmentSubmission $submission, int $score, ?string $feedback, User $grader): void
    {
        DB::transaction(function () use ($submission, $score, $feedback, $grader): void {
            $submission = AssignmentSubmission::query()
                ->whereKey($submission->id)
                ->lockForUpdate()
                ->firstOrFail();

            $submission->fill([
                'score' => $score,
                'feedback' => $feedback,
                'graded_by' => $grader->id,
                'graded_at' => now(),
                'status' => AssignmentSubmissionStatus::Graded,
            ])->save();

            AuditLog::record(
                AuditAction::AssignmentGraded,
                $submission,
                ['score' => $score],
                userId: $grader->id,
            );
        });
    }
}
