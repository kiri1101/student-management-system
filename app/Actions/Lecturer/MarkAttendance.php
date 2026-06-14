<?php

namespace App\Actions\Lecturer;

use App\Enums\AttendanceStatus;
use App\Enums\AuditAction;
use App\Enums\CoursePlanStatus;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\CourseSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkAttendance
{
    /**
     * Upsert attendance marks for a session's cohort in one transaction. The
     * session is re-fetched under lock and its course re-guarded as Approved;
     * only student_profile_ids belonging to the course's implicit cohort are
     * applied, any others in the payload are silently skipped. A single audit
     * row records the number of marks written.
     *
     * @param  array<int, string>  $statuses  student_profile_id => AttendanceStatus value
     *
     * @throws ValidationException
     */
    public function mark(CourseSession $session, array $statuses, User $marker): void
    {
        DB::transaction(function () use ($session, $statuses, $marker): void {
            $session = CourseSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            $course = $session->course;

            if ($course->plan_status !== CoursePlanStatus::Approved) {
                throw ValidationException::withMessages([
                    'course' => __('Attendance can only be marked for an approved course.'),
                ]);
            }

            $cohortIds = $course->cohortStudents()->pluck('id')->all();

            $count = 0;

            foreach ($statuses as $studentProfileId => $status) {
                if (! in_array((int) $studentProfileId, $cohortIds, true)) {
                    continue;
                }

                AttendanceRecord::updateOrCreate(
                    [
                        'course_session_id' => $session->id,
                        'student_profile_id' => (int) $studentProfileId,
                    ],
                    [
                        'status' => AttendanceStatus::from($status),
                        'marked_by' => $marker->id,
                        'marked_at' => now(),
                    ],
                );

                $count++;
            }

            AuditLog::record(
                AuditAction::AttendanceMarked,
                $session,
                ['count' => $count],
                userId: $marker->id,
            );
        });
    }
}
