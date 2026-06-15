<?php

namespace App\Actions\Sao;

use App\Enums\AuditAction;
use App\Enums\ResultStatus;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseResult;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishCourseResults
{
    /**
     * Publish a course's fully-scored draft results. Mirrors ReviewCoursePlanApproval:
     * the candidate rows are re-selected under a row lock inside the transaction so a
     * concurrent publish can't double-stamp, then each is marked Published with the
     * publisher and timestamp. Partial (unscored) drafts are left untouched. Returns
     * the number of results published.
     *
     * @throws ValidationException
     */
    public function publish(Course $course, User $publisher): int
    {
        return DB::transaction(function () use ($course, $publisher): int {
            $results = CourseResult::query()
                ->where('course_id', $course->id)
                ->where('status', ResultStatus::Draft->value)
                ->whereNotNull('ca_score')
                ->whereNotNull('exam_score')
                ->lockForUpdate()
                ->get();

            if ($results->isEmpty()) {
                throw ValidationException::withMessages([
                    'results' => __('There are no fully-scored draft results to publish for this course.'),
                ]);
            }

            foreach ($results as $result) {
                $result->fill([
                    'status' => ResultStatus::Published,
                    'published_at' => now(),
                    'published_by' => $publisher->id,
                ])->saveQuietly();
            }

            $count = $results->count();

            AuditLog::record(
                AuditAction::ResultsPublished,
                $course,
                ['count' => $count],
                userId: $publisher->id,
            );

            return $count;
        });
    }
}
