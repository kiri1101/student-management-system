<?php

namespace App\Models;

use App\Enums\StudentStatus;
use App\Models\Concerns\RecordsAudit;
use Database\Factories\StudentProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'matricule',
    'program_offering_id',
    'level',
    'academic_year',
    'enrolled_at',
    'status',
])]
class StudentProfile extends Model
{
    /** @use HasFactory<StudentProfileFactory> */
    use HasFactory, RecordsAudit, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'enrolled_at' => 'date',
            'status' => StudentStatus::class,
        ];
    }

    /**
     * Canonicalize matricules to lowercase so SQLite tests match the case Fortify
     * lowercases the login identifier to (`CanonicalizeUsername` action).
     */
    protected function matricule(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : Str::lower($value),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Resolved withTrashed so enrollments keep rendering even if their
     * offering is soft-deleted out from under them (AUDIT.md AUD-013).
     */
    public function programOffering(): BelongsTo
    {
        return $this->belongsTo(ProgramOffering::class)->withTrashed();
    }

    /**
     * Issue the next matricule for the given year from the one-row-per-year
     * `matricule_sequences` counter. Caller is responsible for the surrounding
     * `DB::transaction`; the `lockForUpdate()` on the sequence row serializes
     * concurrent admits without locking any profile rows, and the counter is
     * immune to force-deleted profiles (a count-based generator was not).
     */
    public static function nextMatriculeForYear(int $year): string
    {
        if (! DB::table('matricule_sequences')->where('year', $year)->exists()) {
            // First admit of the year (or first since this table shipped):
            // seed from the highest number already issued so pre-sequence
            // rows — including trashed ones — are never collided with.
            // insertOrIgnore keeps a concurrent seeder from erroring; both
            // then serialize on the locking read below.
            DB::table('matricule_sequences')->insertOrIgnore([
                'year' => $year,
                'last_number' => static::highestIssuedNumberForYear($year),
            ]);
        }

        $current = (int) DB::table('matricule_sequences')
            ->where('year', $year)
            ->lockForUpdate()
            ->value('last_number');

        $next = $current + 1;

        DB::table('matricule_sequences')
            ->where('year', $year)
            ->update(['last_number' => $next]);

        return sprintf('stm-%d-%04d', $year, $next);
    }

    private static function highestIssuedNumberForYear(int $year): int
    {
        return (int) static::withTrashed()
            ->where('matricule', 'like', "stm-{$year}-%")
            ->pluck('matricule')
            ->map(fn (string $matricule): int => (int) substr($matricule, strrpos($matricule, '-') + 1))
            ->max();
    }
}
