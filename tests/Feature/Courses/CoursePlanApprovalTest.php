<?php

use App\Enums\AuditAction;
use App\Enums\CoursePlanStatus;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\LecturerProfile;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
});

/**
 * Creates a lecturer user bound to a LecturerProfile and returns the profile.
 */
function c2PlanLecturer(): LecturerProfile
{
    $user = userWithRole(RoleName::Lecturer);

    return LecturerProfile::factory()->create(['user_id' => $user->id]);
}

it('lets the assigned lecturer edit the description and submit the plan', function () {
    $lecturer = c2PlanLecturer();
    $course = Course::factory()->assigned()->create(['lecturer_profile_id' => $lecturer->id]);

    $this->actingAs($lecturer->user)->patch(route('lecturer.courses.update', $course), [
        'description' => 'Foundations of programming, data, and algorithms.',
    ])->assertSessionHasNoErrors();

    expect($course->fresh()->description)->toBe('Foundations of programming, data, and algorithms.');

    $response = $this->actingAs($lecturer->user)->post(route('lecturer.courses.submit', $course));

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $fresh = $course->fresh();
    expect($fresh->plan_status)->toBe(CoursePlanStatus::Submitted)
        ->and($fresh->plan_submitted_at)->not->toBeNull();

    expect(AuditLog::query()
        ->where('subject_type', $course->getMorphClass())
        ->where('subject_id', $course->id)
        ->where('action', AuditAction::CoursePlanSubmitted->value)
        ->exists())->toBeTrue();
});

it('forbids a non-assigned lecturer from editing or submitting a plan', function () {
    $assigned = c2PlanLecturer();
    $intruder = c2PlanLecturer();
    $course = Course::factory()->assigned()->create(['lecturer_profile_id' => $assigned->id]);

    $this->actingAs($intruder->user)
        ->patch(route('lecturer.courses.update', $course), ['description' => 'Hijacked plan.'])
        ->assertForbidden();

    $this->actingAs($intruder->user)
        ->post(route('lecturer.courses.submit', $course))
        ->assertForbidden();

    $fresh = $course->fresh();
    expect($fresh->description)->not->toBe('Hijacked plan.')
        ->and($fresh->plan_status)->toBe(CoursePlanStatus::Draft);
});

it('lets an SAO approve a submitted course plan', function () {
    $sao = userWithRole(RoleName::Sao);
    $course = Course::factory()->submitted()->create();

    $response = $this->actingAs($sao)->post(route('sao.courses.approve', $course));

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $fresh = $course->fresh();
    expect($fresh->plan_status)->toBe(CoursePlanStatus::Approved)
        ->and($fresh->plan_reviewed_at)->not->toBeNull()
        ->and($fresh->plan_reviewed_by)->toBe($sao->id);

    expect(AuditLog::query()
        ->where('subject_type', $course->getMorphClass())
        ->where('subject_id', $course->id)
        ->where('action', AuditAction::CoursePlanApproved->value)
        ->exists())->toBeTrue();
});

it('lets an SAO reject a submitted plan with notes', function () {
    $sao = userWithRole(RoleName::Sao);
    $course = Course::factory()->submitted()->create();

    $response = $this->actingAs($sao)->post(route('sao.courses.reject', $course), [
        'notes' => 'Outline is missing assessment weighting.',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $fresh = $course->fresh();
    expect($fresh->plan_status)->toBe(CoursePlanStatus::Rejected)
        ->and($fresh->plan_review_notes)->toBe('Outline is missing assessment weighting.')
        ->and($fresh->plan_reviewed_by)->toBe($sao->id);

    expect(AuditLog::query()
        ->where('subject_type', $course->getMorphClass())
        ->where('subject_id', $course->id)
        ->where('action', AuditAction::CoursePlanRejected->value)
        ->exists())->toBeTrue();
});

it('requires notes to reject a plan', function () {
    $sao = userWithRole(RoleName::Sao);
    $course = Course::factory()->submitted()->create();

    $this->actingAs($sao)
        ->post(route('sao.courses.reject', $course), [])
        ->assertSessionHasErrors('notes');

    expect($course->fresh()->plan_status)->toBe(CoursePlanStatus::Submitted);
});

it('re-guards approval of a course that is not in the Submitted state', function () {
    $sao = userWithRole(RoleName::Sao);
    $course = Course::factory()->draft()->create();

    $this->actingAs($sao)
        ->post(route('sao.courses.approve', $course))
        ->assertSessionHasErrors('plan_status');

    expect($course->fresh()->plan_status)->toBe(CoursePlanStatus::Draft);

    expect(AuditLog::query()
        ->where('subject_id', $course->id)
        ->where('action', AuditAction::CoursePlanApproved->value)
        ->exists())->toBeFalse();
});

it('forbids non-SAO roles from approving a plan', function (RoleName $role) {
    $user = userWithRole($role);
    $course = Course::factory()->submitted()->create();

    $this->actingAs($user)
        ->post(route('sao.courses.approve', $course))
        ->assertForbidden();

    expect($course->fresh()->plan_status)->toBe(CoursePlanStatus::Submitted);
})->with([
    'student' => [RoleName::Student],
    'lecturer' => [RoleName::Lecturer],
    'accountant' => [RoleName::Accountant],
    'applicant' => [RoleName::Applicant],
]);
