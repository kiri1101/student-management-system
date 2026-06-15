<?php

namespace App\Http\Controllers\Lecturer;

use App\Actions\Lecturer\MarkAttendance;
use App\Enums\AuditAction;
use App\Enums\CoursePlanStatus;
use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lecturer\MarkAttendanceRequest;
use App\Http\Requests\Lecturer\StoreCourseSessionRequest;
use App\Http\Requests\Lecturer\UpdateCourseSessionRequest;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\StudentProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CourseSessionController extends Controller
{
    /**
     * The course's session timeline. Sessions only exist on Approved courses.
     */
    public function index(Request $request, Course $course): Response
    {
        $this->authorizeOwnership($request, $course);

        $sessions = $course->sessions()
            ->orderByDesc('scheduled_for')
            ->get()
            ->map(fn (CourseSession $session): array => [
                'id' => $session->id,
                'scheduled_for' => $session->scheduled_for->toIso8601String(),
                'topic' => $session->topic,
                'duration_minutes' => $session->duration_minutes,
                'status' => $session->status->value,
            ])
            ->all();

        return Inertia::render('lecturer/courses/Sessions', [
            'course' => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
                'plan_status' => $course->plan_status->value,
            ],
            'sessions' => $sessions,
        ]);
    }

    public function store(StoreCourseSessionRequest $request, Course $course): RedirectResponse
    {
        $this->authorizeOwnership($request, $course);
        $this->guardApproved($course);

        $session = $course->sessions()->create([
            'scheduled_for' => $request->date('scheduled_for'),
            'topic' => $request->string('topic')->toString(),
            'duration_minutes' => $request->integer('duration_minutes'),
            'status' => SessionStatus::Scheduled,
        ]);

        AuditLog::record(
            AuditAction::CourseSessionScheduled,
            $session,
            ['course_id' => $course->id],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Session scheduled.')]);

        return back();
    }

    public function update(UpdateCourseSessionRequest $request, Course $course, CourseSession $session): RedirectResponse
    {
        $this->authorizeOwnership($request, $course);
        $this->guardApproved($course);

        $session->update([
            'scheduled_for' => $request->date('scheduled_for'),
            'topic' => $request->string('topic')->toString(),
            'duration_minutes' => $request->integer('duration_minutes'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Session updated.')]);

        return back();
    }

    /**
     * Soft-cancel a session by flipping its status — sessions are never hard-deleted.
     */
    public function destroy(Request $request, Course $course, CourseSession $session): RedirectResponse
    {
        $this->authorizeOwnership($request, $course);

        $session->update(['status' => SessionStatus::Cancelled]);

        AuditLog::record(
            AuditAction::CourseSessionCancelled,
            $session,
            ['course_id' => $course->id],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Session cancelled.')]);

        return back();
    }

    /**
     * The attendance-marking grid: the course cohort left-joined to any existing
     * attendance marks for this session.
     */
    public function attendance(Request $request, Course $course, CourseSession $session): Response
    {
        $this->authorizeOwnership($request, $course);

        $existing = $session->attendanceRecords()
            ->pluck('status', 'student_profile_id');

        $students = $course->cohortStudents()
            ->with('user:id,name')
            ->orderBy('matricule')
            ->get()
            ->map(fn (StudentProfile $profile): array => [
                'student_profile_id' => $profile->id,
                'name' => $profile->user?->name,
                'matricule' => $profile->matricule,
                'status' => $existing->get($profile->id)?->value,
            ])
            ->all();

        return Inertia::render('lecturer/courses/Attendance', [
            'course' => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
            ],
            'session' => [
                'id' => $session->id,
                'scheduled_for' => $session->scheduled_for->toIso8601String(),
                'topic' => $session->topic,
                'status' => $session->status->value,
            ],
            'students' => $students,
        ]);
    }

    public function markAttendance(MarkAttendanceRequest $request, Course $course, CourseSession $session, MarkAttendance $action): RedirectResponse
    {
        $this->authorizeOwnership($request, $course);

        Gate::authorize('mark-attendance');

        $action->mark($session, $request->statuses(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attendance saved.')]);

        return back();
    }

    /**
     * Sessions and attendance only exist on an Approved course plan.
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
