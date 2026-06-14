<?php

namespace App\Http\Controllers\Lecturer;

use App\Enums\AuditAction;
use App\Enums\CoursePlanStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lecturer\UpdateCoursePlanRequest;
use App\Models\AuditLog;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    /**
     * Statuses from which a lecturer may (re)submit a plan for approval: a fresh
     * Draft, or a Rejected plan returned for revision.
     *
     * @var list<CoursePlanStatus>
     */
    private const SUBMITTABLE = [CoursePlanStatus::Draft, CoursePlanStatus::Rejected];

    /**
     * The authenticated lecturer's assigned courses.
     */
    public function index(Request $request): Response
    {
        $profileId = $this->lecturerProfileId($request);

        $courses = Course::query()
            ->where('lecturer_profile_id', $profileId)
            ->orderByDesc('academic_year')
            ->orderBy('code')
            ->get()
            ->map(fn (Course $course): array => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
                'level' => $course->level,
                'academic_year' => $course->academic_year,
                'semester' => $course->semester,
                'credits' => $course->credits,
                'plan_status' => $course->plan_status->value,
                'plan_review_notes' => $course->plan_review_notes,
            ])
            ->all();

        return Inertia::render('lecturer/courses/Index', [
            'courses' => $courses,
        ]);
    }

    public function edit(Request $request, Course $course): Response
    {
        $this->authorizeOwnership($request, $course);

        return Inertia::render('lecturer/courses/Plan', [
            'course' => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
                'credits' => $course->credits,
                'semester' => $course->semester,
                'level' => $course->level,
                'academic_year' => $course->academic_year,
                'description' => $course->description,
                'plan_status' => $course->plan_status->value,
                'plan_review_notes' => $course->plan_review_notes,
            ],
        ]);
    }

    public function update(UpdateCoursePlanRequest $request, Course $course): RedirectResponse
    {
        $this->authorizeOwnership($request, $course);

        $course->update(['description' => $request->string('description')->toString()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Course plan saved.')]);

        return back();
    }

    public function submit(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeOwnership($request, $course);

        if (! in_array($course->plan_status, self::SUBMITTABLE, true)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This plan cannot be submitted in its current state.')]);

            return back();
        }

        $course->update([
            'plan_status' => CoursePlanStatus::Submitted,
            'plan_submitted_at' => now(),
            'plan_review_notes' => null,
        ]);

        AuditLog::record(
            AuditAction::CoursePlanSubmitted,
            $course,
            ['plan_status' => CoursePlanStatus::Submitted->value],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Course plan submitted for approval.')]);

        return back();
    }

    /**
     * Resolve the authenticated user's lecturer profile id, or abort if they
     * have no lecturer profile (the role middleware should prevent this, but the
     * profile is what scopes ownership).
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
