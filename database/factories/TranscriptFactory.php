<?php

namespace Database\Factories;

use App\Models\StudentProfile;
use App\Models\Transcript;
use App\Services\TranscriptService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transcript>
 */
class TranscriptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $snapshot = [
            'student' => ['matricule' => 'stm-2025-0001', 'name' => 'Test Student', 'programme' => 'Computer Science', 'level' => 200],
            'semesters' => [[
                'academic_year' => '2025/2026',
                'semester' => 1,
                'courses' => [['code' => 'CSC101', 'title' => 'Intro', 'credits' => 3, 'score' => 82, 'grade' => 'A', 'points' => 4.0]],
                'gpa' => 4.0,
                'credits_earned' => 3,
                'credits_attempted' => 3,
            ]],
            'cumulative' => ['cgpa' => 4.0, 'credits_earned' => 3, 'credits_attempted' => 3, 'total_courses' => 1],
            'meta' => ['generated_at' => now()->toIso8601String(), 'generated_by_role' => 'student'],
        ];

        $issuedAt = now();
        $number = 'TRN-'.$issuedAt->year.'-'.fake()->unique()->numerify('#####');
        $digest = app(TranscriptService::class)->contentDigest($snapshot);

        return [
            'transcript_number' => $number,
            'student_profile_id' => fn () => StudentProfile::factory(),
            'matricule' => $snapshot['student']['matricule'],
            'student_name' => $snapshot['student']['name'],
            'programme' => $snapshot['student']['programme'],
            'level' => $snapshot['student']['level'],
            'snapshot' => $snapshot,
            'content_digest' => $digest,
            'cgpa' => 4.0,
            'credits_earned' => 3,
            'credits_attempted' => 3,
            'signature' => Transcript::computeSignature($number, $issuedAt->toIso8601String(), $digest),
            'issued_at' => $issuedAt,
            'issued_by' => null,
        ];
    }
}
