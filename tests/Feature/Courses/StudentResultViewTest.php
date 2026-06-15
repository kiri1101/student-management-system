<?php

use App\Enums\ResultStatus;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\CourseResult;
use App\Models\LecturerProfile;
use App\Models\StudentProfile;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->withoutVite();
});

/**
 * Builds an approved course with an owning lecturer.
 */
function c4ViewApprovedCourse(): Course
{
    $lecturer = LecturerProfile::factory()->create();

    return Course::factory()->approved()->create(['lecturer_profile_id' => $lecturer->id]);
}

/**
 * Creates a student user bound to an Active StudentProfile inside the cohort.
 */
function c4ViewCohortStudent(Course $course): StudentProfile
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

it('shows the student their published result', function () {
    $course = c4ViewApprovedCourse();
    $student = c4ViewCohortStudent($course);

    $result = CourseResult::factory()->create([
        'course_id' => $course->id,
        'student_profile_id' => $student->id,
        'ca_score' => 70,
        'exam_score' => 80,
        'status' => ResultStatus::Published->value,
        'published_at' => now(),
    ]);

    $this->actingAs($student->user)
        ->get(route('student.results.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('student/results/Index')
            ->where('courses.0.id', $course->id)
            ->where('courses.0.result_id', $result->id));
});

it('does not show the student a draft result', function () {
    $course = c4ViewApprovedCourse();
    $student = c4ViewCohortStudent($course);

    CourseResult::factory()->create([
        'course_id' => $course->id,
        'student_profile_id' => $student->id,
        'ca_score' => 70,
        'exam_score' => 80,
        'status' => ResultStatus::Draft->value,
        'published_at' => null,
    ]);

    $this->actingAs($student->user)
        ->get(route('student.results.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('student/results/Index')
            ->has('courses', 0));
});

it('never exposes another students published result', function () {
    $course = c4ViewApprovedCourse();
    $viewer = c4ViewCohortStudent($course);
    $other = c4ViewCohortStudent($course);

    CourseResult::factory()->create([
        'course_id' => $course->id,
        'student_profile_id' => $other->id,
        'ca_score' => 90,
        'exam_score' => 90,
        'status' => ResultStatus::Published->value,
        'published_at' => now(),
    ]);

    $this->actingAs($viewer->user)
        ->get(route('student.results.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('student/results/Index')
            ->has('courses', 0));
});
