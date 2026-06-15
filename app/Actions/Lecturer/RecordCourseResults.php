<?php

namespace App\Actions\Lecturer;

use App\Enums\AuditAction;
use App\Enums\ResultStatus;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseResult;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordCourseResults
{
    /**
     * Upsert draft CA/exam marks for a course's cohort. Each row is keyed by
     * (course, student); a row is skipped when its student is not in the implicit
     * cohort (defense-in-depth) or when an existing result is already Published
     * (published results are locked). Returns the number of rows written.
     *
     * @param  array<int, array{student_profile_id: int, ca_score: int|null, exam_score: int|null}>  $rows
     */
    public function record(Course $course, array $rows, User $lecturer): int
    {
        return DB::transaction(function () use ($course, $rows, $lecturer): int {
            $cohortIds = $course->cohortStudents()->pluck('id')->all();

            $count = 0;

            foreach ($rows as $row) {
                $studentProfileId = $row['student_profile_id'];

                if (! in_array($studentProfileId, $cohortIds, true)) {
                    continue;
                }

                $existing = CourseResult::query()
                    ->where('course_id', $course->id)
                    ->where('student_profile_id', $studentProfileId)
                    ->first();

                if ($existing !== null && $existing->status === ResultStatus::Published) {
                    continue;
                }

                CourseResult::query()->updateOrCreate(
                    [
                        'course_id' => $course->id,
                        'student_profile_id' => $studentProfileId,
                    ],
                    [
                        'ca_score' => $row['ca_score'],
                        'exam_score' => $row['exam_score'],
                        'status' => ResultStatus::Draft,
                    ],
                );

                $count++;
            }

            if ($count > 0) {
                AuditLog::record(
                    AuditAction::ResultRecorded,
                    $course,
                    ['count' => $count],
                    userId: $lecturer->id,
                );
            }

            return $count;
        });
    }
}
