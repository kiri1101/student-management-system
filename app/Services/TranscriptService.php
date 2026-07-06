<?php

namespace App\Services;

use App\Enums\ResultStatus;
use App\Models\CourseResult;
use App\Models\StudentProfile;

/**
 * Computes a student's academic transcript from their published course results:
 * groups by academic year -> semester, joins course metadata, and derives a
 * credit-weighted GPA per semester plus a cumulative CGPA on the 4.0 scale.
 * Pure computation — no persistence. The resulting snapshot is the source of
 * truth stored on an immutable Transcript record and rendered to PDF.
 */
class TranscriptService
{
    /**
     * Grade-point value per letter grade on the 4.0 scale. Whole points only —
     * the grading scheme has no +/- tiers (see CourseResult::grade).
     *
     * @var array<string, float>
     */
    public const array GRADE_POINTS = [
        'A' => 4.0,
        'B' => 3.0,
        'C' => 2.0,
        'D' => 1.0,
        'F' => 0.0,
    ];

    /**
     * Build the full transcript snapshot for a student. Only published results
     * with both marks present (a real final score + grade) are included; draft
     * and unscored results never appear. Semesters are ordered by academic year
     * then semester; courses within a semester by code. Returns an empty
     * `semesters` list when there is nothing to report.
     *
     * @return array{
     *     student: array{matricule: string|null, name: string|null, programme: string|null, level: int|null},
     *     semesters: list<array{academic_year: string, semester: int, courses: list<array{code: string, title: string, credits: int, score: int, grade: string, points: float}>, gpa: float, credits_earned: int, credits_attempted: int}>,
     *     cumulative: array{cgpa: float, credits_earned: int, credits_attempted: int, total_courses: int},
     *     meta: array{generated_at: string, generated_by_role: string}
     * }
     */
    public function buildSnapshot(StudentProfile $profile, string $generatedByRole): array
    {
        $results = CourseResult::query()
            ->where('student_profile_id', $profile->id)
            ->where('status', ResultStatus::Published->value)
            ->with('course')
            ->get()
            ->filter(fn (CourseResult $result): bool => $result->final_score !== null && $result->course !== null)
            ->sortBy([
                fn (CourseResult $r) => $r->course->academic_year,
                fn (CourseResult $r) => $r->course->semester,
                fn (CourseResult $r) => $r->course->code,
            ]);

        $grouped = $results->groupBy(fn (CourseResult $r): string => $r->course->academic_year.'|'.$r->course->semester);

        $semesters = [];
        $cumQualityPoints = 0.0;
        $cumCredits = 0;
        $cumEarned = 0;
        $totalCourses = 0;

        foreach ($grouped as $rows) {
            $courses = [];
            $semQualityPoints = 0.0;
            $semCredits = 0;
            $semEarned = 0;

            foreach ($rows as $result) {
                $grade = $result->grade;
                $points = self::GRADE_POINTS[$grade];
                $credits = (int) $result->course->credits;

                $courses[] = [
                    'code' => $result->course->code,
                    'title' => $result->course->title,
                    'credits' => $credits,
                    'score' => (int) $result->final_score,
                    'grade' => $grade,
                    'points' => $points,
                ];

                $semQualityPoints += $points * $credits;
                $semCredits += $credits;

                if ($grade !== 'F') {
                    $semEarned += $credits;
                }
            }

            $first = $rows->first();

            $semesters[] = [
                'academic_year' => $first->course->academic_year,
                'semester' => (int) $first->course->semester,
                'courses' => $courses,
                'gpa' => $this->weightedAverage($semQualityPoints, $semCredits),
                'credits_earned' => $semEarned,
                'credits_attempted' => $semCredits,
            ];

            $cumQualityPoints += $semQualityPoints;
            $cumCredits += $semCredits;
            $cumEarned += $semEarned;
            $totalCourses += count($courses);
        }

        return [
            'student' => [
                'matricule' => $profile->matricule,
                'name' => $profile->user?->name,
                'programme' => $profile->programOffering?->department?->name,
                'level' => $profile->level,
            ],
            'semesters' => $semesters,
            'cumulative' => [
                'cgpa' => $this->weightedAverage($cumQualityPoints, $cumCredits),
                'credits_earned' => $cumEarned,
                'credits_attempted' => $cumCredits,
                'total_courses' => $totalCourses,
            ],
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'generated_by_role' => $generatedByRole,
            ],
        ];
    }

    /**
     * Stable SHA-256 over the snapshot's identity + academic content. The `meta`
     * block (issue time + issuer) is excluded so re-issuing the same results for
     * the same student yields the same digest and is deduped. Encoding is
     * deterministic across the DB JSON round-trip (int/float normalization), so
     * the digest recomputed at verify time matches the one computed at issue.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function contentDigest(array $snapshot): string
    {
        $stable = $snapshot;
        unset($stable['meta']);

        return hash('sha256', json_encode($stable, JSON_THROW_ON_ERROR));
    }

    private function weightedAverage(float $qualityPoints, int $credits): float
    {
        return round($credits > 0 ? $qualityPoints / $credits : 0.0, 2);
    }
}
