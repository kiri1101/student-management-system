<?php

namespace App\Http\Controllers\Student;

use App\Enums\CoursePlanStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\CourseSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    /**
     * The authenticated student's attendance, grouped by the approved courses in
     * their implicit cohort (offering + level + academic_year). Each course lists
     * its sessions with the student's own mark, or null where unmarked. Attendance
     * is strictly scoped to the viewer — no other student's marks are exposed.
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
                ->with(['sessions' => fn ($query) => $query->orderByDesc('scheduled_for')])
                ->orderBy('code')
                ->get();

            $marks = AttendanceRecord::query()
                ->where('student_profile_id', $profile->id)
                ->whereIn('course_session_id', $cohortCourses->flatMap->sessions->pluck('id'))
                ->pluck('status', 'course_session_id');

            $courses = $cohortCourses->map(fn (Course $course): array => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
                'academic_year' => $course->academic_year,
                'sessions' => $course->sessions->map(fn (CourseSession $session): array => [
                    'id' => $session->id,
                    'scheduled_for' => $session->scheduled_for->toIso8601String(),
                    'topic' => $session->topic,
                    'status' => $session->status->value,
                    'attendance_status' => $marks->get($session->id)?->value,
                ])->all(),
            ])->all();
        }

        return Inertia::render('student/Attendance', [
            'courses' => $courses,
        ]);
    }
}
