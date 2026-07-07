<?php

use App\Enums\RoleName;
use App\Models\Course;
use App\Models\CourseResult;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => $this->seed(RolesSeeder::class));

it('lets SAO download any student transcript and blocks other roles', function (): void {
    $profile = StudentProfile::factory()->create();
    $course = Course::factory()->approved()->create(['credits' => 3]);
    CourseResult::factory()->published()->create(['course_id' => $course->id, 'student_profile_id' => $profile->id, 'ca_score' => 80, 'exam_score' => 80]);

    $this->actingAs(userWithRole(RoleName::Sao))
        ->get(route('sao.students.transcript', $profile))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs(userWithRole(RoleName::Lecturer))
        ->get(route('sao.students.transcript', $profile))
        ->assertForbidden();
});

it('renders a searchable student index for SAO', function (): void {
    StudentProfile::factory()->create(['matricule' => 'stm-2025-4242']);
    StudentProfile::factory()->create(['matricule' => 'stm-2025-9999']);

    $this->actingAs(userWithRole(RoleName::Sao))
        ->get(route('sao.students.index', ['search' => '4242']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('sao/students/Index')
            ->has('students.data', 1)
            ->where('students.data.0.matricule', 'stm-2025-4242'));
});

it('filters the student index by the student name', function (): void {
    $matched = User::factory()->create(['name' => 'Ada Ngwa']);
    StudentProfile::factory()->create(['user_id' => $matched->id, 'matricule' => 'stm-2025-1111']);

    $other = User::factory()->create(['name' => 'Bola Tabi']);
    StudentProfile::factory()->create(['user_id' => $other->id, 'matricule' => 'stm-2025-2222']);

    $this->actingAs(userWithRole(RoleName::Sao))
        ->get(route('sao.students.index', ['search' => 'Ngwa']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('sao/students/Index')
            ->has('students.data', 1)
            ->where('students.data.0.matricule', 'stm-2025-1111')
            ->where('students.data.0.name', 'Ada Ngwa'));
});
