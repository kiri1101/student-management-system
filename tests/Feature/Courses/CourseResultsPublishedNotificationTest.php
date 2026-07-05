<?php

use App\Enums\AuditAction;
use App\Enums\ResultStatus;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseResult;
use App\Models\StudentProfile;
use App\Notifications\CourseResultsPublishedNotification;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    Notification::fake();
});

/**
 * A draft result for the given student on the given course, with both marks
 * present (so it qualifies for publication).
 */
function resultsNotifScoredDraft(Course $course, StudentProfile $student): CourseResult
{
    return CourseResult::factory()->create([
        'course_id' => $course->id,
        'student_profile_id' => $student->id,
        'ca_score' => 55,
        'exam_score' => 65,
        'status' => ResultStatus::Draft->value,
    ]);
}

it('notifies only the students whose results were published, on mail and database', function () {
    $sao = userWithRole(RoleName::Sao);
    $course = Course::factory()->approved()->create();

    $studentA = StudentProfile::factory()->create();
    $studentB = StudentProfile::factory()->create();
    resultsNotifScoredDraft($course, $studentA);
    resultsNotifScoredDraft($course, $studentB);

    // In the cohort but with no marks yet — nothing is published for them.
    $studentC = StudentProfile::factory()->create();
    CourseResult::factory()->unscored()->create([
        'course_id' => $course->id,
        'student_profile_id' => $studentC->id,
    ]);

    $this->actingAs($sao)
        ->post(route('sao.courses.publishResults', $course))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    foreach ([$studentA, $studentB] as $student) {
        Notification::assertSentTo(
            $student->user,
            CourseResultsPublishedNotification::class,
            function (CourseResultsPublishedNotification $notification, array $channels) use ($course): bool {
                return in_array('mail', $channels, true)
                    && in_array('database', $channels, true)
                    && $notification->course->is($course);
            },
        );
    }

    Notification::assertNotSentTo($studentC->user, CourseResultsPublishedNotification::class);

    expect(AuditLog::query()
        ->where('subject_type', $course->getMorphClass())
        ->where('subject_id', $course->id)
        ->where('action', AuditAction::ResultsPublished->value)
        ->exists())->toBeTrue();
});

it('re-publishing notifies only the newly published students', function () {
    $sao = userWithRole(RoleName::Sao);
    $course = Course::factory()->approved()->create();

    // Already published in an earlier round.
    $studentA = StudentProfile::factory()->create();
    CourseResult::factory()->published()->create([
        'course_id' => $course->id,
        'student_profile_id' => $studentA->id,
        'ca_score' => 55,
        'exam_score' => 65,
    ]);

    // Newly scored draft.
    $studentB = StudentProfile::factory()->create();
    resultsNotifScoredDraft($course, $studentB);

    $this->actingAs($sao)
        ->post(route('sao.courses.publishResults', $course))
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($studentB->user, CourseResultsPublishedNotification::class);
    Notification::assertNotSentTo($studentA->user, CourseResultsPublishedNotification::class);
});
