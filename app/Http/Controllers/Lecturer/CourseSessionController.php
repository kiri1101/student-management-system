<?php

namespace App\Http\Controllers\Lecturer;

use App\Actions\Lecturer\MarkAttendance;
use App\Enums\AuditAction;
use App\Enums\CoursePlanStatus;
use App\Enums\SessionChangeType;
use App\Enums\SessionStatus;
use App\Events\CourseSessionChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lecturer\CancelCourseSessionRequest;
use App\Http\Requests\Lecturer\MarkAttendanceRequest;
use App\Http\Requests\Lecturer\StoreCourseSessionRequest;
use App\Http\Requests\Lecturer\UpdateCourseSessionRequest;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\StudentProfile;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $previous = $session->scheduled_for;

        $session->update([
            'scheduled_for' => $request->date('scheduled_for'),
            'topic' => $request->string('topic')->toString(),
            'duration_minutes' => $request->integer('duration_minutes'),
        ]);

        // Only a genuine time move on a still-scheduled, future session is a
        // reschedule worth notifying the cohort about — topic/duration-only
        // edits stay silent.
        $rescheduled = $session->status === SessionStatus::Scheduled
            && ! $session->scheduled_for->equalTo($previous)
            && $session->scheduled_for->isFuture();

        if ($rescheduled) {
            AuditLog::record(
                AuditAction::CourseSessionRescheduled,
                $session,
                ['course_id' => $course->id],
            );

            CourseSessionChanged::dispatch(
                $session,
                SessionChangeType::Rescheduled,
                null,
                $previous instanceof CarbonImmutable ? $previous : $previous->toImmutable(),
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Session updated.')]);

        return back();
    }

    /**
     * Soft-cancel a session by flipping its status — sessions are never hard-deleted.
     * Only a future, currently-scheduled session notifies the cohort; flipping an
     * already-cancelled or past session stays silent.
     */
    public function destroy(CancelCourseSessionRequest $request, Course $course, CourseSession $session): RedirectResponse
    {
        $this->authorizeOwnership($request, $course);

        $shouldNotify = $session->status === SessionStatus::Scheduled && $session->scheduled_for->isFuture();

        $reason = $request->string('reason')->toString() ?: null;

        $session->update([
            'status' => SessionStatus::Cancelled,
            'cancellation_reason' => $reason,
        ]);

        AuditLog::record(
            AuditAction::CourseSessionCancelled,
            $session,
            ['course_id' => $course->id],
        );

        if ($shouldNotify) {
            CourseSessionChanged::dispatch($session, SessionChangeType::Cancelled, $reason);
        }

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
