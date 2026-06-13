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
use Illuminate\Database\Eloquent\SoftDeletes;

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

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }
}
