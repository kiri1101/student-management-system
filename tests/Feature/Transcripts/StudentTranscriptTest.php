<?php

use App\Enums\RoleName;
use App\Models\Course;
use App\Models\CourseResult;
use App\Models\StudentProfile;
use App\Models\Transcript;
use Database\Seeders\RolesSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => $this->seed(RolesSeeder::class));

it('lets a student download their own transcript as a PDF', function (): void {
    $user = userWithRole(RoleName::Student);
    $profile = StudentProfile::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->approved()->create(['credits' => 3]);
    CourseResult::factory()->published()->create(['course_id' => $course->id, 'student_profile_id' => $profile->id, 'ca_score' => 80, 'exam_score' => 80]);

    $response = $this->actingAs($user)->get(route('student.transcript'));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF');
});

it('redirects back and issues nothing when the student has no published results', function (): void {
    $user = userWithRole(RoleName::Student);
    StudentProfile::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->from(route('student.results.index'))->get(route('student.transcript'))
        ->assertRedirect(route('student.results.index'));

    expect(Transcript::count())->toBe(0);
});

it('flags transcript availability on the results page', function (): void {
    $user = userWithRole(RoleName::Student);
    $profile = StudentProfile::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->approved()->create();
    CourseResult::factory()->published()->create(['course_id' => $course->id, 'student_profile_id' => $profile->id, 'ca_score' => 80, 'exam_score' => 80]);

    $this->actingAs($user)->get(route('student.results.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('hasTranscript', true));
});
