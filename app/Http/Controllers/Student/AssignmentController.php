<?php

namespace App\Http\Controllers\Student;

use App\Actions\Student\SubmitAssignment;
use App\Enums\CoursePlanStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreAssignmentSubmissionRequest;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    /**
     * The authenticated student's assignments, grouped by the approved courses in
     * their implicit cohort (offering + level + academic_year). Each assignment
     * carries the student's own submission, or null when nothing was submitted.
     * Submissions are strictly scoped to the viewer — no peer's work is exposed.
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
                ->with(['assignments' => fn ($query) => $query->orderByDesc('due_at')])
                ->orderBy('code')
                ->get();

            $assignmentIds = $cohortCourses->flatMap->assignments->pluck('id');

            $submissions = AssignmentSubmission::query()
                ->where('student_profile_id', $profile->id)
                ->whereIn('assignment_id', $assignmentIds)
                ->get()
                ->keyBy('assignment_id');

            $courses = $cohortCourses->map(fn (Course $course): array => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
                'academic_year' => $course->academic_year,
                'assignments' => $course->assignments->map(fn (Assignment $assignment): array => [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'instructions' => $assignment->instructions,
                    'due_at' => $assignment->due_at->toIso8601String(),
                    'max_score' => $assignment->max_score,
                    'submission' => $this->submissionShape($submissions->get($assignment->id)),
                ])->all(),
            ])->all();
        }

        return Inertia::render('student/assignments/Index', [
            'courses' => $courses,
        ]);
    }

    public function submit(StoreAssignmentSubmissionRequest $request, Assignment $assignment, SubmitAssignment $action): RedirectResponse
    {
        $profile = $request->user()->studentProfile;

        if ($profile === null) {
            throw ValidationException::withMessages([
                'file' => __('You need an active student enrollment before submitting an assignment.'),
            ]);
        }

        $course = $assignment->course;

        abort_unless($course->plan_status === CoursePlanStatus::Approved, 403);
        abort_unless(
            $course->program_offering_id === $profile->program_offering_id
                && $course->level === $profile->level
                && $course->academic_year === $profile->academic_year,
            403,
        );

        $action->submit($assignment, $profile, $request->file('file'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment submitted.')]);

        return back();
    }

    /**
     * Shape the viewer's own submission for an assignment, or null if none.
     *
     * @return array{original_filename: string, submitted_at: string, is_late: bool, score: int|null, feedback: string|null, status: string, view_url: string, download_url: string}|null
     */
    private function submissionShape(?AssignmentSubmission $submission): ?array
    {
        if ($submission === null) {
            return null;
        }

        return [
            'original_filename' => $submission->original_filename,
            'submitted_at' => $submission->submitted_at->toIso8601String(),
            'is_late' => $submission->is_late,
            'score' => $submission->score,
            'feedback' => $submission->feedback,
            'status' => $submission->status->value,
            'view_url' => route('assignments.submission.view', $submission),
            'download_url' => route('assignments.submission.download', $submission),
        ];
    }
}
