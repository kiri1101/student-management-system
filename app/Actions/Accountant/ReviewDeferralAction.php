<?php

namespace App\Actions\Accountant;

use App\Enums\AuditAction;
use App\Enums\DeferralStatus;
use App\Events\DeferralReviewed;
use App\Models\AuditLog;
use App\Models\TuitionDeferral;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewDeferralAction
{
    /**
     * Terminal outcomes an accountant may set on a deferral request.
     *
     * @var list<DeferralStatus>
     */
    private const ALLOWED_DECISIONS = [
        DeferralStatus::Approved,
        DeferralStatus::Rejected,
    ];

    /**
     * Approve or reject a deferral request. Mirrors ReviewPaymentAction: a
     * re-fetch under lock + status re-guard inside a transaction so a concurrent
     * review can't double-decide, an audit row per outcome, and a queued
     * notification to the student after commit. Approval requires the extended
     * deadline (the date the standing engine reads); rejection requires a reason.
     *
     * @throws ValidationException
     */
    public function execute(
        TuitionDeferral $deferral,
        DeferralStatus $decision,
        ?string $newDeadline,
        ?string $notes,
        User $accountant,
    ): TuitionDeferral {
        if (! in_array($decision, self::ALLOWED_DECISIONS, strict: true)) {
            throw ValidationException::withMessages([
                'status' => __('The selected decision is not allowed.'),
            ]);
        }

        if ($decision === DeferralStatus::Approved && ($newDeadline === null || trim($newDeadline) === '')) {
            throw ValidationException::withMessages([
                'new_deadline' => __('An extended deadline is required to approve a deferral.'),
            ]);
        }

        if ($decision === DeferralStatus::Rejected && ($notes === null || trim($notes) === '')) {
            throw ValidationException::withMessages([
                'decision_notes' => __('A reason is required to reject a deferral.'),
            ]);
        }

        return DB::transaction(function () use ($deferral, $decision, $newDeadline, $notes, $accountant): TuitionDeferral {
            $deferral = TuitionDeferral::query()
                ->whereKey($deferral->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($deferral->isTerminal()) {
                throw ValidationException::withMessages([
                    'status' => __('This deferral request has already been reviewed.'),
                ]);
            }

            $deferral->fill([
                'status' => $decision,
                'new_deadline' => $decision === DeferralStatus::Approved ? Carbon::parse($newDeadline)->toDateString() : null,
                'decision_notes' => $notes,
                'reviewed_by' => $accountant->id,
                'reviewed_at' => now(),
            ])->saveQuietly();

            AuditLog::record(
                $decision === DeferralStatus::Approved ? AuditAction::DeferralApproved : AuditAction::DeferralRejected,
                $deferral,
                ['status' => $decision->value, 'new_deadline' => $deferral->new_deadline?->toDateString(), 'notes' => $notes],
                userId: $accountant->id,
            );

            DB::afterCommit(function () use ($deferral, $accountant): void {
                event(new DeferralReviewed($deferral->fresh(), $accountant));
            });

            return $deferral;
        });
    }
}
