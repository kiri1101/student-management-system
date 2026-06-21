<?php

namespace App\Models;

use App\Enums\AssignmentSubmissionStatus;
use App\Models\Concerns\RecordsAudit;
use Database\Factories\AssignmentSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A student's uploaded file for one assignment, flagged is_late when submitted
 * after the due date. Status (AssignmentSubmissionStatus) is Submitted until a
 * lecturer records a score and feedback, which marks it Graded.
 */
#[Fillable([
    'assignment_id',
    'student_profile_id',
    'file_path',
    'original_filename',
    'mime_type',
    'size_bytes',
    'submitted_at',
    'is_late',
    'score',
    'feedback',
    'graded_by',
    'graded_at',
    'status',
])]
class AssignmentSubmission extends Model
{
    /** @use HasFactory<AssignmentSubmissionFactory> */
    use HasFactory, RecordsAudit, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
            'is_late' => 'boolean',
            'score' => 'integer',
            'size_bytes' => 'integer',
            'status' => AssignmentSubmissionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
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
    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
