<?php

namespace App\Http\Controllers\Transcripts;

use App\Http\Controllers\Controller;
use App\Models\Transcript;
use Inertia\Inertia;
use Inertia\Response;

class VerifyTranscriptController extends Controller
{
    /**
     * Public, unauthenticated transcript verification (#71). Re-derives the HMAC
     * from the stored snapshot and shows the transcript summary only when it is
     * authentic. An unknown number and a tampered/forged record both read
     * "invalid" (no oracle for which transcript numbers exist).
     */
    public function __invoke(string $transcriptNumber): Response
    {
        $transcript = Transcript::query()
            ->where('transcript_number', $transcriptNumber)
            ->first();

        $valid = $transcript !== null && $transcript->verifies();

        return Inertia::render('transcripts/Verify', [
            'transcriptNumber' => $transcriptNumber,
            'valid' => $valid,
            'transcript' => $valid ? [
                'transcript_number' => $transcript->transcript_number,
                'student_name' => $transcript->student_name,
                'matricule' => $transcript->matricule,
                'programme' => $transcript->programme,
                'level' => $transcript->level,
                'cgpa' => (float) $transcript->cgpa,
                'credits_earned' => $transcript->credits_earned,
                'credits_attempted' => $transcript->credits_attempted,
                'issued_at' => $transcript->issued_at?->toIso8601String(),
                'semesters' => collect($transcript->snapshot['semesters'] ?? [])
                    ->map(fn (array $semester): array => [
                        'academic_year' => $semester['academic_year'],
                        'semester' => $semester['semester'],
                        'gpa' => $semester['gpa'],
                        'credits_earned' => $semester['credits_earned'],
                        'credits_attempted' => $semester['credits_attempted'],
                    ])->all(),
            ] : null,
        ]);
    }
}
