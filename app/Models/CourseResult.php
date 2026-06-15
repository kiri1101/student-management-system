<?php

namespace App\Models;

use App\Enums\ResultStatus;
use App\Models\Concerns\RecordsAudit;
use Database\Factories\CourseResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'course_id',
    'student_profile_id',
    'ca_score',
    'exam_score',
    'status',
    'published_at',
    'published_by',
])]
class CourseResult extends Model
{
    /** @use HasFactory<CourseResultFactory> */
    use HasFactory, RecordsAudit, SoftDeletes;

    /**
     * Weighting of the continuous-assessment mark in the final score.
     */
    public const float CA_WEIGHT = 0.3;

    /**
     * Weighting of the examination mark in the final score.
     */
    public const float EXAM_WEIGHT = 0.7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ca_score' => 'integer',
            'exam_score' => 'integer',
            'status' => ResultStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * @return HasMany<ResultDispute, $this>
     */
    public function disputes(): HasMany
    {
        return $this->hasMany(ResultDispute::class);
    }

    /**
     * The weighted final score (0–100), or null until both marks are recorded.
     */
    protected function finalScore(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->computedFinalScore());
    }

    /**
     * The letter grade derived from the final score, or null until both marks
     * are recorded.
     */
    protected function grade(): Attribute
    {
        return Attribute::get(function (): ?string {
            $final = $this->computedFinalScore();

            if ($final === null) {
                return null;
            }

            return match (true) {
                $final >= 80 => 'A',
                $final >= 70 => 'B',
                $final >= 60 => 'C',
                $final >= 50 => 'D',
                default => 'F',
            };
        });
    }

    /**
     * Compute the weighted final score from the raw marks, shared by the
     * final_score and grade accessors. Null until both marks are present.
     */
    private function computedFinalScore(): ?int
    {
        if ($this->ca_score === null || $this->exam_score === null) {
            return null;
        }

        return (int) round($this->ca_score * self::CA_WEIGHT + $this->exam_score * self::EXAM_WEIGHT);
    }
}
