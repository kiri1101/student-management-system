<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\StudentProfile;
use App\Models\Transcript;
use App\Models\User;
use App\Services\TranscriptService;
use Illuminate\Support\Facades\DB;

/**
 * Issues a student's official transcript: builds the snapshot, and returns an
 * immutable, signed Transcript record for it — reusing an existing record when
 * the academic content is unchanged (deduped by content digest), otherwise
 * minting a new numbered, audited one. Returns null when the student has no
 * published results (the caller declines to issue an empty document).
 */
class IssueTranscript
{
    public function __construct(private TranscriptService $transcripts) {}

    public function execute(StudentProfile $profile, User $issuedBy, string $generatedByRole): ?Transcript
    {
        $snapshot = $this->transcripts->buildSnapshot($profile, $generatedByRole);

        if ($snapshot['semesters'] === []) {
            return null;
        }

        $digest = $this->transcripts->contentDigest($snapshot);

        $existing = Transcript::query()
            ->where('student_profile_id', $profile->id)
            ->where('content_digest', $digest)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($profile, $issuedBy, $snapshot, $digest): Transcript {
            $issuedAt = now();
            $number = Transcript::nextTranscriptNumberForYear($issuedAt->year);

            $transcript = Transcript::create([
                'transcript_number' => $number,
                'student_profile_id' => $profile->id,
                'matricule' => $snapshot['student']['matricule'],
                'student_name' => $snapshot['student']['name'],
                'programme' => $snapshot['student']['programme'],
                'level' => $snapshot['student']['level'],
                'snapshot' => $snapshot,
                'content_digest' => $digest,
                'cgpa' => $snapshot['cumulative']['cgpa'],
                'credits_earned' => $snapshot['cumulative']['credits_earned'],
                'credits_attempted' => $snapshot['cumulative']['credits_attempted'],
                'signature' => Transcript::computeSignature($number, $issuedAt->toIso8601String(), $digest),
                'issued_at' => $issuedAt,
                'issued_by' => $issuedBy->id,
            ]);

            AuditLog::record(
                AuditAction::TranscriptGenerated,
                $transcript,
                ['transcript_number' => $number, 'student_profile_id' => $profile->id],
                userId: $issuedBy->id,
            );

            return $transcript;
        });
    }
}
