<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The single, tamper-evident proof of a validated tuition payment. Immutable
 * after issuance (Eloquent updates/deletes throw; no timestamps). Each receipt
 * carries an HMAC-SHA256 `signature` over `receipt_number|matricule|amount_xaf|
 * academic_year` keyed by APP_KEY, so the public verify endpoint can detect any
 * forgery or tampering. Receipt numbers (`RCP-{year}-{00001}`) are issued from a
 * per-year locked sequence. Money is integer XAF.
 */
#[Fillable(['payment_submission_id', 'receipt_number', 'amount_xaf', 'signature', 'issued_at'])]
class SchoolReceipt extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_xaf' => 'integer',
            'issued_at' => 'datetime',
        ];
    }

    /**
     * A receipt is the single proof of payment — it must never change after
     * issuance, so block Eloquent updates and deletes outright (like AuditLog).
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('School receipts are immutable and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('School receipts are immutable and cannot be deleted.');
        });
    }

    /**
     * @return BelongsTo<PaymentSubmission, $this>
     */
    public function paymentSubmission(): BelongsTo
    {
        return $this->belongsTo(PaymentSubmission::class);
    }

    /**
     * Canonical payload the HMAC signs: the receipt number plus the identity it
     * is bound to. Any mismatch between these and the stored signature means
     * the receipt is forged or has been tampered with.
     */
    public static function canonicalPayload(string $receiptNumber, string $matricule, int $amountXaf, string $academicYear): string
    {
        return implode('|', [$receiptNumber, $matricule, $amountXaf, $academicYear]);
    }

    /**
     * HMAC-SHA256 of the canonical payload keyed by the application key. Server
     * side only — no third party ever needs the key to verify, they hit the
     * public verify endpoint which re-derives this.
     */
    public static function computeSignature(string $receiptNumber, string $matricule, int $amountXaf, string $academicYear): string
    {
        return hash_hmac(
            'sha256',
            self::canonicalPayload($receiptNumber, $matricule, $amountXaf, $academicYear),
            (string) config('app.key'),
        );
    }

    /**
     * Re-derive this receipt's signature from its currently bound identity
     * (matricule + academic year on the submission, amount on the receipt).
     */
    public function expectedSignature(): string
    {
        $submission = $this->paymentSubmission;
        $matricule = (string) ($submission?->studentProfile?->matricule ?? '');
        $academicYear = (string) ($submission?->academic_year ?? '');

        return self::computeSignature($this->receipt_number, $matricule, $this->amount_xaf, $academicYear);
    }

    /**
     * Constant-time check that the stored signature still matches the bound
     * identity. False ⇒ forged or tampered.
     */
    public function verifies(): bool
    {
        return hash_equals($this->signature, $this->expectedSignature());
    }

    /**
     * Issue the next receipt number for the given year from the one-row-per-year
     * `receipt_sequences` counter. Caller owns the surrounding transaction; the
     * `lockForUpdate()` serializes concurrent issuances. Mirrors
     * StudentProfile::nextMatriculeForYear (AUDIT.md AUD-006).
     */
    public static function nextReceiptNumberForYear(int $year): string
    {
        if (! DB::table('receipt_sequences')->where('year', $year)->exists()) {
            DB::table('receipt_sequences')->insertOrIgnore([
                'year' => $year,
                'last_number' => static::highestIssuedNumberForYear($year),
            ]);
        }

        $current = (int) DB::table('receipt_sequences')
            ->where('year', $year)
            ->lockForUpdate()
            ->value('last_number');

        $next = $current + 1;

        DB::table('receipt_sequences')
            ->where('year', $year)
            ->update(['last_number' => $next]);

        return sprintf('RCP-%d-%05d', $year, $next);
    }

    private static function highestIssuedNumberForYear(int $year): int
    {
        return (int) static::query()
            ->where('receipt_number', 'like', "RCP-{$year}-%")
            ->pluck('receipt_number')
            ->map(fn (string $number): int => (int) substr($number, strrpos($number, '-') + 1))
            ->max();
    }
}
