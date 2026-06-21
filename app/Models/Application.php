<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Models\Concerns\RecordsAudit;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An admission application moving through the SAO triage lifecycle
 * (Draft → Submitted → interim → terminal; see {@see ApplicationStatus}).
 *
 * Carries its own snapshot of the applicant's identity at submission time
 * (first_name, last_name, contact_email, phone, date_of_birth,
 * previous_institute) rather than reading live off the owning User, so the
 * record stays faithful to what was submitted even if the account later
 * changes. At most one application per applicant may be "open" at a time
 * (see {@see Application::OPEN_STATUSES}).
 */
#[Fillable([
    'user_id',
    'program_offering_id',
    'level',
    'first_name',
    'last_name',
    'contact_email',
    'phone',
    'date_of_birth',
    'previous_institute',
    'status',
    'submitted_at',
    'decided_at',
    'decided_by_user_id',
    'decision_notes',
])]
class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory, RecordsAudit, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'date_of_birth' => 'date',
            'status' => ApplicationStatus::class,
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Resolved withTrashed so historical applications keep rendering even if
     * their offering is soft-deleted out from under them (AUDIT.md AUD-013).
     *
     * @return BelongsTo<ProgramOffering, $this>
     */
    public function programOffering(): BelongsTo
    {
        return $this->belongsTo(ProgramOffering::class)->withTrashed();
    }

    /**
     * The SAO/staff user who issued the terminal decision, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    /**
     * @return HasMany<ApplicationDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    /**
     * Statuses from which no further transition is allowed.
     *
     * @var list<ApplicationStatus>
     */
    public const TERMINAL_STATUSES = [
        ApplicationStatus::Admitted,
        ApplicationStatus::Rejected,
        ApplicationStatus::Waitlisted,
        ApplicationStatus::Withdrawn,
    ];

    /**
     * Reversible interim statuses owned by the SAO triage flow.
     *
     * @var list<ApplicationStatus>
     */
    public const INTERIM_STATUSES = [
        ApplicationStatus::Submitted,
        ApplicationStatus::UnderReview,
        ApplicationStatus::DocumentsRequested,
    ];

    /**
     * Statuses that count as "in progress" for the one-open-application-per-
     * applicant rule: Draft plus the interim trio.
     *
     * @var list<ApplicationStatus>
     */
    public const OPEN_STATUSES = [
        ApplicationStatus::Draft,
        ...self::INTERIM_STATUSES,
    ];

    /**
     * Whether the application has reached a status from which no further
     * transition is allowed.
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, strict: true);
    }

    /**
     * Transition matrix: Draft → Submitted only (the applicant-side submit);
     * interim → any other interim or terminal; terminal → nothing.
     */
    public function canTransitionTo(ApplicationStatus $next): bool
    {
        if ($this->status === ApplicationStatus::Draft) {
            return $next === ApplicationStatus::Submitted;
        }

        if (! in_array($this->status, self::INTERIM_STATUSES, strict: true)) {
            return false;
        }

        return $next !== ApplicationStatus::Draft;
    }
}
