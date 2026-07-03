<?php

use App\Enums\RoleName;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\LecturerProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->withoutVite();
});

/**
 * Creates a student user bound to a StudentProfile inside the given course cohort
 * and returns the profile.
 */
function myCoursesStudent(Course $course): StudentProfile
{
    $user = userWithRole(RoleName::Student);

    return StudentProfile::factory()->create([
        'user_id' => $user->id,
        'program_offering_id' => $course->program_offering_id,
        'level' => $course->level,
        'academic_year' => $course->academic_year,
    ]);
}

it('lists approved cohort courses ordered by semester with lecturer and counts', function () {
    $lecturer = LecturerProfile::factory()->create();

    $semesterTwo = Course::factory()->approved()->create([
        'code' => 'CSC201',
        'semester' => 2,
    ]);
    $semesterOne = Course::factory()->approved()->create([
        'code' => 'CSC101',
        'semester' => 1,
        'lecturer_profile_id' => $lecturer->id,
        'program_offering_id' => $semesterTwo->program_offering_id,
    ]);

    CourseSession::factory()->count(2)->create(['course_id' => $semesterOne->id]);
    Assignment::factory()->create(['course_id' => $semesterOne->id]);

    $student = myCoursesStudent($semesterOne);

    $this->actingAs($student->user)
        ->get(route('student.courses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('student/courses/Index')
            ->has('courses', 2)
            ->where('courses.0.code', 'CSC101')
            ->where('courses.0.semester', 1)
            ->where('courses.0.lecturer_name', $lecturer->user->name)
            ->where('courses.0.sessions_count', 2)
            ->where('courses.0.assignments_count', 1)
            ->where('courses.1.code', 'CSC201')
            ->where('courses.1.semester', 2)
            ->where('courses.1.lecturer_name', null)
            ->where('cohort.level', $student->level)
            ->where('cohort.academic_year', $student->academic_year));
});

it('excludes unapproved and out-of-cohort courses', function () {
    $approved = Course::factory()->approved()->create();

    // Same cohort but still under review — invisible to students.
    Course::factory()->submitted()->create();

    // Approved but a different level — outside the viewer's cohort.
    Course::factory()->approved()->create(['level' => 200]);

    $student = myCoursesStudent($approved);

    $this->actingAs($student->user)
        ->get(route('student.courses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('courses', 1)
            ->where('courses.0.id', $approved->id));
});

it('renders empty for a student account without a profile', function () {
    $user = userWithRole(RoleName::Student);

    $this->actingAs($user)
        ->get(route('student.courses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('student/courses/Index')
            ->has('courses', 0)
            ->where('cohort', null));
});

it('forbids a non-student role from the student courses index', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Lecturer);

    $this->actingAs($user)
        ->get(route('student.courses.index'))
        ->assertForbidden();
});
