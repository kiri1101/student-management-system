<?php

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Enums\SessionStatus;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\LecturerProfile;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
});

/**
 * Creates a lecturer user bound to a LecturerProfile and returns the profile.
 */
function c2SessionLecturer(): LecturerProfile
{
    $user = userWithRole(RoleName::Lecturer);

    return LecturerProfile::factory()->create(['user_id' => $user->id]);
}

/**
 * Builds an Approved course owned by the given lecturer.
 */
function c2SessionApprovedCourse(LecturerProfile $lecturer): Course
{
    return Course::factory()->approved()->create(['lecturer_profile_id' => $lecturer->id]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function c2SessionPayload(array $overrides = []): array
{
    return [
        'scheduled_for' => now()->addDay()->toDateTimeString(),
        'topic' => 'Stacks and queues',
        'duration_minutes' => 120,
        ...$overrides,
    ];
}

it('lets the assigned lecturer schedule a session on an approved course', function () {
    $lecturer = c2SessionLecturer();
    $course = c2SessionApprovedCourse($lecturer);

    $response = $this->actingAs($lecturer->user)
        ->post(route('lecturer.courses.sessions.store', $course), c2SessionPayload());

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $session = CourseSession::query()->where('course_id', $course->id)->sole();
    expect($session->topic)->toBe('Stacks and queues')
        ->and($session->duration_minutes)->toBe(120)
        ->and($session->status)->toBe(SessionStatus::Scheduled);

    expect(AuditLog::query()
        ->where('subject_type', $session->getMorphClass())
        ->where('subject_id', $session->id)
        ->where('action', AuditAction::CourseSessionScheduled->value)
        ->exists())->toBeTrue();
});

it('rejects scheduling a session on a non-approved (draft) course', function () {
    $lecturer = c2SessionLecturer();
    $course = Course::factory()->draft()->create(['lecturer_profile_id' => $lecturer->id]);

    $this->actingAs($lecturer->user)
        ->post(route('lecturer.courses.sessions.store', $course), c2SessionPayload());

    expect(CourseSession::query()->where('course_id', $course->id)->count())->toBe(0);

    expect(AuditLog::query()
        ->where('action', AuditAction::CourseSessionScheduled->value)
        ->exists())->toBeFalse();
});

it('forbids a non-assigned lecturer from scheduling a session', function () {
    $owner = c2SessionLecturer();
    $intruder = c2SessionLecturer();
    $course = c2SessionApprovedCourse($owner);

    $this->actingAs($intruder->user)
        ->post(route('lecturer.courses.sessions.store', $course), c2SessionPayload())
        ->assertForbidden();

    expect(CourseSession::query()->where('course_id', $course->id)->count())->toBe(0);
});

it('cancels a session via DELETE, setting status Cancelled and writing an audit', function () {
    $lecturer = c2SessionLecturer();
    $course = c2SessionApprovedCourse($lecturer);
    $session = CourseSession::factory()->scheduled()->create(['course_id' => $course->id]);

    $response = $this->actingAs($lecturer->user)
        ->delete(route('lecturer.courses.sessions.destroy', [$course, $session]));

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    expect($session->fresh()->status)->toBe(SessionStatus::Cancelled);

    expect(AuditLog::query()
        ->where('subject_type', $session->getMorphClass())
        ->where('subject_id', $session->id)
        ->where('action', AuditAction::CourseSessionCancelled->value)
        ->exists())->toBeTrue();
});

it('forbids a non-assigned lecturer from cancelling a session', function () {
    $owner = c2SessionLecturer();
    $intruder = c2SessionLecturer();
    $course = c2SessionApprovedCourse($owner);
    $session = CourseSession::factory()->scheduled()->create(['course_id' => $course->id]);

    $this->actingAs($intruder->user)
        ->delete(route('lecturer.courses.sessions.destroy', [$course, $session]))
        ->assertForbidden();

    expect($session->fresh()->status)->toBe(SessionStatus::Scheduled);
});
