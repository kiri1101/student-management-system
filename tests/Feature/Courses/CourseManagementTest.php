<?php

use App\Enums\AuditAction;
use App\Enums\CoursePlanStatus;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\LecturerProfile;
use App\Models\StudentProfile;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
});

/**
 * Sources a valid program_offering_id without pulling in the (parallel) Course
 * factory. StudentProfileFactory provisions a default ProgramOffering for us.
 */
function c1CourseOfferingId(): int
{
    return StudentProfile::factory()->create()->program_offering_id;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function c1CoursePayload(array $overrides = []): array
{
    return [
        'program_offering_id' => c1CourseOfferingId(),
        'level' => 100,
        'academic_year' => '2026',
        'code' => 'CS101',
        'title' => 'Introduction to Computer Science',
        'credits' => 6,
        'semester' => 1,
        'description' => null,
        ...$overrides,
    ];
}

it('lets an SAO create a course, persisting the row and a CourseCreated audit', function () {
    $sao = userWithRole(RoleName::Sao);
    $payload = c1CoursePayload();

    $response = $this->actingAs($sao)->post(route('sao.courses.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $course = Course::query()->where('code', 'CS101')->sole();
    expect($course->program_offering_id)->toBe($payload['program_offering_id'])
        ->and($course->level)->toBe(100)
        ->and($course->academic_year)->toBe('2026')
        ->and($course->title)->toBe('Introduction to Computer Science')
        ->and($course->credits)->toBe(6)
        ->and($course->semester)->toBe(1)
        ->and($course->plan_status)->toBe(CoursePlanStatus::Draft)
        ->and($course->lecturer_profile_id)->toBeNull();

    expect(AuditLog::query()
        ->where('subject_type', $course->getMorphClass())
        ->where('subject_id', $course->id)
        ->where('action', AuditAction::CourseCreated->value)
        ->exists())->toBeTrue();
});

it('rejects a duplicate course on (program_offering, level, academic_year, code)', function () {
    $sao = userWithRole(RoleName::Sao);
    $offeringId = c1CourseOfferingId();

    Course::factory()->draft()->create([
        'program_offering_id' => $offeringId,
        'level' => 100,
        'academic_year' => '2026',
        'code' => 'CS101',
    ]);

    $response = $this->actingAs($sao)->post(route('sao.courses.store'), c1CoursePayload([
        'program_offering_id' => $offeringId,
        'level' => 100,
        'academic_year' => '2026',
        'code' => 'CS101',
    ]));

    $response->assertSessionHasErrors('code');
    expect(Course::query()->where('code', 'CS101')->count())->toBe(1);
});

it('allows the same code for a different level/year/offering', function () {
    $sao = userWithRole(RoleName::Sao);
    $offeringId = c1CourseOfferingId();

    Course::factory()->draft()->create([
        'program_offering_id' => $offeringId,
        'level' => 100,
        'academic_year' => '2026',
        'code' => 'CS101',
    ]);

    $this->actingAs($sao)->post(route('sao.courses.store'), c1CoursePayload([
        'program_offering_id' => $offeringId,
        'level' => 200,
        'academic_year' => '2026',
        'code' => 'CS101',
    ]))->assertSessionHasNoErrors();

    expect(Course::query()->where('code', 'CS101')->count())->toBe(2);
});

it('forbids non-SAO roles from creating a course', function (RoleName $role) {
    $user = userWithRole($role);

    $this->actingAs($user)
        ->post(route('sao.courses.store'), c1CoursePayload())
        ->assertForbidden();

    expect(Course::count())->toBe(0);
})->with([
    'student' => [RoleName::Student],
    'lecturer' => [RoleName::Lecturer],
    'accountant' => [RoleName::Accountant],
    'applicant' => [RoleName::Applicant],
]);

it('lets an admin create a course too', function () {
    $admin = userWithRole(RoleName::Admin);

    $this->actingAs($admin)
        ->post(route('sao.courses.store'), c1CoursePayload())
        ->assertSessionHasNoErrors();

    expect(Course::query()->where('code', 'CS101')->exists())->toBeTrue();
});

it('lets an SAO assign a lecturer to a course and writes a LecturerAssigned audit', function () {
    $sao = userWithRole(RoleName::Sao);
    $course = Course::factory()->draft()->create();
    $lecturer = LecturerProfile::factory()->create();

    $response = $this->actingAs($sao)->post(route('sao.courses.assignLecturer', $course), [
        'lecturer_profile_id' => $lecturer->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    expect($course->fresh()->lecturer_profile_id)->toBe($lecturer->id);

    expect(AuditLog::query()
        ->where('subject_type', $course->getMorphClass())
        ->where('subject_id', $course->id)
        ->where('action', AuditAction::LecturerAssigned->value)
        ->exists())->toBeTrue();
});

it('forbids non-SAO roles from assigning a lecturer', function () {
    $lecturer = userWithRole(RoleName::Lecturer);
    $course = Course::factory()->draft()->create();
    $profile = LecturerProfile::factory()->create();

    $this->actingAs($lecturer)
        ->post(route('sao.courses.assignLecturer', $course), ['lecturer_profile_id' => $profile->id])
        ->assertForbidden();

    expect($course->fresh()->lecturer_profile_id)->toBeNull();
});
