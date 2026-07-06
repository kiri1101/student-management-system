<?php

use App\Models\Course;
use App\Models\CourseResult;
use App\Models\StudentProfile;
use App\Services\TranscriptService;

it('aggregates published results into per-semester GPA and cumulative CGPA', function (): void {
    $profile = StudentProfile::factory()->create();

    // Semester 1 2025/2026: A(3cr)=4.0, B(4cr)=3.0 -> (12+12)/7 = 3.4285 -> 3.43
    $s1 = Course::factory()->approved()->create(['academic_year' => '2025/2026', 'semester' => 1, 'credits' => 3, 'code' => 'AAA100']);
    $s2 = Course::factory()->approved()->create(['academic_year' => '2025/2026', 'semester' => 1, 'credits' => 4, 'code' => 'BBB100']);
    // Semester 2 2025/2026: F(2cr)=0.0 -> GPA 0.00, earns 0 credits
    $s3 = Course::factory()->approved()->create(['academic_year' => '2025/2026', 'semester' => 2, 'credits' => 2, 'code' => 'CCC100']);

    CourseResult::factory()->published()->create(['course_id' => $s1->id, 'student_profile_id' => $profile->id, 'ca_score' => 85, 'exam_score' => 85]); // 85 -> A
    CourseResult::factory()->published()->create(['course_id' => $s2->id, 'student_profile_id' => $profile->id, 'ca_score' => 72, 'exam_score' => 72]); // 72 -> B
    CourseResult::factory()->published()->create(['course_id' => $s3->id, 'student_profile_id' => $profile->id, 'ca_score' => 10, 'exam_score' => 10]); // 10 -> F

    $snapshot = app(TranscriptService::class)->buildSnapshot($profile, 'student');

    expect($snapshot['semesters'])->toHaveCount(2)
        ->and($snapshot['semesters'][0]['gpa'])->toBe(3.43)
        ->and($snapshot['semesters'][0]['credits_earned'])->toBe(7)
        ->and($snapshot['semesters'][0]['credits_attempted'])->toBe(7)
        ->and($snapshot['semesters'][1]['gpa'])->toBe(0.0)
        ->and($snapshot['semesters'][1]['credits_earned'])->toBe(0)
        ->and($snapshot['semesters'][1]['credits_attempted'])->toBe(2)
        ->and($snapshot['cumulative']['cgpa'])->toBe(2.67) // 24/9
        ->and($snapshot['cumulative']['credits_earned'])->toBe(7)
        ->and($snapshot['cumulative']['credits_attempted'])->toBe(9)
        ->and($snapshot['cumulative']['total_courses'])->toBe(3);
});

it('excludes draft and unscored published results', function (): void {
    $profile = StudentProfile::factory()->create();
    $scored = Course::factory()->approved()->create(['credits' => 3]);
    $drafted = Course::factory()->approved()->create(['credits' => 3]);
    $unscored = Course::factory()->approved()->create(['credits' => 3]);

    CourseResult::factory()->published()->create(['course_id' => $scored->id, 'student_profile_id' => $profile->id, 'ca_score' => 80, 'exam_score' => 80]);
    CourseResult::factory()->draft()->create(['course_id' => $drafted->id, 'student_profile_id' => $profile->id]);
    CourseResult::factory()->published()->unscored()->create(['course_id' => $unscored->id, 'student_profile_id' => $profile->id]);

    $snapshot = app(TranscriptService::class)->buildSnapshot($profile, 'student');

    expect($snapshot['cumulative']['total_courses'])->toBe(1);
});

it('produces the same digest for unchanged content regardless of generation metadata', function (): void {
    $profile = StudentProfile::factory()->create();
    $course = Course::factory()->approved()->create(['credits' => 3]);
    CourseResult::factory()->published()->create(['course_id' => $course->id, 'student_profile_id' => $profile->id, 'ca_score' => 80, 'exam_score' => 80]);

    $service = app(TranscriptService::class);
    $a = $service->contentDigest($service->buildSnapshot($profile, 'student'));
    $b = $service->contentDigest($service->buildSnapshot($profile, 'sao'));

    expect($a)->toBe($b);
});

it('produces a different digest when the underlying academic content differs', function (): void {
    $profileA = StudentProfile::factory()->create();
    $courseA = Course::factory()->approved()->create(['credits' => 3]);
    CourseResult::factory()->published()->create(['course_id' => $courseA->id, 'student_profile_id' => $profileA->id, 'ca_score' => 80, 'exam_score' => 80]); // A

    $profileB = StudentProfile::factory()->create();
    $courseB = Course::factory()->approved()->create(['credits' => 3]);
    CourseResult::factory()->published()->create(['course_id' => $courseB->id, 'student_profile_id' => $profileB->id, 'ca_score' => 55, 'exam_score' => 55]); // D

    $service = app(TranscriptService::class);
    $digestA = $service->contentDigest($service->buildSnapshot($profileA, 'student'));
    $digestB = $service->contentDigest($service->buildSnapshot($profileB, 'student'));

    expect($digestA)->not->toBe($digestB);
});

it('orders semesters by year then semester, and courses by code, even when created out of chronological order', function (): void {
    $profile = StudentProfile::factory()->create();

    // Deliberately created out of order: a later semester first, then an earlier
    // academic year, then two same-year/semester courses with codes that would
    // stay unsorted under the old (broken) sortBy([...]) multi-key sort.
    $laterSemester = Course::factory()->approved()->create(['academic_year' => '2025/2026', 'semester' => 2, 'credits' => 3, 'code' => 'YYY200']);
    $earlierYear = Course::factory()->approved()->create(['academic_year' => '2024/2025', 'semester' => 1, 'credits' => 3, 'code' => 'AAA100']);
    $laterCode = Course::factory()->approved()->create(['academic_year' => '2025/2026', 'semester' => 1, 'credits' => 3, 'code' => 'BBB100']);
    $earlierCode = Course::factory()->approved()->create(['academic_year' => '2025/2026', 'semester' => 1, 'credits' => 3, 'code' => 'AAA050']);

    CourseResult::factory()->published()->create(['course_id' => $laterSemester->id, 'student_profile_id' => $profile->id, 'ca_score' => 80, 'exam_score' => 80]);
    CourseResult::factory()->published()->create(['course_id' => $earlierYear->id, 'student_profile_id' => $profile->id, 'ca_score' => 80, 'exam_score' => 80]);
    CourseResult::factory()->published()->create(['course_id' => $laterCode->id, 'student_profile_id' => $profile->id, 'ca_score' => 80, 'exam_score' => 80]);
    CourseResult::factory()->published()->create(['course_id' => $earlierCode->id, 'student_profile_id' => $profile->id, 'ca_score' => 80, 'exam_score' => 80]);

    $snapshot = app(TranscriptService::class)->buildSnapshot($profile, 'student');

    expect($snapshot['semesters'])->toHaveCount(3)
        ->and($snapshot['semesters'][0]['academic_year'])->toBe('2024/2025')
        ->and($snapshot['semesters'][0]['semester'])->toBe(1)
        ->and($snapshot['semesters'][1]['academic_year'])->toBe('2025/2026')
        ->and($snapshot['semesters'][1]['semester'])->toBe(1)
        ->and($snapshot['semesters'][2]['academic_year'])->toBe('2025/2026')
        ->and($snapshot['semesters'][2]['semester'])->toBe(2)
        ->and(array_column($snapshot['semesters'][1]['courses'], 'code'))->toBe(['AAA050', 'BBB100']);
});

it('returns an empty semester list for a student with no published results', function (): void {
    $profile = StudentProfile::factory()->create();

    expect(app(TranscriptService::class)->buildSnapshot($profile, 'student')['semesters'])->toBe([]);
});
