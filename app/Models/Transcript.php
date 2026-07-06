<?php

namespace App\Models;

use App\Services\TranscriptService;
use Database\Factories\TranscriptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * An immutable, HMAC-signed snapshot of a student's academic transcript at the
 * moment it was issued (#71). The stored `snapshot` is the source of truth for
 * both the rendered PDF and the public verify endpoint. Immutable after insert
 * (updates and deletes throw, like SchoolReceipt / AuditLog); numbers
 * (`TRN-{year}-{00001}`) come from a per-year locked sequence.
 */
#[Fillable([
    'transcript_number',
    'student_profile_id',
    'matricule',
    'student_name',
    'programme',
    'level',
    'snapshot',
    'content_digest',
    'cgpa',
    'credits_earned',
    'credits_attempted',
    'signature',
    'issued_at',
    'issued_by',
])]
class Transcript extends Model
{
    /** @use HasFactory<TranscriptFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'level' => 'integer',
            'cgpa' => 'decimal:2',
            'credits_earned' => 'integer',
            'credits_attempted' => 'integer',
            'issued_at' => 'datetime',
        ];
    }

    /**
     * A transcript is a verifiable proof — it must never change after issuance,
     * so block Eloquent updates and deletes outright (like SchoolReceipt).
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Transcripts are immutable and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Transcripts are immutable and cannot be deleted.');
        });
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
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Canonical payload the HMAC signs: the transcript number, its issue time,
     * and a digest of the bound snapshot.
     */
    public static function canonicalPayload(string $transcriptNumber, string $issuedAtIso, string $contentDigest): string
    {
        return implode('|', [$transcriptNumber, $issuedAtIso, $contentDigest]);
    }

    /**
     * HMAC-SHA256 of the canonical payload keyed by the application key.
     */
    public static function computeSignature(string $transcriptNumber, string $issuedAtIso, string $contentDigest): string
    {
        return hash_hmac(
            'sha256',
            self::canonicalPayload($transcriptNumber, $issuedAtIso, $contentDigest),
            (string) config('app.key'),
        );
    }

    /**
     * Re-derive this transcript's signature from its currently stored snapshot.
     * The digest is recomputed from `snapshot`, so tampering with the stored
     * snapshot (or the number/date) changes the expected signature.
     */
    public function expectedSignature(): string
    {
        $digest = app(TranscriptService::class)->contentDigest($this->snapshot ?? []);

        return self::computeSignature(
            $this->transcript_number,
            $this->issued_at?->toIso8601String() ?? '',
            $digest,
        );
    }

    /**
     * Constant-time check that the stored signature still matches the bound
     * snapshot. False => forged or tampered.
     */
    public function verifies(): bool
    {
        return hash_equals($this->signature, $this->expectedSignature());
    }

    /**
     * Issue the next transcript number for the given year from the one-row-per-
     * year `transcript_sequences` counter. Caller owns the surrounding
     * transaction; `lockForUpdate()` serializes concurrent issuances. Mirrors
     * SchoolReceipt::nextReceiptNumberForYear (AUDIT.md AUD-006).
     */
    public static function nextTranscriptNumberForYear(int $year): string
    {
        if (! DB::table('transcript_sequences')->where('year', $year)->exists()) {
            DB::table('transcript_sequences')->insertOrIgnore([
                'year' => $year,
                'last_number' => static::highestIssuedNumberForYear($year),
            ]);
        }

        $current = (int) DB::table('transcript_sequences')
            ->where('year', $year)
            ->lockForUpdate()
            ->value('last_number');

        $next = $current + 1;

        DB::table('transcript_sequences')
            ->where('year', $year)
            ->update(['last_number' => $next]);

        return sprintf('TRN-%d-%05d', $year, $next);
    }

    private static function highestIssuedNumberForYear(int $year): int
    {
        return (int) static::query()
            ->where('transcript_number', 'like', "TRN-{$year}-%")
            ->pluck('transcript_number')
            ->map(fn (string $number): int => (int) substr($number, strrpos($number, '-') + 1))
            ->max();
    }
}
