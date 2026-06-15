<?php

use App\Enums\AuditAction;
use App\Enums\DisputeStatus;
use App\Enums\ResultStatus;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseResult;
use App\Models\LecturerProfile;
use App\Models\ResultDispute;
use App\Models\StudentProfile;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
});

/**
 * Builds an approved course with an owning lecturer.
 */
function c4DispApprovedCourse(): Course
{
    $lecturer = LecturerProfile::factory()->create();

    return Course::factory()->approved()->create(['lecturer_profile_id' => $lecturer->id]);
}

/**
 * Creates a student user bound to an Active StudentProfile inside the cohort.
 */
function c4DispCohortStudent(Course $course): StudentProfile
{
    $user = userWithRole(RoleName::Student);

    return StudentProfile::factory()->create([
        'user_id' => $user->id,
        'program_offering_id' => $course->program_offering_id,
        'level' => $course->level,
        'academic_year' => $course->academic_year,
        'status' => StudentStatus::Active->value,
    ]);
}

/**
 * Creates a CourseResult with the given status for the given student.
 */
function c4DispResult(Course $course, StudentProfile $student, ResultStatus $status): CourseResult
{
    return CourseResult::factory()->create([
        'course_id' => $course->id,
        'student_profile_id' => $student->id,
        'ca_score' => 70,
        'exam_score' => 80,
        'status' => $status->value,
        'published_at' => $status === ResultStatus::Published ? now() : null,
    ]);
}

it('lets a student raise a dispute on their published result', function () {
    $course = c4DispApprovedCourse();
    $student = c4DispCohortStudent($course);
    $result = c4DispResult($course, $student, ResultStatus::Published);

    $response = $this->actingAs($student->user)
        ->post(route('student.results.dispute', $result), [
            'reason' => 'My exam mark does not match the script I submitted.',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $dispute = ResultDispute::query()
        ->where('course_result_id', $result->id)
        ->sole();

    expect($dispute->status)->toBe(DisputeStatus::Open)
        ->and($dispute->student_profile_id)->toBe($student->id);

    expect(AuditLog::query()
        ->where('subject_type', $dispute->getMorphClass())
        ->where('subject_id', $dispute->id)
        ->where('action', AuditAction::DisputeRaised->value)
        ->exists())->toBeTrue();
});

it('forbids disputing a draft result', function () {
    $course = c4DispApprovedCourse();
    $student = c4DispCohortStudent($course);
    $result = c4DispResult($course, $student, ResultStatus::Draft);

    $this->actingAs($student->user)
        ->post(route('student.results.dispute', $result), [
            'reason' => 'Trying to dispute a draft.',
        ])
        ->assertForbidden();

    expect(ResultDispute::query()->where('course_result_id', $result->id)->count())->toBe(0);
});

it('forbids disputing another students result', function () {
    $course = c4DispApprovedCourse();
    $owner = c4DispCohortStudent($course);
    $intruder = c4DispCohortStudent($course);
    $result = c4DispResult($course, $owner, ResultStatus::Published);

    $this->actingAs($intruder->user)
        ->post(route('student.results.dispute', $result), [
            'reason' => 'Not my result.',
        ])
        ->assertForbidden();

    expect(ResultDispute::query()->where('course_result_id', $result->id)->count())->toBe(0);
});

it('rejects a second dispute while an open one exists', function () {
    $course = c4DispApprovedCourse();
    $student = c4DispCohortStudent($course);
    $result = c4DispResult($course, $student, ResultStatus::Published);

    ResultDispute::factory()->create([
        'course_result_id' => $result->id,
        'student_profile_id' => $student->id,
        'reason' => 'First dispute.',
        'status' => DisputeStatus::Open->value,
    ]);

    $this->actingAs($student->user)
        ->post(route('student.results.dispute', $result), [
            'reason' => 'Second dispute while first is open.',
        ])
        ->assertSessionHasErrors('reason');

    expect(ResultDispute::query()->where('course_result_id', $result->id)->count())->toBe(1);
});

it('allows a new dispute after the previous one was resolved', function () {
    $course = c4DispApprovedCourse();
    $student = c4DispCohortStudent($course);
    $result = c4DispResult($course, $student, ResultStatus::Published);

    ResultDispute::factory()->create([
        'course_result_id' => $result->id,
        'student_profile_id' => $student->id,
        'reason' => 'First dispute.',
        'status' => DisputeStatus::Resolved->value,
        'reviewed_at' => now(),
    ]);

    $this->actingAs($student->user)
        ->post(route('student.results.dispute', $result), [
            'reason' => 'New concern after the first was resolved.',
        ])
        ->assertSessionHasNoErrors();

    expect(ResultDispute::query()->where('course_result_id', $result->id)->count())->toBe(2)
        ->and(ResultDispute::query()
            ->where('course_result_id', $result->id)
            ->where('status', DisputeStatus::Open->value)
            ->count())->toBe(1);
});
