<?php

use App\Enums\AuditAction;
use App\Enums\ResultStatus;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseResult;
use App\Models\LecturerProfile;
use App\Models\StudentProfile;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
});

/**
 * Builds an approved course with an owning lecturer.
 */
function c4PubApprovedCourse(): Course
{
    $lecturer = LecturerProfile::factory()->create();

    return Course::factory()->approved()->create(['lecturer_profile_id' => $lecturer->id]);
}

/**
 * Creates an Active StudentProfile inside the given course's cohort.
 */
function c4PubCohortStudent(Course $course): StudentProfile
{
    return StudentProfile::factory()->create([
        'program_offering_id' => $course->program_offering_id,
        'level' => $course->level,
        'academic_year' => $course->academic_year,
        'status' => StudentStatus::Active->value,
    ]);
}

/**
 * Creates a Draft CourseResult with the given marks for a cohort student.
 */
function c4PubDraftResult(Course $course, ?int $ca, ?int $exam): CourseResult
{
    $student = c4PubCohortStudent($course);

    return CourseResult::factory()->create([
        'course_id' => $course->id,
        'student_profile_id' => $student->id,
        'ca_score' => $ca,
        'exam_score' => $exam,
        'status' => ResultStatus::Draft->value,
        'published_at' => null,
        'published_by' => null,
    ]);
}

it('publishes only fully-scored draft results and leaves partial drafts as draft', function () {
    $sao = userWithRole(RoleName::Sao);
    $course = c4PubApprovedCourse();

    $scored = c4PubDraftResult($course, 70, 80);
    $partial = c4PubDraftResult($course, 70, null);

    $response = $this->actingAs($sao)
        ->post(route('sao.courses.publishResults', $course));

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $freshScored = $scored->fresh();
    expect($freshScored->status)->toBe(ResultStatus::Published)
        ->and($freshScored->published_at)->not->toBeNull()
        ->and($freshScored->published_by)->toBe($sao->id);

    $freshPartial = $partial->fresh();
    expect($freshPartial->status)->toBe(ResultStatus::Draft)
        ->and($freshPartial->published_at)->toBeNull();

    expect(AuditLog::query()
        ->where('subject_type', $course->getMorphClass())
        ->where('subject_id', $course->id)
        ->where('action', AuditAction::ResultsPublished->value)
        ->exists())->toBeTrue();
});

it('lets an admin publish results too', function () {
    $admin = userWithRole(RoleName::Admin);
    $course = c4PubApprovedCourse();
    $scored = c4PubDraftResult($course, 60, 60);

    $this->actingAs($admin)
        ->post(route('sao.courses.publishResults', $course))
        ->assertSessionHasNoErrors();

    expect($scored->fresh()->status)->toBe(ResultStatus::Published);
});

it('errors when there are no fully-scored drafts to publish', function () {
    $sao = userWithRole(RoleName::Sao);
    $course = c4PubApprovedCourse();
    $partial = c4PubDraftResult($course, null, 80);

    $this->actingAs($sao)
        ->post(route('sao.courses.publishResults', $course))
        ->assertSessionHasErrors('results');

    expect($partial->fresh()->status)->toBe(ResultStatus::Draft);
});

it('forbids a lecturer from publishing results', function () {
    $lecturerUser = userWithRole(RoleName::Lecturer);
    $course = c4PubApprovedCourse();
    c4PubDraftResult($course, 70, 70);

    $this->actingAs($lecturerUser)
        ->post(route('sao.courses.publishResults', $course))
        ->assertForbidden();
});

it('forbids a student from publishing results', function () {
    $studentUser = userWithRole(RoleName::Student);
    $course = c4PubApprovedCourse();
    c4PubDraftResult($course, 70, 70);

    $this->actingAs($studentUser)
        ->post(route('sao.courses.publishResults', $course))
        ->assertForbidden();
});
