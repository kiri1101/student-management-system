<?php

use App\Enums\AttendanceStatus;
use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\LecturerProfile;
use App\Models\StudentProfile;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
});

/**
 * Creates a lecturer user bound to a LecturerProfile and returns the profile.
 */
function c2MarkLecturer(): LecturerProfile
{
    $user = userWithRole(RoleName::Lecturer);

    return LecturerProfile::factory()->create(['user_id' => $user->id]);
}

/**
 * Builds an Approved course owned by the given lecturer plus a scheduled session.
 *
 * @return array{0:Course,1:CourseSession}
 */
function c2MarkCourseWithSession(LecturerProfile $lecturer): array
{
    $course = Course::factory()->approved()->create(['lecturer_profile_id' => $lecturer->id]);
    $session = CourseSession::factory()->scheduled()->create(['course_id' => $course->id]);

    return [$course, $session];
}

/**
 * Creates a StudentProfile that falls inside the course's implicit cohort.
 */
function c2MarkCohortStudent(Course $course): StudentProfile
{
    return StudentProfile::factory()->create([
        'program_offering_id' => $course->program_offering_id,
        'level' => $course->level,
        'academic_year' => $course->academic_year,
    ]);
}

it('marks attendance for each cohort student with status, marked_by and an audit', function () {
    $lecturer = c2MarkLecturer();
    [$course, $session] = c2MarkCourseWithSession($lecturer);
    $present = c2MarkCohortStudent($course);
    $absent = c2MarkCohortStudent($course);

    $response = $this->actingAs($lecturer->user)
        ->post(route('lecturer.courses.sessions.markAttendance', [$course, $session]), [
            'records' => [
                ['student_profile_id' => $present->id, 'status' => AttendanceStatus::Present->value],
                ['student_profile_id' => $absent->id, 'status' => AttendanceStatus::Absent->value],
            ],
        ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    expect(AttendanceRecord::query()->where('course_session_id', $session->id)->count())->toBe(2);

    $presentRecord = AttendanceRecord::query()
        ->where('course_session_id', $session->id)
        ->where('student_profile_id', $present->id)
        ->sole();
    expect($presentRecord->status)->toBe(AttendanceStatus::Present)
        ->and($presentRecord->marked_by)->toBe($lecturer->user->id)
        ->and($presentRecord->marked_at)->not->toBeNull();

    expect(AttendanceRecord::query()
        ->where('course_session_id', $session->id)
        ->where('student_profile_id', $absent->id)
        ->value('status'))->toBe(AttendanceStatus::Absent);

    expect(AuditLog::query()
        ->where('subject_type', $session->getMorphClass())
        ->where('subject_id', $session->id)
        ->where('action', AuditAction::AttendanceMarked->value)
        ->exists())->toBeTrue();
});

it('updates an existing record on re-mark rather than duplicating it', function () {
    $lecturer = c2MarkLecturer();
    [$course, $session] = c2MarkCourseWithSession($lecturer);
    $student = c2MarkCohortStudent($course);

    $this->actingAs($lecturer->user)
        ->post(route('lecturer.courses.sessions.markAttendance', [$course, $session]), [
            'records' => [
                ['student_profile_id' => $student->id, 'status' => AttendanceStatus::Absent->value],
            ],
        ])->assertSessionHasNoErrors();

    $this->actingAs($lecturer->user)
        ->post(route('lecturer.courses.sessions.markAttendance', [$course, $session]), [
            'records' => [
                ['student_profile_id' => $student->id, 'status' => AttendanceStatus::Present->value],
            ],
        ])->assertSessionHasNoErrors();

    expect(AttendanceRecord::query()
        ->where('course_session_id', $session->id)
        ->where('student_profile_id', $student->id)
        ->count())->toBe(1);

    expect(AttendanceRecord::query()
        ->where('course_session_id', $session->id)
        ->where('student_profile_id', $student->id)
        ->value('status'))->toBe(AttendanceStatus::Present);
});

it('ignores students who are not in the course cohort', function () {
    $lecturer = c2MarkLecturer();
    [$course, $session] = c2MarkCourseWithSession($lecturer);
    $cohort = c2MarkCohortStudent($course);

    // Outside the cohort: a different level than the course.
    $outsider = StudentProfile::factory()->create([
        'program_offering_id' => $course->program_offering_id,
        'level' => $course->level + 100,
        'academic_year' => $course->academic_year,
    ]);

    $this->actingAs($lecturer->user)
        ->post(route('lecturer.courses.sessions.markAttendance', [$course, $session]), [
            'records' => [
                ['student_profile_id' => $cohort->id, 'status' => AttendanceStatus::Present->value],
                ['student_profile_id' => $outsider->id, 'status' => AttendanceStatus::Present->value],
            ],
        ])->assertSessionHasNoErrors();

    expect(AttendanceRecord::query()
        ->where('course_session_id', $session->id)
        ->where('student_profile_id', $outsider->id)
        ->exists())->toBeFalse();

    expect(AttendanceRecord::query()->where('course_session_id', $session->id)->count())->toBe(1);
});

it('forbids a non-assigned lecturer from marking attendance', function () {
    $owner = c2MarkLecturer();
    $intruder = c2MarkLecturer();
    [$course, $session] = c2MarkCourseWithSession($owner);
    $student = c2MarkCohortStudent($course);

    $this->actingAs($intruder->user)
        ->post(route('lecturer.courses.sessions.markAttendance', [$course, $session]), [
            'records' => [
                ['student_profile_id' => $student->id, 'status' => AttendanceStatus::Present->value],
            ],
        ])->assertForbidden();

    expect(AttendanceRecord::query()->where('course_session_id', $session->id)->count())->toBe(0);
});

it('forbids non-lecturer roles from marking attendance', function (RoleName $role) {
    $owner = c2MarkLecturer();
    [$course, $session] = c2MarkCourseWithSession($owner);
    $student = c2MarkCohortStudent($course);
    $user = userWithRole($role);

    $this->actingAs($user)
        ->post(route('lecturer.courses.sessions.markAttendance', [$course, $session]), [
            'records' => [
                ['student_profile_id' => $student->id, 'status' => AttendanceStatus::Present->value],
            ],
        ])->assertForbidden();

    expect(AttendanceRecord::query()->where('course_session_id', $session->id)->count())->toBe(0);
})->with([
    'student' => [RoleName::Student],
    'accountant' => [RoleName::Accountant],
    'applicant' => [RoleName::Applicant],
]);

it('re-guards marking attendance on a course that is not approved', function () {
    $lecturer = c2MarkLecturer();
    $course = Course::factory()->draft()->create(['lecturer_profile_id' => $lecturer->id]);
    $session = CourseSession::factory()->scheduled()->create(['course_id' => $course->id]);
    $student = StudentProfile::factory()->create([
        'program_offering_id' => $course->program_offering_id,
        'level' => $course->level,
        'academic_year' => $course->academic_year,
    ]);

    $this->actingAs($lecturer->user)
        ->post(route('lecturer.courses.sessions.markAttendance', [$course, $session]), [
            'records' => [
                ['student_profile_id' => $student->id, 'status' => AttendanceStatus::Present->value],
            ],
        ]);

    expect(AttendanceRecord::query()->where('course_session_id', $session->id)->count())->toBe(0);
});
