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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->withoutVite();
});

/**
 * Builds an approved course with an owning lecturer.
 *
 * @return array{0:Course,1:LecturerProfile}
 */
function c3SubCourse(): array
{
    $lecturer = LecturerProfile::factory()->create();
    $course = Course::factory()->approved()->create(['lecturer_profile_id' => $lecturer->id]);

    return [$course, $lecturer];
}

/**
 * Creates an assignment on the given course, due in the future by default.
 *
 * @param  array<string, mixed>  $overrides
 */
function c3SubAssignment(Course $course, array $overrides = []): Assignment
{
    return Assignment::factory()->create([
        'course_id' => $course->id,
        'due_at' => now()->addWeek(),
        ...$overrides,
    ]);
}

/**
 * Creates a student user bound to a StudentProfile inside the course cohort.
 */
function c3SubCohortStudent(Course $course): StudentProfile
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
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function c3SubPayload(array $overrides = []): array
{
    return [
        'file' => UploadedFile::fake()->create('submission.pdf', 100, 'application/pdf'),
        ...$overrides,
    ];
}

it('lets a cohort student submit a file before the due date', function () {
    Storage::fake();
    [$course] = c3SubCourse();
    $assignment = c3SubAssignment($course);
    $student = c3SubCohortStudent($course);

    $response = $this->actingAs($student->user)
        ->post(route('student.assignments.submit', $assignment), c3SubPayload());

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $submission = AssignmentSubmission::query()
        ->where('assignment_id', $assignment->id)
        ->where('student_profile_id', $student->id)
        ->sole();

    expect($submission->status)->toBe(AssignmentSubmissionStatus::Submitted)
        ->and($submission->is_late)->toBeFalse();

    Storage::assertExists($submission->file_path);

    expect(AuditLog::query()
        ->where('subject_type', $submission->getMorphClass())
        ->where('subject_id', $submission->id)
        ->where('action', AuditAction::AssignmentSubmitted->value)
        ->exists())->toBeTrue();
});

it('flags a submission made after the due date as late', function () {
    Storage::fake();
    [$course] = c3SubCourse();
    $assignment = c3SubAssignment($course, ['due_at' => now()->subDay()]);
    $student = c3SubCohortStudent($course);

    $this->actingAs($student->user)
        ->post(route('student.assignments.submit', $assignment), c3SubPayload())
        ->assertSessionHasNoErrors();

    $submission = AssignmentSubmission::query()
        ->where('assignment_id', $assignment->id)
        ->where('student_profile_id', $student->id)
        ->sole();

    expect($submission->is_late)->toBeTrue();
});

it('forbids a student outside the cohort from submitting', function () {
    Storage::fake();
    [$course] = c3SubCourse();
    $assignment = c3SubAssignment($course);

    $user = userWithRole(RoleName::Student);
    $outsider = StudentProfile::factory()->create([
        'user_id' => $user->id,
        'program_offering_id' => $course->program_offering_id,
        'level' => $course->level + 100,
        'academic_year' => $course->academic_year,
    ]);

    $this->actingAs($outsider->user)
        ->post(route('student.assignments.submit', $assignment), c3SubPayload())
        ->assertForbidden();

    expect(AssignmentSubmission::query()->where('assignment_id', $assignment->id)->count())->toBe(0);
});

it('replaces the file on resubmission and keeps a single row', function () {
    Storage::fake();
    [$course] = c3SubCourse();
    $assignment = c3SubAssignment($course);
    $student = c3SubCohortStudent($course);

    $this->actingAs($student->user)
        ->post(route('student.assignments.submit', $assignment), c3SubPayload([
            'file' => UploadedFile::fake()->create('first.pdf', 100, 'application/pdf'),
        ]))
        ->assertSessionHasNoErrors();

    $oldPath = AssignmentSubmission::query()
        ->where('assignment_id', $assignment->id)
        ->where('student_profile_id', $student->id)
        ->sole()
        ->file_path;

    Storage::assertExists($oldPath);

    $this->actingAs($student->user)
        ->post(route('student.assignments.submit', $assignment), c3SubPayload([
            'file' => UploadedFile::fake()->create('second.pdf', 100, 'application/pdf'),
        ]))
        ->assertSessionHasNoErrors();

    expect(AssignmentSubmission::query()
        ->where('assignment_id', $assignment->id)
        ->where('student_profile_id', $student->id)
        ->count())->toBe(1);

    $newPath = AssignmentSubmission::query()
        ->where('assignment_id', $assignment->id)
        ->where('student_profile_id', $student->id)
        ->sole()
        ->file_path;

    expect($newPath)->not->toBe($oldPath);
    Storage::assertExists($newPath);
    Storage::assertMissing($oldPath);
});

it('rejects a disallowed file type', function () {
    Storage::fake();
    [$course] = c3SubCourse();
    $assignment = c3SubAssignment($course);
    $student = c3SubCohortStudent($course);

    $this->actingAs($student->user)
        ->post(route('student.assignments.submit', $assignment), c3SubPayload([
            'file' => UploadedFile::fake()->create('x.exe', 10),
        ]))
        ->assertSessionHasErrors('file');

    expect(AssignmentSubmission::query()->where('assignment_id', $assignment->id)->count())->toBe(0);
});

it('rejects an oversized file', function () {
    Storage::fake();
    [$course] = c3SubCourse();
    $assignment = c3SubAssignment($course);
    $student = c3SubCohortStudent($course);

    $this->actingAs($student->user)
        ->post(route('student.assignments.submit', $assignment), c3SubPayload([
            'file' => UploadedFile::fake()->create('big.pdf', 9000, 'application/pdf'),
        ]))
        ->assertSessionHasErrors('file');

    expect(AssignmentSubmission::query()->where('assignment_id', $assignment->id)->count())->toBe(0);
});

it('serves the file inline to the owning student', function () {
    Storage::fake();
    [$course] = c3SubCourse();
    $assignment = c3SubAssignment($course);
    $student = c3SubCohortStudent($course);

    $this->actingAs($student->user)
        ->post(route('student.assignments.submit', $assignment), c3SubPayload())
        ->assertSessionHasNoErrors();

    $submission = AssignmentSubmission::query()
        ->where('assignment_id', $assignment->id)
        ->where('student_profile_id', $student->id)
        ->sole();

    $response = $this->actingAs($student->user)
        ->get(route('assignments.submission.view', $submission));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('inline');
});

it('serves the file inline to the assigned lecturer', function () {
    Storage::fake();
    [$course, $lecturer] = c3SubCourse();
    $assignment = c3SubAssignment($course);
    $student = c3SubCohortStudent($course);

    $this->actingAs($student->user)
        ->post(route('student.assignments.submit', $assignment), c3SubPayload())
        ->assertSessionHasNoErrors();

    $submission = AssignmentSubmission::query()
        ->where('assignment_id', $assignment->id)
        ->where('student_profile_id', $student->id)
        ->sole();

    $this->actingAs($lecturer->user)
        ->get(route('assignments.submission.view', $submission))
        ->assertOk();
});

it('forbids an unrelated student from viewing the submission', function () {
    Storage::fake();
    [$course] = c3SubCourse();
    $assignment = c3SubAssignment($course);
    $owner = c3SubCohortStudent($course);

    $this->actingAs($owner->user)
        ->post(route('student.assignments.submit', $assignment), c3SubPayload())
        ->assertSessionHasNoErrors();

    $submission = AssignmentSubmission::query()
        ->where('assignment_id', $assignment->id)
        ->where('student_profile_id', $owner->id)
        ->sole();

    $intruder = c3SubCohortStudent($course);

    $this->actingAs($intruder->user)
        ->get(route('assignments.submission.view', $submission))
        ->assertForbidden();
});

it('downloads the file as an attachment for the owning student', function () {
    Storage::fake();
    [$course] = c3SubCourse();
    $assignment = c3SubAssignment($course);
    $student = c3SubCohortStudent($course);

    $this->actingAs($student->user)
        ->post(route('student.assignments.submit', $assignment), c3SubPayload())
        ->assertSessionHasNoErrors();

    $submission = AssignmentSubmission::query()
        ->where('assignment_id', $assignment->id)
        ->where('student_profile_id', $student->id)
        ->sole();

    $this->actingAs($student->user)
        ->get(route('assignments.submission.download', $submission))
        ->assertOk();
});
