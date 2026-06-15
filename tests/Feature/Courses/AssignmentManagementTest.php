<?php

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\Assignment;
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
function c3MgmtLecturer(): LecturerProfile
{
    $user = userWithRole(RoleName::Lecturer);

    return LecturerProfile::factory()->create(['user_id' => $user->id]);
}

/**
 * Builds an Approved course owned by the given lecturer.
 */
function c3MgmtApprovedCourse(LecturerProfile $lecturer): Course
{
    return Course::factory()->approved()->create(['lecturer_profile_id' => $lecturer->id]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function c3MgmtPayload(array $overrides = []): array
{
    return [
        'title' => 'Linked lists assignment',
        'instructions' => 'Implement a doubly linked list and submit the source.',
        'due_at' => now()->addWeek()->toDateTimeString(),
        'max_score' => 20,
        ...$overrides,
    ];
}

it('lets the assigned lecturer create an assignment on an approved course', function () {
    $lecturer = c3MgmtLecturer();
    $course = c3MgmtApprovedCourse($lecturer);

    $response = $this->actingAs($lecturer->user)
        ->post(route('lecturer.courses.assignments.store', $course), c3MgmtPayload());

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $assignment = Assignment::query()->where('course_id', $course->id)->sole();
    expect($assignment->title)->toBe('Linked lists assignment')
        ->and($assignment->max_score)->toBe(20)
        ->and($assignment->created_by)->toBe($lecturer->user->id);

    expect(AuditLog::query()
        ->where('subject_type', $assignment->getMorphClass())
        ->where('subject_id', $assignment->id)
        ->where('action', AuditAction::AssignmentCreated->value)
        ->exists())->toBeTrue();
});

it('rejects creating an assignment on a non-approved (draft) course', function () {
    $lecturer = c3MgmtLecturer();
    $course = Course::factory()->draft()->create(['lecturer_profile_id' => $lecturer->id]);

    $this->actingAs($lecturer->user)
        ->post(route('lecturer.courses.assignments.store', $course), c3MgmtPayload())
        ->assertForbidden();

    expect(Assignment::query()->where('course_id', $course->id)->count())->toBe(0);
});

it('forbids a non-assigned lecturer from creating an assignment', function () {
    $owner = c3MgmtLecturer();
    $intruder = c3MgmtLecturer();
    $course = c3MgmtApprovedCourse($owner);

    $this->actingAs($intruder->user)
        ->post(route('lecturer.courses.assignments.store', $course), c3MgmtPayload())
        ->assertForbidden();

    expect(Assignment::query()->where('course_id', $course->id)->count())->toBe(0);
});

it('lets the assigned lecturer update an assignment', function () {
    $lecturer = c3MgmtLecturer();
    $course = c3MgmtApprovedCourse($lecturer);
    $assignment = Assignment::factory()->create([
        'course_id' => $course->id,
        'created_by' => $lecturer->user->id,
        'title' => 'Old title',
        'max_score' => 10,
    ]);

    $response = $this->actingAs($lecturer->user)
        ->patch(route('lecturer.courses.assignments.update', [$course, $assignment]), c3MgmtPayload([
            'title' => 'Updated title',
            'max_score' => 40,
        ]));

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $fresh = $assignment->fresh();
    expect($fresh->title)->toBe('Updated title')
        ->and($fresh->max_score)->toBe(40);
});

it('lets the assigned lecturer soft-delete an assignment', function () {
    $lecturer = c3MgmtLecturer();
    $course = c3MgmtApprovedCourse($lecturer);
    $assignment = Assignment::factory()->create([
        'course_id' => $course->id,
        'created_by' => $lecturer->user->id,
    ]);

    $response = $this->actingAs($lecturer->user)
        ->delete(route('lecturer.courses.assignments.destroy', [$course, $assignment]));

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $this->assertSoftDeleted($assignment);
});

it('forbids a non-assigned lecturer from updating an assignment', function () {
    $owner = c3MgmtLecturer();
    $intruder = c3MgmtLecturer();
    $course = c3MgmtApprovedCourse($owner);
    $assignment = Assignment::factory()->create([
        'course_id' => $course->id,
        'created_by' => $owner->user->id,
        'title' => 'Owner title',
    ]);

    $this->actingAs($intruder->user)
        ->patch(route('lecturer.courses.assignments.update', [$course, $assignment]), c3MgmtPayload([
            'title' => 'Hijacked title',
        ]))
        ->assertForbidden();

    expect($assignment->fresh()->title)->toBe('Owner title');
});

it('forbids a non-assigned lecturer from deleting an assignment', function () {
    $owner = c3MgmtLecturer();
    $intruder = c3MgmtLecturer();
    $course = c3MgmtApprovedCourse($owner);
    $assignment = Assignment::factory()->create([
        'course_id' => $course->id,
        'created_by' => $owner->user->id,
    ]);

    $this->actingAs($intruder->user)
        ->delete(route('lecturer.courses.assignments.destroy', [$course, $assignment]))
        ->assertForbidden();

    $this->assertNotSoftDeleted($assignment);
});

it('validates a missing title', function () {
    $lecturer = c3MgmtLecturer();
    $course = c3MgmtApprovedCourse($lecturer);

    $this->actingAs($lecturer->user)
        ->post(route('lecturer.courses.assignments.store', $course), c3MgmtPayload(['title' => '']))
        ->assertSessionHasErrors('title');

    expect(Assignment::query()->where('course_id', $course->id)->count())->toBe(0);
});

it('validates a max_score below one', function () {
    $lecturer = c3MgmtLecturer();
    $course = c3MgmtApprovedCourse($lecturer);

    $this->actingAs($lecturer->user)
        ->post(route('lecturer.courses.assignments.store', $course), c3MgmtPayload(['max_score' => 0]))
        ->assertSessionHasErrors('max_score');

    expect(Assignment::query()->where('course_id', $course->id)->count())->toBe(0);
});
