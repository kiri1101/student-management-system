<?php

namespace App\Actions\Accountant;

use App\Enums\AuditAction;
use App\Enums\PaymentStatus;
use App\Events\PaymentReviewed;
use App\Models\AuditLog;
use App\Models\PaymentSubmission;
use App\Models\SchoolReceipt;
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
     * commit. On validation it also mints the immutable, HMAC-signed school
     * receipt inside the same transaction.
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

            if ($decision === PaymentStatus::Validated) {
                $this->issueReceipt($submission, $accountant);
            }

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

    /**
     * Mint the immutable, HMAC-signed school receipt for a just-validated
     * submission — exactly one (the unique FK + the re-review guard guarantee
     * it), bound to the student's matricule, amount, and academic year so the
     * public verify endpoint can prove authenticity and surface reuse.
     */
    private function issueReceipt(PaymentSubmission $submission, User $accountant): void
    {
        $year = (int) $submission->academic_year;
        $matricule = (string) $submission->studentProfile->matricule;
        $receiptNumber = SchoolReceipt::nextReceiptNumberForYear($year);

        $receipt = SchoolReceipt::create([
            'payment_submission_id' => $submission->id,
            'receipt_number' => $receiptNumber,
            'amount_xaf' => $submission->amount_xaf,
            'signature' => SchoolReceipt::computeSignature(
                $receiptNumber,
                $matricule,
                $submission->amount_xaf,
                $submission->academic_year,
            ),
            'issued_at' => now(),
        ]);

        AuditLog::record(
            AuditAction::ReceiptIssued,
            $receipt,
            ['receipt_number' => $receiptNumber, 'payment_submission_id' => $submission->id],
            userId: $accountant->id,
        );
    }
}
