<?php

namespace App\Actions\Accountant;

use App\Enums\AuditAction;
use App\Enums\PaymentStatus;
use App\Events\PaymentReviewed;
use App\Models\AuditLog;
use App\Models\PaymentSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewPaymentAction
{
    /**
     * Terminal outcomes an accountant may set on a submission.
     *
     * @var list<PaymentStatus>
     */
    private const ALLOWED_DECISIONS = [
        PaymentStatus::Validated,
        PaymentStatus::Rejected,
    ];

    /**
     * Apply a terminal decision to a payment submission. Mirrors the hardened
     * SAO decision flow: re-fetch under lock + status re-guard inside a
     * transaction so a concurrent review can't slip past a stale check, an
     * audit row per outcome, and a queued notification to the student after
     * commit. Issuing the tamper-proof school receipt on validation arrives in
     * Phase P3.
     *
     * @throws ValidationException
     */
    public function execute(
        PaymentSubmission $submission,
        PaymentStatus $decision,
        ?string $rejectionReason,
        User $accountant,
    ): PaymentSubmission {
        if (! in_array($decision, self::ALLOWED_DECISIONS, strict: true)) {
            throw ValidationException::withMessages([
                'status' => __('The selected decision is not allowed.'),
            ]);
        }

        if ($decision === PaymentStatus::Rejected && ($rejectionReason === null || trim($rejectionReason) === '')) {
            throw ValidationException::withMessages([
                'rejection_reason' => __('A reason is required to reject a payment.'),
            ]);
        }

        return DB::transaction(function () use ($submission, $decision, $rejectionReason, $accountant): PaymentSubmission {
            // Re-fetch under lock so a concurrent review can't double-decide a
            // submission past a stale status check (mirrors AUDIT.md AUD-001).
            $submission = PaymentSubmission::query()
                ->whereKey($submission->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($submission->isTerminal()) {
                throw ValidationException::withMessages([
                    'status' => __('This payment has already been reviewed.'),
                ]);
            }

            $submission->fill([
                'status' => $decision,
                'reviewed_by' => $accountant->id,
                'reviewed_at' => now(),
                'rejection_reason' => $decision === PaymentStatus::Rejected ? $rejectionReason : null,
            ])->saveQuietly();

            AuditLog::record(
                $decision === PaymentStatus::Validated ? AuditAction::PaymentValidated : AuditAction::PaymentRejected,
                $submission,
                ['status' => $decision->value, 'rejection_reason' => $submission->rejection_reason],
                userId: $accountant->id,
            );

            DB::afterCommit(function () use ($submission, $accountant): void {
                event(new PaymentReviewed($submission->fresh(), $accountant));
            });

            return $submission;
        });
    }
}
