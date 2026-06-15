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
 * Creates a lecturer user bound to a LecturerProfile and returns the profile.
 */
function c4RecLecturer(): LecturerProfile
{
    $user = userWithRole(RoleName::Lecturer);

    return LecturerProfile::factory()->create(['user_id' => $user->id]);
}

/**
 * Builds an approved course owned by the given lecturer.
 */
function c4RecApprovedCourse(LecturerProfile $lecturer): Course
{
    return Course::factory()->approved()->create(['lecturer_profile_id' => $lecturer->id]);
}

/**
 * Creates an Active StudentProfile inside the given course's cohort.
 */
function c4RecCohortStudent(Course $course): StudentProfile
{
    return StudentProfile::factory()->create([
        'program_offering_id' => $course->program_offering_id,
        'level' => $course->level,
        'academic_year' => $course->academic_year,
        'status' => StudentStatus::Active->value,
    ]);
}

it('lets the assigned lecturer record ca and exam marks for cohort students', function () {
    $lecturer = c4RecLecturer();
    $course = c4RecApprovedCourse($lecturer);
    $first = c4RecCohortStudent($course);
    $second = c4RecCohortStudent($course);

    $response = $this->actingAs($lecturer->user)
        ->post(route('lecturer.courses.results.store', $course), [
            'results' => [
                ['student_profile_id' => $first->id, 'ca_score' => 80, 'exam_score' => 90],
                ['student_profile_id' => $second->id, 'ca_score' => 50, 'exam_score' => 50],
            ],
        ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $firstResult = CourseResult::query()
        ->where('course_id', $course->id)
        ->where('student_profile_id', $first->id)
        ->sole();
    $secondResult = CourseResult::query()
        ->where('course_id', $course->id)
        ->where('student_profile_id', $second->id)
        ->sole();

    expect($firstResult->status)->toBe(ResultStatus::Draft)
        ->and($firstResult->ca_score)->toBe(80)
        ->and($firstResult->exam_score)->toBe(90)
        // round(0.3*80 + 0.7*90) = round(24 + 63) = 87 => A
        ->and($firstResult->final_score)->toBe(87)
        ->and($firstResult->grade)->toBe('A')
        ->and($secondResult->status)->toBe(ResultStatus::Draft)
        // round(0.3*50 + 0.7*50) = 50 => D
        ->and($secondResult->final_score)->toBe(50)
        ->and($secondResult->grade)->toBe('D');

    expect(AuditLog::query()
        ->where('subject_type', $course->getMorphClass())
        ->where('subject_id', $course->id)
        ->where('action', AuditAction::ResultRecorded->value)
        ->exists())->toBeTrue();
});

it('rejects a ca_score above the allowed maximum', function () {
    $lecturer = c4RecLecturer();
    $course = c4RecApprovedCourse($lecturer);
    $student = c4RecCohortStudent($course);

    $this->actingAs($lecturer->user)
        ->post(route('lecturer.courses.results.store', $course), [
            'results' => [
                ['student_profile_id' => $student->id, 'ca_score' => 150, 'exam_score' => 40],
            ],
        ])
        ->assertSessionHasErrors('results.0.ca_score');

    expect(CourseResult::query()->where('course_id', $course->id)->count())->toBe(0);
});

it('forbids a non-assigned lecturer from recording results', function () {
    $owner = c4RecLecturer();
    $intruder = c4RecLecturer();
    $course = c4RecApprovedCourse($owner);
    $student = c4RecCohortStudent($course);

    $this->actingAs($intruder->user)
        ->post(route('lecturer.courses.results.store', $course), [
            'results' => [
                ['student_profile_id' => $student->id, 'ca_score' => 70, 'exam_score' => 70],
            ],
        ])
        ->assertForbidden();

    expect(CourseResult::query()->where('course_id', $course->id)->count())->toBe(0);
});

it('forbids recording results on a non-approved (draft) course', function () {
    $lecturer = c4RecLecturer();
    $course = Course::factory()->draft()->create(['lecturer_profile_id' => $lecturer->id]);
    $student = c4RecCohortStudent($course);

    $this->actingAs($lecturer->user)
        ->post(route('lecturer.courses.results.store', $course), [
            'results' => [
                ['student_profile_id' => $student->id, 'ca_score' => 70, 'exam_score' => 70],
            ],
        ])
        ->assertForbidden();

    expect(CourseResult::query()->where('course_id', $course->id)->count())->toBe(0);
});

it('ignores rows for students outside the cohort while writing cohort rows', function () {
    $lecturer = c4RecLecturer();
    $course = c4RecApprovedCourse($lecturer);
    $cohortStudent = c4RecCohortStudent($course);

    // Same program/year but a different level => outside the cohort.
    $outsider = StudentProfile::factory()->create([
        'program_offering_id' => $course->program_offering_id,
        'level' => $course->level + 100,
        'academic_year' => $course->academic_year,
        'status' => StudentStatus::Active->value,
    ]);

    $this->actingAs($lecturer->user)
        ->post(route('lecturer.courses.results.store', $course), [
            'results' => [
                ['student_profile_id' => $cohortStudent->id, 'ca_score' => 60, 'exam_score' => 60],
                ['student_profile_id' => $outsider->id, 'ca_score' => 90, 'exam_score' => 90],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect(CourseResult::query()
        ->where('course_id', $course->id)
        ->where('student_profile_id', $cohortStudent->id)
        ->exists())->toBeTrue()
        ->and(CourseResult::query()
            ->where('course_id', $course->id)
            ->where('student_profile_id', $outsider->id)
            ->exists())->toBeFalse();
});

it('does not overwrite a published result row on a subsequent store', function () {
    $lecturer = c4RecLecturer();
    $course = c4RecApprovedCourse($lecturer);
    $student = c4RecCohortStudent($course);

    $published = CourseResult::factory()->create([
        'course_id' => $course->id,
        'student_profile_id' => $student->id,
        'ca_score' => 40,
        'exam_score' => 40,
        'status' => ResultStatus::Published->value,
        'published_at' => now(),
        'published_by' => $lecturer->user->id,
    ]);

    $this->actingAs($lecturer->user)
        ->post(route('lecturer.courses.results.store', $course), [
            'results' => [
                ['student_profile_id' => $student->id, 'ca_score' => 95, 'exam_score' => 95],
            ],
        ])
        ->assertSessionHasNoErrors();

    $fresh = $published->fresh();
    expect($fresh->ca_score)->toBe(40)
        ->and($fresh->exam_score)->toBe(40)
        ->and($fresh->status)->toBe(ResultStatus::Published);
});
