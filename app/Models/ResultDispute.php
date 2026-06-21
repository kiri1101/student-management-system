<?php

namespace App\Models;

use App\Enums\DisputeStatus;
use App\Models\Concerns\RecordsAudit;
use Database\Factories\ResultDisputeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A student's contestation of a published course result. Status (DisputeStatus)
 * runs Open → UnderReview → Resolved or Rejected; an SAO/lecturer reviewer records
 * the outcome and resolution_notes. Resolved and Rejected are terminal.
 */
#[Fillable([
    'course_result_id',
    'student_profile_id',
    'reason',
    'status',
    'resolution_notes',
    'reviewed_by',
    'reviewed_at',
])]
class ResultDispute extends Model
{
    /** @use HasFactory<ResultDisputeFactory> */
    use HasFactory, RecordsAudit, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DisputeStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CourseResult, $this>
     */
    public function courseResult(): BelongsTo
    {
        return $this->belongsTo(CourseResult::class);
    }

    /**
     * @return BelongsTo<StudentProfile, $this>
     */
    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
