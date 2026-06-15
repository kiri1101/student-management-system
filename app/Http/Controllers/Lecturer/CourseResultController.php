<?php

namespace App\Http\Controllers\Lecturer;

use App\Actions\Lecturer\RecordCourseResults;
use App\Enums\CoursePlanStatus;
use App\Enums\ResultStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lecturer\RecordCourseResultsRequest;
use App\Models\Course;
use App\Models\CourseResult;
use App\Models\StudentProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseResultController extends Controller
{
    /**
     * The marks sheet for a course: one row per cohort student, pre-filled with any
     * existing CA/exam marks and the computed final score, grade and status.
     */
    public function index(Request $request, Course $course): Response
    {
        $this->authorizeOwnership($request, $course);

        $students = $course->cohortStudents()
            ->with('user:id,name')
            ->orderBy('matricule')
            ->get();

        $results = CourseResult::query()
            ->where('course_id', $course->id)
            ->get()
            ->keyBy('student_profile_id');

        $rows = $students->map(function (StudentProfile $student) use ($results): array {
            /** @var CourseResult|null $result */
            $result = $results->get($student->id);

            return [
                'student_profile_id' => $student->id,
                'student_name' => $student->user?->name,
                'matricule' => $student->matricule,
                'ca_score' => $result?->ca_score,
                'exam_score' => $result?->exam_score,
                'final_score' => $result?->final_score,
                'grade' => $result?->grade,
                'status' => $result?->status->value ?? ResultStatus::Draft->value,
            ];
        })->all();

        $draftCount = $results->filter(fn (CourseResult $result): bool => $result->status === ResultStatus::Draft)->count();
        $publishedCount = $results->filter(fn (CourseResult $result): bool => $result->status === ResultStatus::Published)->count();
        $publishableCount = $results->filter(fn (CourseResult $result): bool => $result->status === ResultStatus::Draft
            && $result->ca_score !== null
            && $result->exam_score !== null)->count();

        return Inertia::render('lecturer/courses/Results', [
            'course' => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
                'plan_status' => $course->plan_status->value,
            ],
            'results' => $rows,
            'draft_count' => $draftCount,
            'published_count' => $publishedCount,
            'publishable_count' => $publishableCount,
        ]);
    }

    public function store(RecordCourseResultsRequest $request, Course $course, RecordCourseResults $action): RedirectResponse
    {
        $this->authorizeOwnership($request, $course);
        $this->guardApproved($course);

        $action->record($course, $request->rows(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Results saved.')]);

        return back();
    }

    /**
     * Results only exist on an Approved course plan.
     */
    private function guardApproved(Course $course): void
    {
        abort_unless($course->plan_status === CoursePlanStatus::Approved, 403);
    }

    /**
     * Resolve the authenticated user's lecturer profile id, or abort if they
     * have no lecturer profile.
     */
    private function lecturerProfileId(Request $request): int
    {
        $profile = $request->user()->lecturerProfile;

        abort_if($profile === null, 403);

        return $profile->id;
    }

    /**
     * Guard that the course is assigned to the authenticated lecturer.
     */
    private function authorizeOwnership(Request $request, Course $course): void
    {
        abort_unless($course->lecturer_profile_id === $this->lecturerProfileId($request), 403);
    }
}
