<?php

namespace App\Models;

use App\Enums\Bank;
use App\Enums\PaymentStatus;
use App\Models\Concerns\RecordsAudit;
use Database\Factories\PaymentSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A student's claim of a bank deposit toward tuition, awaiting accountant review.
 * Mutable and audited; a `Validated` submission is the figure that counts toward
 * payment standing (#8) and is the sole trigger for issuing the immutable
 * SchoolReceipt. Money is integer XAF.
 */
#[Fillable([
    'student_profile_id',
    'academic_year',
    'bank',
    'amount_xaf',
    'bank_reference',
    'slip_path',
    'slip_original_filename',
    'slip_mime_type',
    'status',
    'reviewed_by',
    'reviewed_at',
    'rejection_reason',
])]
class PaymentSubmission extends Model
{
    /** @use HasFactory<PaymentSubmissionFactory> */
    use HasFactory, RecordsAudit, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bank' => Bank::class,
            'amount_xaf' => 'integer',
            'status' => PaymentStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<StudentProfile, $this>
     */
    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    /**
     * The accountant who validated or rejected this submission.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The receipt issued on validation; null until then.
     *
     * @return HasOne<SchoolReceipt, $this>
     */
    public function schoolReceipt(): HasOne
    {
        return $this->hasOne(SchoolReceipt::class);
    }

    /**
     * True once validated or rejected — no longer actionable in the review queue.
     */
    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }
}
