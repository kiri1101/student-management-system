<?php

namespace App\Actions\Student;

use App\Enums\AssignmentSubmissionStatus;
use App\Enums\AuditAction;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\StudentProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SubmitAssignment
{
    /**
     * Record (or replace) a student's submission for an assignment. A student may
     * resubmit any number of times: the row is upserted on (assignment, student)
     * and grading metadata is reset, so a fresh submission always lands as
     * Submitted/ungraded.
     *
     * The new file is written to disk before any DB write (mirrors
     * PaymentController::store, AUDIT.md AUD-009). On success any previously
     * stored file is deleted out-of-transaction so a failed write never orphans
     * the live slip; on failure the just-written file is cleaned up and the error
     * rethrown.
     */
    public function submit(Assignment $assignment, StudentProfile $profile, UploadedFile $file): AssignmentSubmission
    {
        $isLate = now()->greaterThan($assignment->due_at);

        $newPath = $file->store('assignment-submissions');

        try {
            $oldPath = AssignmentSubmission::query()
                ->where('assignment_id', $assignment->id)
                ->where('student_profile_id', $profile->id)
                ->value('file_path');

            $submission = AssignmentSubmission::updateOrCreate(
                [
                    'assignment_id' => $assignment->id,
                    'student_profile_id' => $profile->id,
                ],
                [
                    'file_path' => $newPath,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size_bytes' => $file->getSize(),
                    'submitted_at' => now(),
                    'is_late' => $isLate,
                    'status' => AssignmentSubmissionStatus::Submitted,
                    'score' => null,
                    'feedback' => null,
                    'graded_by' => null,
                    'graded_at' => null,
                ],
            );
        } catch (Throwable $exception) {
            Storage::delete($newPath);

            throw $exception;
        }

        if ($oldPath !== null && $oldPath !== $newPath) {
            Storage::delete($oldPath);
        }

        AuditLog::record(
            AuditAction::AssignmentSubmitted,
            $submission,
            ['assignment_id' => $assignment->id, 'is_late' => $isLate],
            userId: $profile->user_id,
        );

        return $submission;
    }
}
