<?php

namespace App\Http\Controllers\Lecturer;

use App\Actions\Lecturer\GradeSubmission;
use App\Enums\AssignmentSubmissionStatus;
use App\Enums\AuditAction;
use App\Enums\CoursePlanStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lecturer\GradeSubmissionRequest;
use App\Http\Requests\Lecturer\StoreAssignmentRequest;
use App\Http\Requests\Lecturer\UpdateAssignmentRequest;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    /**
     * The course's assignments, newest first, each with its submission counts.
     */
    public function index(Request $request, Course $course): Response
    {
        $this->authorizeOwnership($request, $course);

        $assignments = $course->assignments()
            ->withCount([
                'submissions',
                'submissions as graded_count' => fn ($query) => $query->where('status', AssignmentSubmissionStatus::Graded->value),
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Assignment $assignment): array => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'instructions' => $assignment->instructions,
                'due_at' => $assignment->due_at->toIso8601String(),
                'max_score' => $assignment->max_score,
                'submission_count' => $assignment->submissions_count,
                'graded_count' => $assignment->graded_count,
            ])
            ->all();

        return Inertia::render('lecturer/courses/Assignments', [
            'course' => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
                'plan_status' => $course->plan_status->value,
            ],
            'assignments' => $assignments,
        ]);
    }

    public function store(StoreAssignmentRequest $request, Course $course): RedirectResponse
    {
        $this->authorizeOwnership($request, $course);
        $this->guardApproved($course);

        $assignment = $course->assignments()->create([
            'title' => $request->string('title')->toString(),
            'instructions' => $request->string('instructions')->toString(),
            'due_at' => $request->date('due_at'),
            'max_score' => $request->integer('max_score'),
            'created_by' => $request->user()->id,
        ]);

        AuditLog::record(
            AuditAction::AssignmentCreated,
            $assignment,
            ['course_id' => $course->id],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment created.')]);

        return back();
    }

    public function update(UpdateAssignmentRequest $request, Course $course, Assignment $assignment): RedirectResponse
    {
        $this->authorizeOwnership($request, $course);

        $assignment->update([
            'title' => $request->string('title')->toString(),
            'instructions' => $request->string('instructions')->toString(),
            'due_at' => $request->date('due_at'),
            'max_score' => $request->integer('max_score'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment updated.')]);

        return back();
    }

    /**
     * Soft-delete the assignment. RecordsAudit logs the Deleted event itself.
     */
    public function destroy(Request $request, Course $course, Assignment $assignment): RedirectResponse
    {
        $this->authorizeOwnership($request, $course);

        $assignment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment deleted.')]);

        return back();
    }

    /**
     * The cohort's submissions for one assignment, each with a view/download link
     * and its current grade.
     */
    public function submissions(Request $request, Course $course, Assignment $assignment): Response
    {
        $this->authorizeOwnership($request, $course);

        $submissions = $assignment->submissions()
            ->with('studentProfile.user:id,name')
            ->get()
            ->map(fn (AssignmentSubmission $submission): array => $this->submissionShape($submission))
            ->all();

        return Inertia::render('lecturer/courses/Submissions', [
            'course' => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
            ],
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'instructions' => $assignment->instructions,
                'due_at' => $assignment->due_at->toIso8601String(),
                'max_score' => $assignment->max_score,
            ],
            'submissions' => $submissions,
        ]);
    }

    public function grade(GradeSubmissionRequest $request, Course $course, Assignment $assignment, AssignmentSubmission $submission, GradeSubmission $action): RedirectResponse
    {
        $this->authorizeOwnership($request, $course);

        abort_if($submission->assignment_id !== $assignment->id, 404);

        $action->grade($submission, $request->integer('score'), $request->feedback(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Submission graded.')]);

        return back();
    }

    /**
     * @return array{id: int, student_profile_id: int, student_name: string|null, matricule: string|null, original_filename: string, mime_type: string, submitted_at: string, is_late: bool, score: int|null, feedback: string|null, status: string, view_url: string, download_url: string}
     */
    private function submissionShape(AssignmentSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'student_profile_id' => $submission->student_profile_id,
            'student_name' => $submission->studentProfile?->user?->name,
            'matricule' => $submission->studentProfile?->matricule,
            'original_filename' => $submission->original_filename,
            'mime_type' => $submission->mime_type,
            'submitted_at' => $submission->submitted_at->toIso8601String(),
            'is_late' => $submission->is_late,
            'score' => $submission->score,
            'feedback' => $submission->feedback,
            'status' => $submission->status->value,
            'view_url' => route('assignments.submission.view', $submission),
            'download_url' => route('assignments.submission.download', $submission),
        ];
    }

    /**
     * Assignments only exist on an Approved course plan.
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
