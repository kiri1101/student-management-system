<?php

namespace App\Http\Controllers\Student;

use App\Enums\AuditAction;
use App\Enums\CoursePlanStatus;
use App\Enums\DisputeStatus;
use App\Enums\ResultStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreResultDisputeRequest;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseResult;
use App\Models\ResultDispute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CourseResultController extends Controller
{
    /**
     * The authenticated student's published results across the approved courses in
     * their implicit cohort (offering + level + academic_year). Only courses with a
     * published result for this student are listed; each carries the student's own
     * latest dispute (if any). Draft/unpublished results are never exposed.
     */
    public function index(Request $request): Response
    {
        $profile = $request->user()->studentProfile;

        $courses = [];

        if ($profile !== null) {
            $cohortCourses = Course::query()
                ->where('program_offering_id', $profile->program_offering_id)
                ->where('level', $profile->level)
                ->where('academic_year', $profile->academic_year)
                ->where('plan_status', CoursePlanStatus::Approved->value)
                ->orderBy('code')
                ->get();

            $results = CourseResult::query()
                ->where('student_profile_id', $profile->id)
                ->where('status', ResultStatus::Published->value)
                ->whereIn('course_id', $cohortCourses->pluck('id'))
                ->with(['disputes' => fn ($query) => $query
                    ->where('student_profile_id', $profile->id)
                    ->orderByDesc('created_at')])
                ->get()
                ->keyBy('course_id');

            $courses = $cohortCourses
                ->filter(fn (Course $course): bool => $results->has($course->id))
                ->map(function (Course $course) use ($results): array {
                    /** @var CourseResult $result */
                    $result = $results->get($course->id);

                    return [
                        'id' => $course->id,
                        'code' => $course->code,
                        'title' => $course->title,
                        'academic_year' => $course->academic_year,
                        'ca_score' => $result->ca_score,
                        'exam_score' => $result->exam_score,
                        'final_score' => $result->final_score,
                        'grade' => $result->grade,
                        'result_id' => $result->id,
                        'dispute' => $this->disputeShape($result->disputes->first()),
                    ];
                })
                ->values()
                ->all();
        }

        return Inertia::render('student/results/Index', [
            'courses' => $courses,
        ]);
    }

    public function dispute(StoreResultDisputeRequest $request, CourseResult $result): RedirectResponse
    {
        $profile = $request->user()->studentProfile;

        if ($profile === null) {
            throw ValidationException::withMessages([
                'reason' => __('You need an active student enrollment before disputing a result.'),
            ]);
        }

        abort_unless($result->student_profile_id === $profile->id, 403);
        abort_unless($result->status === ResultStatus::Published, 403);

        $hasOpen = ResultDispute::query()
            ->where('course_result_id', $result->id)
            ->whereIn('status', [DisputeStatus::Open->value, DisputeStatus::UnderReview->value])
            ->exists();

        if ($hasOpen) {
            throw ValidationException::withMessages([
                'reason' => __('You already have an open dispute for this result.'),
            ]);
        }

        $dispute = ResultDispute::create([
            'course_result_id' => $result->id,
            'student_profile_id' => $profile->id,
            'reason' => $request->string('reason')->toString(),
            'status' => DisputeStatus::Open,
        ]);

        AuditLog::record(
            AuditAction::DisputeRaised,
            $dispute,
            ['course_result_id' => $result->id],
            userId: $profile->user_id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dispute submitted.')]);

        return back();
    }

    /**
     * Shape the student's own latest dispute for a result, or null if none.
     *
     * @return array{id: int, status: string, reason: string, resolution_notes: string|null, created_at: string}|null
     */
    private function disputeShape(?ResultDispute $dispute): ?array
    {
        if ($dispute === null) {
            return null;
        }

        return [
            'id' => $dispute->id,
            'status' => $dispute->status->value,
            'reason' => $dispute->reason,
            'resolution_notes' => $dispute->resolution_notes,
            'created_at' => $dispute->created_at->toIso8601String(),
        ];
    }
}
