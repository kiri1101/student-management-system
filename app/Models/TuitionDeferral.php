<?php

namespace App\Models;

use App\Enums\DeferralStatus;
use App\Models\Concerns\RecordsAudit;
use Database\Factories\TuitionDeferralFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A student's request to extend their tuition deadline, reviewed by an accountant.
 * An `Approved` deferral whose `new_deadline` has not yet passed lifts the access
 * gate (standing becomes `Deferred` rather than `Blocked`); see the `activeAsOf`
 * scope and PaymentStandingService. Mutable and audited.
 */
#[Fillable([
    'student_profile_id',
    'academic_year',
    'reason',
    'requested_new_deadline',
    'status',
    'new_deadline',
    'reviewed_by',
    'reviewed_at',
    'decision_notes',
])]
class TuitionDeferral extends Model
{
    /** @use HasFactory<TuitionDeferralFactory> */
    use HasFactory, RecordsAudit, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeferralStatus::class,
            'requested_new_deadline' => 'date',
            'new_deadline' => 'date',
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
     * The accountant who approved or rejected this request.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * True once approved or rejected — no longer actionable in the review queue.
     */
    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * Approved deferrals whose extended deadline has not yet passed — the ones
     * that lift the access gate as of the given date.
     */
    public function scopeActiveAsOf(Builder $query, \DateTimeInterface $asOf): Builder
    {
        return $query
            ->where('status', DeferralStatus::Approved->value)
            ->whereDate('new_deadline', '>=', $asOf);
    }
}
