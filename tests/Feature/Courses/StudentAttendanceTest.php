<?php

use App\Enums\AttendanceStatus;
use App\Enums\RoleName;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\LecturerProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->withoutVite();
});

/**
 * Creates a student user bound to a StudentProfile inside the given course cohort
 * and returns the profile.
 */
function c2AttendStudent(Course $course): StudentProfile
{
    $user = userWithRole(RoleName::Student);

    return StudentProfile::factory()->create([
        'user_id' => $user->id,
        'program_offering_id' => $course->program_offering_id,
        'level' => $course->level,
        'academic_year' => $course->academic_year,
    ]);
}

/**
 * Builds an approved course with a scheduled session for cohort wiring.
 *
 * @return array{0:Course,1:CourseSession}
 */
function c2AttendCourseWithSession(): array
{
    $lecturer = LecturerProfile::factory()->create();
    $course = Course::factory()->approved()->create(['lecturer_profile_id' => $lecturer->id]);
    $session = CourseSession::factory()->held()->create(['course_id' => $course->id]);

    return [$course, $session];
}

it('shows a student their own attendance grouped by course', function () {
    [$course, $session] = c2AttendCourseWithSession();
    $student = c2AttendStudent($course);

    AttendanceRecord::factory()->create([
        'course_session_id' => $session->id,
        'student_profile_id' => $student->id,
        'status' => AttendanceStatus::Present->value,
    ]);

    $this->actingAs($student->user)
        ->get(route('student.attendance.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('student/Attendance')
            ->has('courses', 1)
            ->where('courses.0.id', $course->id)
            ->has('courses.0.sessions', 1)
            ->where('courses.0.sessions.0.attendance_status', AttendanceStatus::Present->value));
});

it('scopes attendance to the authenticated student, never another student mark', function () {
    [$course, $session] = c2AttendCourseWithSession();
    $me = c2AttendStudent($course);
    $other = c2AttendStudent($course);

    AttendanceRecord::factory()->create([
        'course_session_id' => $session->id,
        'student_profile_id' => $me->id,
        'status' => AttendanceStatus::Present->value,
    ]);
    AttendanceRecord::factory()->create([
        'course_session_id' => $session->id,
        'student_profile_id' => $other->id,
        'status' => AttendanceStatus::Absent->value,
    ]);

    // The viewer sees only their own Present mark, not the other student's Absent.
    $this->actingAs($me->user)
        ->get(route('student.attendance.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('courses.0.sessions.0.attendance_status', AttendanceStatus::Present->value));
});

it('shows an unmarked session with a null attendance status', function () {
    [$course] = c2AttendCourseWithSession();
    $student = c2AttendStudent($course);

    $this->actingAs($student->user)
        ->get(route('student.attendance.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('courses', 1)
            ->where('courses.0.sessions.0.attendance_status', null));
});

it('forbids a non-student role from the student attendance index', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Lecturer);

    $this->actingAs($user)
        ->get(route('student.attendance.index'))
        ->assertForbidden();
});
