<?php

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Enums\SessionChangeType;
use App\Enums\SessionStatus;
use App\Enums\StudentStatus;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\LecturerProfile;
use App\Models\StudentProfile;
use App\Notifications\CourseSessionChangedNotification;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    Notification::fake();
});

/**
 * Creates a lecturer user bound to a LecturerProfile and returns the profile.
 */
function courseNotifLecturer(): LecturerProfile
{
    $user = userWithRole(RoleName::Lecturer);

    return LecturerProfile::factory()->create(['user_id' => $user->id]);
}

/**
 * Builds an Approved course owned by the given lecturer.
 */
function courseNotifApprovedCourse(LecturerProfile $lecturer): Course
{
    return Course::factory()->approved()->create(['lecturer_profile_id' => $lecturer->id]);
}

/**
 * Builds a future, Scheduled session on the given course (qualifies for notifications).
 */
function courseNotifFutureSession(Course $course): CourseSession
{
    return CourseSession::factory()->scheduled()->create([
        'course_id' => $course->id,
        'scheduled_for' => now()->addWeek(),
    ]);
}

/**
 * Creates an active StudentProfile inside the course's implicit cohort.
 */
function courseNotifCohortStudent(Course $course): StudentProfile
{
    return StudentProfile::factory()->create([
        'program_offering_id' => $course->program_offering_id,
        'level' => $course->level,
        'academic_year' => $course->academic_year,
        'status' => StudentStatus::Active->value,
    ]);
}

it('notifies each active cohort student on mail and database when a future session is cancelled', function () {
    $lecturer = courseNotifLecturer();
    $course = courseNotifApprovedCourse($lecturer);
    $session = courseNotifFutureSession($course);

    $studentA = courseNotifCohortStudent($course);
    $studentB = courseNotifCohortStudent($course);

    $response = $this->actingAs($lecturer->user)
        ->delete(route('lecturer.courses.sessions.destroy', [$course, $session]), [
            'reason' => 'Lecturer travelling for a conference.',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    foreach ([$studentA, $studentB] as $student) {
        Notification::assertSentTo(
            $student->user,
            CourseSessionChangedNotification::class,
            function (CourseSessionChangedNotification $notification, array $channels): bool {
                return in_array('mail', $channels, true)
                    && in_array('database', $channels, true)
                    && $notification->type === SessionChangeType::Cancelled;
            },
        );
    }

    $fresh = $session->fresh();
    expect($fresh->status)->toBe(SessionStatus::Cancelled)
        ->and($fresh->cancellation_reason)->toBe('Lecturer travelling for a conference.');

    expect(AuditLog::query()
        ->where('subject_type', $session->getMorphClass())
        ->where('subject_id', $session->id)
        ->where('action', AuditAction::CourseSessionCancelled->value)
        ->exists())->toBeTrue();
});

it('does not notify students outside the cohort or inactive cohort-matching students', function () {
    $lecturer = courseNotifLecturer();
    $course = courseNotifApprovedCourse($lecturer);
    $session = courseNotifFutureSession($course);

    // Recipient who should be notified — proves the dispatch actually ran.
    courseNotifCohortStudent($course);

    // Different cohort: a different level than the course.
    $otherCohort = StudentProfile::factory()->create([
        'program_offering_id' => $course->program_offering_id,
        'level' => $course->level + 100,
        'academic_year' => $course->academic_year,
        'status' => StudentStatus::Active->value,
    ]);

    // Cohort-matching but inactive (graduated) — excluded by cohortStudents().
    $inactive = StudentProfile::factory()->create([
        'program_offering_id' => $course->program_offering_id,
        'level' => $course->level,
        'academic_year' => $course->academic_year,
        'status' => StudentStatus::Graduated->value,
    ]);

    $this->actingAs($lecturer->user)
        ->delete(route('lecturer.courses.sessions.destroy', [$course, $session]), [
            'reason' => 'Venue unavailable.',
        ])->assertSessionHasNoErrors();

    Notification::assertNotSentTo($otherCohort->user, CourseSessionChangedNotification::class);
    Notification::assertNotSentTo($inactive->user, CourseSessionChangedNotification::class);
});

it('notifies cohort with Rescheduled type and the previous time when scheduled_for changes', function () {
    $lecturer = courseNotifLecturer();
    $course = courseNotifApprovedCourse($lecturer);
    $session = courseNotifFutureSession($course);
    $student = courseNotifCohortStudent($course);

    $originalScheduledFor = $session->scheduled_for;
    $newScheduledFor = now()->addWeeks(2);

    $response = $this->actingAs($lecturer->user)
        ->patch(route('lecturer.courses.sessions.update', [$course, $session]), [
            'scheduled_for' => $newScheduledFor->toDateTimeString(),
            'topic' => $session->topic,
            'duration_minutes' => $session->duration_minutes,
        ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    Notification::assertSentTo(
        $student->user,
        CourseSessionChangedNotification::class,
        function (CourseSessionChangedNotification $notification, array $channels) use ($originalScheduledFor): bool {
            return in_array('mail', $channels, true)
                && in_array('database', $channels, true)
                && $notification->type === SessionChangeType::Rescheduled
                && $notification->previousScheduledFor !== null
                && $notification->previousScheduledFor->equalTo($originalScheduledFor);
        },
    );

    expect(AuditLog::query()
        ->where('subject_type', $session->getMorphClass())
        ->where('subject_id', $session->id)
        ->where('action', AuditAction::CourseSessionRescheduled->value)
        ->exists())->toBeTrue();
});

it('sends nothing when only the topic or duration is edited', function () {
    $lecturer = courseNotifLecturer();
    $course = courseNotifApprovedCourse($lecturer);
    $session = courseNotifFutureSession($course);
    courseNotifCohortStudent($course);

    $this->actingAs($lecturer->user)
        ->patch(route('lecturer.courses.sessions.update', [$course, $session]), [
            'scheduled_for' => $session->scheduled_for->toDateTimeString(),
            'topic' => 'Revised topic only',
            'duration_minutes' => 90,
        ])->assertSessionHasNoErrors();

    Notification::assertNothingSent();
});

it('sends nothing when destroying an already cancelled session', function () {
    $lecturer = courseNotifLecturer();
    $course = courseNotifApprovedCourse($lecturer);
    $session = CourseSession::factory()->cancelled()->create([
        'course_id' => $course->id,
        'scheduled_for' => now()->addWeek(),
    ]);
    courseNotifCohortStudent($course);

    $this->actingAs($lecturer->user)
        ->delete(route('lecturer.courses.sessions.destroy', [$course, $session]), [
            'reason' => 'Repeat cancel attempt.',
        ]);

    Notification::assertNothingSent();
});

it('sends nothing when the session being cancelled is already in the past', function () {
    $lecturer = courseNotifLecturer();
    $course = courseNotifApprovedCourse($lecturer);
    $session = CourseSession::factory()->scheduled()->create([
        'course_id' => $course->id,
        'scheduled_for' => now()->subWeek(),
    ]);
    courseNotifCohortStudent($course);

    $this->actingAs($lecturer->user)
        ->delete(route('lecturer.courses.sessions.destroy', [$course, $session]), [
            'reason' => 'Past session cancel.',
        ]);

    Notification::assertNothingSent();
});

it('forbids a non-owning lecturer from cancelling and notifies no one', function () {
    $owner = courseNotifLecturer();
    $intruder = courseNotifLecturer();
    $course = courseNotifApprovedCourse($owner);
    $session = courseNotifFutureSession($course);
    courseNotifCohortStudent($course);

    $this->actingAs($intruder->user)
        ->delete(route('lecturer.courses.sessions.destroy', [$course, $session]), [
            'reason' => 'Not my course.',
        ])->assertForbidden();

    expect($session->fresh()->status)->toBe(SessionStatus::Scheduled);
    Notification::assertNothingSent();
});

it('forbids a non-owning lecturer from rescheduling and notifies no one', function () {
    $owner = courseNotifLecturer();
    $intruder = courseNotifLecturer();
    $course = courseNotifApprovedCourse($owner);
    $session = courseNotifFutureSession($course);
    courseNotifCohortStudent($course);

    $this->actingAs($intruder->user)
        ->patch(route('lecturer.courses.sessions.update', [$course, $session]), [
            'scheduled_for' => now()->addWeeks(3)->toDateTimeString(),
            'topic' => $session->topic,
            'duration_minutes' => $session->duration_minutes,
        ])->assertForbidden();

    Notification::assertNothingSent();
});
