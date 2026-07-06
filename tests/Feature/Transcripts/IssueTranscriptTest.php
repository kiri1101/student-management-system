<?php

use App\Actions\IssueTranscript;
use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseResult;
use App\Models\StudentProfile;
use App\Models\Transcript;
use App\Models\User;

function issueFor(StudentProfile $profile): ?Transcript
{
    return app(IssueTranscript::class)->execute($profile, User::factory()->create(), 'student');
}

it('issues a signed transcript and audits it', function (): void {
    $profile = StudentProfile::factory()->create();
    $course = Course::factory()->approved()->create(['credits' => 3]);
    CourseResult::factory()->published()->create(['course_id' => $course->id, 'student_profile_id' => $profile->id, 'ca_score' => 80, 'exam_score' => 80]);

    $transcript = issueFor($profile);

    expect($transcript)->not->toBeNull()
        ->and($transcript->verifies())->toBeTrue()
        ->and($transcript->transcript_number)->toStartWith('TRN-')
        ->and((float) $transcript->cgpa)->toBe(4.0)
        ->and(AuditLog::where('action', AuditAction::TranscriptGenerated->value)->where('subject_id', $transcript->id)->exists())->toBeTrue();
});

it('returns null and issues nothing when there are no published results', function (): void {
    $profile = StudentProfile::factory()->create();

    expect(issueFor($profile))->toBeNull()
        ->and(Transcript::count())->toBe(0);
});

it('dedupes unchanged content and mints a new record when results change', function (): void {
    $profile = StudentProfile::factory()->create();
    $c1 = Course::factory()->approved()->create(['credits' => 3]);
    CourseResult::factory()->published()->create(['course_id' => $c1->id, 'student_profile_id' => $profile->id, 'ca_score' => 80, 'exam_score' => 80]);

    $first = issueFor($profile);
    $again = issueFor($profile);

    expect($again->id)->toBe($first->id)
        ->and($again->transcript_number)->toBe($first->transcript_number)
        ->and(Transcript::count())->toBe(1)
        ->and(AuditLog::where('action', AuditAction::TranscriptGenerated->value)->count())->toBe(1);

    $c2 = Course::factory()->approved()->create(['credits' => 3]);
    CourseResult::factory()->published()->create(['course_id' => $c2->id, 'student_profile_id' => $profile->id, 'ca_score' => 70, 'exam_score' => 70]);

    $third = issueFor($profile);

    expect($third->id)->not->toBe($first->id)
        ->and(Transcript::count())->toBe(2);
});
