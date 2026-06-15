<?php

use App\Enums\AssignmentSubmissionStatus;
use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\LecturerProfile;
use App\Models\StudentProfile;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
});

/**
 * Creates a lecturer user bound to a LecturerProfile and returns the profile.
 */
function c3GradeLecturer(): LecturerProfile
{
    $user = userWithRole(RoleName::Lecturer);

    return LecturerProfile::factory()->create(['user_id' => $user->id]);
}

/**
 * Builds an approved course owned by the given lecturer plus an assignment.
 *
 * @return array{0:Course,1:Assignment}
 */
function c3GradeCourseWithAssignment(LecturerProfile $lecturer, int $maxScore = 20): array
{
    $course = Course::factory()->approved()->create(['lecturer_profile_id' => $lecturer->id]);
    $assignment = Assignment::factory()->create([
        'course_id' => $course->id,
        'created_by' => $lecturer->user->id,
        'max_score' => $maxScore,
    ]);

    return [$course, $assignment];
}

/**
 * Creates a submitted (ungraded) submission from a cohort student on the assignment.
 */
function c3GradeSubmission(Course $course, Assignment $assignment): AssignmentSubmission
{
    $student = StudentProfile::factory()->create([
        'program_offering_id' => $course->program_offering_id,
        'level' => $course->level,
        'academic_year' => $course->academic_year,
    ]);

    return AssignmentSubmission::factory()->create([
        'assignment_id' => $assignment->id,
        'student_profile_id' => $student->id,
    ]);
}

it('lets the assigned lecturer grade a submission within the max score', function () {
    $lecturer = c3GradeLecturer();
    [$course, $assignment] = c3GradeCourseWithAssignment($lecturer, 20);
    $submission = c3GradeSubmission($course, $assignment);

    $response = $this->actingAs($lecturer->user)
        ->post(route('lecturer.courses.assignments.grade', [$course, $assignment, $submission]), [
            'score' => 18,
            'feedback' => 'Good work, minor edge cases missed.',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $fresh = $submission->fresh();
    expect($fresh->score)->toBe(18)
        ->and($fresh->feedback)->toBe('Good work, minor edge cases missed.')
        ->and($fresh->graded_by)->toBe($lecturer->user->id)
        ->and($fresh->graded_at)->not->toBeNull()
        ->and($fresh->status)->toBe(AssignmentSubmissionStatus::Graded);

    expect(AuditLog::query()
        ->where('subject_type', $submission->getMorphClass())
        ->where('subject_id', $submission->id)
        ->where('action', AuditAction::AssignmentGraded->value)
        ->exists())->toBeTrue();
});

it('rejects a score above the assignment max score', function () {
    $lecturer = c3GradeLecturer();
    [$course, $assignment] = c3GradeCourseWithAssignment($lecturer, 20);
    $submission = c3GradeSubmission($course, $assignment);

    $this->actingAs($lecturer->user)
        ->post(route('lecturer.courses.assignments.grade', [$course, $assignment, $submission]), [
            'score' => 25,
            'feedback' => 'Too high.',
        ])
        ->assertSessionHasErrors('score');

    $fresh = $submission->fresh();
    expect($fresh->status)->toBe(AssignmentSubmissionStatus::Submitted)
        ->and($fresh->graded_at)->toBeNull();
});

it('forbids a non-assigned lecturer from grading a submission', function () {
    $owner = c3GradeLecturer();
    $intruder = c3GradeLecturer();
    [$course, $assignment] = c3GradeCourseWithAssignment($owner, 20);
    $submission = c3GradeSubmission($course, $assignment);

    $this->actingAs($intruder->user)
        ->post(route('lecturer.courses.assignments.grade', [$course, $assignment, $submission]), [
            'score' => 15,
            'feedback' => 'Hijack.',
        ])
        ->assertForbidden();

    expect($submission->fresh()->status)->toBe(AssignmentSubmissionStatus::Submitted);
});

it('returns 404 when the submission does not belong to the route assignment', function () {
    $lecturer = c3GradeLecturer();
    [$course, $assignment] = c3GradeCourseWithAssignment($lecturer, 20);

    // A second assignment on the same course; the submission belongs to it, not $assignment.
    $otherAssignment = Assignment::factory()->create([
        'course_id' => $course->id,
        'created_by' => $lecturer->user->id,
        'max_score' => 20,
    ]);
    $submission = c3GradeSubmission($course, $otherAssignment);

    $this->actingAs($lecturer->user)
        ->post(route('lecturer.courses.assignments.grade', [$course, $assignment, $submission]), [
            'score' => 15,
            'feedback' => 'Mismatch.',
        ])
        ->assertNotFound();

    expect($submission->fresh()->status)->toBe(AssignmentSubmissionStatus::Submitted);
});
