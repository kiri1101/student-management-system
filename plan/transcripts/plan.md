# Student Transcript Generation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aggregate a student's published course results into an official, immutable, publicly-verifiable PDF academic transcript (4.0 GPA/CGPA), downloadable by the student and issuable by SAO/admin.

**Architecture:** A pure `TranscriptService` computes the snapshot (group published results by year→semester, credit-weighted GPA/CGPA). An `IssueTranscript` action dedupes by content digest and persists an **immutable, HMAC-signed** `Transcript` record (mirrors `SchoolReceipt`). A `TranscriptPdfRenderer` turns a record into a PDF (mpdf) with an SVG QR pointing at a public, unauthenticated verify endpoint that re-derives the HMAC over the stored snapshot. Authorization is the existing `role:*` route middleware + ownership — no ability gates (ADR-0025).

**Tech Stack:** Laravel 13 / PHP 8.4, Inertia v3 + Vue 3 + PrimeVue/Aura, Pest v4, MySQL (SQLite in tests), Wayfinder, `mpdf/mpdf`, `simplesoftwareio/simple-qrcode`.

**Design source:** `plan/transcripts/design.md`.

## Global Constraints

- **DB:** MySQL local (`student_management`); tests run in-memory SQLite via `RefreshDatabase`. Edit migrations in place; local re-migrates with `migrate:fresh --seed`.
- **Enum columns:** string column + Eloquent enum cast — never native `$table->enum()`. (No enum columns here, but honor it if one arises.)
- **Immutable records:** `Transcript` blocks Eloquent update/delete like `SchoolReceipt`/`AuditLog`; per-year numbers come from a `lockForUpdate()` sequence table (AUD-006 pattern).
- **Authorization:** `role:*` route-group middleware + per-resource ownership. No `Gate::` / ability gates (ADR-0025). The student route resolves the caller's own profile (no id in URL); SAO routes are `role:sao,admin`.
- **UI:** new UI uses **PrimeVue/Aura**, imported **per-page** (no global registration). Icons: `lucide-vue-next` only. Server flash toasts via `Inertia::flash('toast', ['type' => ..., 'message' => ...])`.
- **Routes:** named routes; TypeScript route helpers via Wayfinder barrels (`@/routes/...`). Regenerate after adding routes.
- **Formatting/gates:** run `vendor/bin/pint --dirty --format agent` after touching PHP; `npm run types:check && npm run lint:check` after touching TS/Vue. Run tests scoped: `--testsuite=Unit,Feature` (the Browser suite hangs locally).
- **Dependencies:** the two new composer packages need approval. The implementer subagent is sandbox-restricted and **cannot** run `composer require` — the **orchestrator** installs them at Task 5 kickoff after confirming with the user.
- **Docs:** on ship, run the `docs-refresh` skill (Task 8).

---

### Task 1: `TranscriptService` — snapshot + GPA/CGPA computation

**Files:**
- Create: `app/Services/TranscriptService.php`
- Test: `tests/Feature/Transcripts/TranscriptServiceTest.php`

**Interfaces:**
- Consumes: `CourseResult` (`status`, `final_score`, `grade`, `course`), `Course` (`code`, `title`, `credits`, `semester`, `academic_year`), `StudentProfile` (`matricule`, `user.name`, `programOffering.department.name`, `level`).
- Produces:
  - `public const array GRADE_POINTS = ['A'=>4.0,'B'=>3.0,'C'=>2.0,'D'=>1.0,'F'=>0.0];`
  - `buildSnapshot(StudentProfile $profile, string $generatedByRole): array` — returns the snapshot shape below.
  - `contentDigest(array $snapshot): string` — sha256 over the snapshot minus `meta`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Transcripts/TranscriptServiceTest.php`:

```php
<?php

use App\Models\Course;
use App\Models\CourseResult;
use App\Models\StudentProfile;
use App\Services\TranscriptService;

it('aggregates published results into per-semester GPA and cumulative CGPA', function (): void {
    $profile = StudentProfile::factory()->create();

    // Semester 1 2025/2026: A(3cr)=4.0, B(4cr)=3.0 -> (12+12)/7 = 3.4285 -> 3.43
    $s1 = Course::factory()->approved()->create(['academic_year' => '2025/2026', 'semester' => 1, 'credits' => 3, 'code' => 'AAA100']);
    $s2 = Course::factory()->approved()->create(['academic_year' => '2025/2026', 'semester' => 1, 'credits' => 4, 'code' => 'BBB100']);
    // Semester 2 2025/2026: F(2cr)=0.0 -> GPA 0.00, earns 0 credits
    $s3 = Course::factory()->approved()->create(['academic_year' => '2025/2026', 'semester' => 2, 'credits' => 2, 'code' => 'CCC100']);

    CourseResult::factory()->published()->create(['course_id' => $s1->id, 'student_profile_id' => $profile->id, 'ca_score' => 85, 'exam_score' => 85]); // 85 -> A
    CourseResult::factory()->published()->create(['course_id' => $s2->id, 'student_profile_id' => $profile->id, 'ca_score' => 72, 'exam_score' => 72]); // 72 -> B
    CourseResult::factory()->published()->create(['course_id' => $s3->id, 'student_profile_id' => $profile->id, 'ca_score' => 10, 'exam_score' => 10]); // 10 -> F

    $snapshot = app(TranscriptService::class)->buildSnapshot($profile, 'student');

    expect($snapshot['semesters'])->toHaveCount(2)
        ->and($snapshot['semesters'][0]['gpa'])->toBe(3.43)
        ->and($snapshot['semesters'][0]['credits_earned'])->toBe(7)
        ->and($snapshot['semesters'][0]['credits_attempted'])->toBe(7)
        ->and($snapshot['semesters'][1]['gpa'])->toBe(0.0)
        ->and($snapshot['semesters'][1]['credits_earned'])->toBe(0)
        ->and($snapshot['semesters'][1]['credits_attempted'])->toBe(2)
        ->and($snapshot['cumulative']['cgpa'])->toBe(2.67) // 24/9
        ->and($snapshot['cumulative']['credits_earned'])->toBe(7)
        ->and($snapshot['cumulative']['credits_attempted'])->toBe(9)
        ->and($snapshot['cumulative']['total_courses'])->toBe(3);
});

it('excludes draft and unscored published results', function (): void {
    $profile = StudentProfile::factory()->create();
    $scored = Course::factory()->approved()->create(['credits' => 3]);
    $drafted = Course::factory()->approved()->create(['credits' => 3]);
    $unscored = Course::factory()->approved()->create(['credits' => 3]);

    CourseResult::factory()->published()->create(['course_id' => $scored->id, 'student_profile_id' => $profile->id, 'ca_score' => 80, 'exam_score' => 80]);
    CourseResult::factory()->draft()->create(['course_id' => $drafted->id, 'student_profile_id' => $profile->id]);
    CourseResult::factory()->published()->unscored()->create(['course_id' => $unscored->id, 'student_profile_id' => $profile->id]);

    $snapshot = app(TranscriptService::class)->buildSnapshot($profile, 'student');

    expect($snapshot['cumulative']['total_courses'])->toBe(1);
});

it('produces the same digest for unchanged content regardless of generation metadata', function (): void {
    $profile = StudentProfile::factory()->create();
    $course = Course::factory()->approved()->create(['credits' => 3]);
    CourseResult::factory()->published()->create(['course_id' => $course->id, 'student_profile_id' => $profile->id, 'ca_score' => 80, 'exam_score' => 80]);

    $service = app(TranscriptService::class);
    $a = $service->contentDigest($service->buildSnapshot($profile, 'student'));
    $b = $service->contentDigest($service->buildSnapshot($profile, 'sao'));

    expect($a)->toBe($b);
});

it('returns an empty semester list for a student with no published results', function (): void {
    $profile = StudentProfile::factory()->create();

    expect(app(TranscriptService::class)->buildSnapshot($profile, 'student')['semesters'])->toBe([]);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Transcripts/TranscriptServiceTest.php --compact`
Expected: FAIL — `Class "App\Services\TranscriptService" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Services/TranscriptService.php`:

```php
<?php

namespace App\Services;

use App\Enums\ResultStatus;
use App\Models\CourseResult;
use App\Models\StudentProfile;

/**
 * Computes a student's academic transcript from their published course results:
 * groups by academic year -> semester, joins course metadata, and derives a
 * credit-weighted GPA per semester plus a cumulative CGPA on the 4.0 scale.
 * Pure computation — no persistence. The resulting snapshot is the source of
 * truth stored on an immutable Transcript record and rendered to PDF.
 */
class TranscriptService
{
    /**
     * Grade-point value per letter grade on the 4.0 scale. Whole points only —
     * the grading scheme has no +/- tiers (see CourseResult::grade).
     *
     * @var array<string, float>
     */
    public const array GRADE_POINTS = [
        'A' => 4.0,
        'B' => 3.0,
        'C' => 2.0,
        'D' => 1.0,
        'F' => 0.0,
    ];

    /**
     * Build the full transcript snapshot for a student. Only published results
     * with both marks present (a real final score + grade) are included; draft
     * and unscored results never appear. Semesters are ordered by academic year
     * then semester; courses within a semester by code. Returns an empty
     * `semesters` list when there is nothing to report.
     *
     * @return array{
     *     student: array{matricule: string|null, name: string|null, programme: string|null, level: int|null},
     *     semesters: list<array{academic_year: string, semester: int, courses: list<array{code: string, title: string, credits: int, score: int, grade: string, points: float}>, gpa: float, credits_earned: int, credits_attempted: int}>,
     *     cumulative: array{cgpa: float, credits_earned: int, credits_attempted: int, total_courses: int},
     *     meta: array{generated_at: string, generated_by_role: string}
     * }
     */
    public function buildSnapshot(StudentProfile $profile, string $generatedByRole): array
    {
        $results = CourseResult::query()
            ->where('student_profile_id', $profile->id)
            ->where('status', ResultStatus::Published->value)
            ->with('course')
            ->get()
            ->filter(fn (CourseResult $result): bool => $result->final_score !== null && $result->course !== null)
            ->sortBy([
                fn (CourseResult $r) => $r->course->academic_year,
                fn (CourseResult $r) => $r->course->semester,
                fn (CourseResult $r) => $r->course->code,
            ]);

        $grouped = $results->groupBy(fn (CourseResult $r): string => $r->course->academic_year.'|'.$r->course->semester);

        $semesters = [];
        $cumQualityPoints = 0.0;
        $cumCredits = 0;
        $cumEarned = 0;
        $totalCourses = 0;

        foreach ($grouped as $rows) {
            $courses = [];
            $semQualityPoints = 0.0;
            $semCredits = 0;
            $semEarned = 0;

            foreach ($rows as $result) {
                $grade = $result->grade;
                $points = self::GRADE_POINTS[$grade];
                $credits = (int) $result->course->credits;

                $courses[] = [
                    'code' => $result->course->code,
                    'title' => $result->course->title,
                    'credits' => $credits,
                    'score' => (int) $result->final_score,
                    'grade' => $grade,
                    'points' => $points,
                ];

                $semQualityPoints += $points * $credits;
                $semCredits += $credits;

                if ($grade !== 'F') {
                    $semEarned += $credits;
                }
            }

            $first = $rows->first();

            $semesters[] = [
                'academic_year' => $first->course->academic_year,
                'semester' => (int) $first->course->semester,
                'courses' => $courses,
                'gpa' => $this->weightedAverage($semQualityPoints, $semCredits),
                'credits_earned' => $semEarned,
                'credits_attempted' => $semCredits,
            ];

            $cumQualityPoints += $semQualityPoints;
            $cumCredits += $semCredits;
            $cumEarned += $semEarned;
            $totalCourses += count($courses);
        }

        return [
            'student' => [
                'matricule' => $profile->matricule,
                'name' => $profile->user?->name,
                'programme' => $profile->programOffering?->department?->name,
                'level' => $profile->level,
            ],
            'semesters' => $semesters,
            'cumulative' => [
                'cgpa' => $this->weightedAverage($cumQualityPoints, $cumCredits),
                'credits_earned' => $cumEarned,
                'credits_attempted' => $cumCredits,
                'total_courses' => $totalCourses,
            ],
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'generated_by_role' => $generatedByRole,
            ],
        ];
    }

    /**
     * Stable SHA-256 over the snapshot's identity + academic content. The `meta`
     * block (issue time + issuer) is excluded so re-issuing the same results for
     * the same student yields the same digest and is deduped. Encoding is
     * deterministic across the DB JSON round-trip (int/float normalization), so
     * the digest recomputed at verify time matches the one computed at issue.
     *
     * @param array<string, mixed> $snapshot
     */
    public function contentDigest(array $snapshot): string
    {
        $stable = $snapshot;
        unset($stable['meta']);

        return hash('sha256', json_encode($stable, JSON_THROW_ON_ERROR));
    }

    private function weightedAverage(float $qualityPoints, int $credits): float
    {
        return round($credits > 0 ? $qualityPoints / $credits : 0.0, 2);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Transcripts/TranscriptServiceTest.php --compact`
Expected: PASS (4 passed).

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/TranscriptService.php tests/Feature/Transcripts/TranscriptServiceTest.php
git commit -m "feat(transcripts): TranscriptService — snapshot + 4.0 GPA/CGPA (#71)"
```

---

### Task 2: `transcripts` + `transcript_sequences` tables, `Transcript` model, factory

**Files:**
- Create: `database/migrations/2026_07_06_100000_create_transcripts_table.php`
- Create: `database/migrations/2026_07_06_100001_create_transcript_sequences_table.php`
- Create: `app/Models/Transcript.php`
- Create: `database/factories/TranscriptFactory.php`
- Test: `tests/Feature/Transcripts/TranscriptModelTest.php`

**Interfaces:**
- Consumes: `TranscriptService::contentDigest()` (Task 1), `StudentProfile`, `User`.
- Produces (used by later tasks):
  - `Transcript::nextTranscriptNumberForYear(int $year): string` → `TRN-{year}-{00001}`.
  - `Transcript::computeSignature(string $number, string $issuedAtIso, string $digest): string`.
  - `Transcript::verifies(): bool`; relations `studentProfile()`, `issuer()`.
  - `TranscriptFactory` producing a self-consistent, `verifies()`-true record.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Transcripts/TranscriptModelTest.php`:

```php
<?php

use App\Models\Transcript;
use Illuminate\Support\Facades\DB;

it('is immutable — updates and deletes throw', function (): void {
    $transcript = Transcript::factory()->create();

    expect(fn () => $transcript->update(['cgpa' => 1.0]))->toThrow(RuntimeException::class)
        ->and(fn () => $transcript->delete())->toThrow(RuntimeException::class);
});

it('issues sequential per-year transcript numbers', function (): void {
    expect(Transcript::nextTranscriptNumberForYear(2026))->toBe('TRN-2026-00001')
        ->and(Transcript::nextTranscriptNumberForYear(2026))->toBe('TRN-2026-00002')
        ->and(Transcript::nextTranscriptNumberForYear(2027))->toBe('TRN-2027-00001');
});

it('verifies an untampered record and rejects a tampered snapshot', function (): void {
    $transcript = Transcript::factory()->create();

    expect($transcript->verifies())->toBeTrue();

    // Tamper the stored snapshot directly, bypassing the immutable model.
    DB::table('transcripts')->where('id', $transcript->id)->update([
        'snapshot' => json_encode(['student' => ['matricule' => 'hacked'], 'semesters' => [], 'cumulative' => []]),
    ]);

    expect($transcript->fresh()->verifies())->toBeFalse();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Transcripts/TranscriptModelTest.php --compact`
Expected: FAIL — `Class "App\Models\Transcript" not found`.

- [ ] **Step 3: Write the migrations**

Create `database/migrations/2026_07_06_100000_create_transcripts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An immutable, HMAC-signed snapshot of a student's transcript at issue time
     * (#71). The `snapshot` JSON is the source of truth for the rendered PDF and
     * the public verify endpoint; `content_digest` drives dedupe. Immutable once
     * written (the model blocks update/delete like school_receipts).
     */
    public function up(): void
    {
        Schema::create('transcripts', function (Blueprint $table) {
            $table->id();
            $table->string('transcript_number')->unique();
            $table->foreignId('student_profile_id')->constrained()->restrictOnDelete();
            $table->string('matricule');
            $table->string('student_name')->nullable();
            $table->string('programme')->nullable();
            $table->unsignedInteger('level')->nullable();
            $table->json('snapshot');
            $table->string('content_digest', 64);
            $table->decimal('cgpa', 3, 2);
            $table->unsignedInteger('credits_earned');
            $table->unsignedInteger('credits_attempted');
            $table->string('signature');
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['student_profile_id', 'content_digest']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcripts');
    }
};
```

Create `database/migrations/2026_07_06_100001_create_transcript_sequences_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per academic year backing transcript-number generation. Query
     * builder only — a counter, not domain data. Mirrors receipt_sequences
     * (AUDIT.md AUD-006).
     */
    public function up(): void
    {
        Schema::create('transcript_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_sequences');
    }
};
```

- [ ] **Step 4: Write the `Transcript` model**

Create `app/Models/Transcript.php`:

```php
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
```

- [ ] **Step 5: Write the factory**

Create `database/factories/TranscriptFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\StudentProfile;
use App\Models\Transcript;
use App\Services\TranscriptService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transcript>
 */
class TranscriptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $snapshot = [
            'student' => ['matricule' => 'stm-2025-0001', 'name' => 'Test Student', 'programme' => 'Computer Science', 'level' => 200],
            'semesters' => [[
                'academic_year' => '2025/2026',
                'semester' => 1,
                'courses' => [['code' => 'CSC101', 'title' => 'Intro', 'credits' => 3, 'score' => 82, 'grade' => 'A', 'points' => 4.0]],
                'gpa' => 4.0,
                'credits_earned' => 3,
                'credits_attempted' => 3,
            ]],
            'cumulative' => ['cgpa' => 4.0, 'credits_earned' => 3, 'credits_attempted' => 3, 'total_courses' => 1],
            'meta' => ['generated_at' => now()->toIso8601String(), 'generated_by_role' => 'student'],
        ];

        $issuedAt = now();
        $number = 'TRN-'.$issuedAt->year.'-'.fake()->unique()->numerify('#####');
        $digest = app(TranscriptService::class)->contentDigest($snapshot);

        return [
            'transcript_number' => $number,
            'student_profile_id' => fn () => StudentProfile::factory(),
            'matricule' => $snapshot['student']['matricule'],
            'student_name' => $snapshot['student']['name'],
            'programme' => $snapshot['student']['programme'],
            'level' => $snapshot['student']['level'],
            'snapshot' => $snapshot,
            'content_digest' => $digest,
            'cgpa' => 4.0,
            'credits_earned' => 3,
            'credits_attempted' => 3,
            'signature' => Transcript::computeSignature($number, $issuedAt->toIso8601String(), $digest),
            'issued_at' => $issuedAt,
            'issued_by' => null,
        ];
    }
}
```

- [ ] **Step 6: Run migrations and tests**

Run: `php artisan migrate --no-interaction` then `php artisan test tests/Feature/Transcripts/TranscriptModelTest.php --compact`
Expected: PASS (3 passed).

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Transcript.php database/migrations/2026_07_06_1000*_*.php database/factories/TranscriptFactory.php tests/Feature/Transcripts/TranscriptModelTest.php
git commit -m "feat(transcripts): immutable HMAC-signed Transcript model + sequence (#71)"
```

---

### Task 3: `IssueTranscript` action + `AuditAction::TranscriptGenerated`

**Files:**
- Create: `app/Actions/IssueTranscript.php`
- Modify: `app/Enums/AuditAction.php` (add one case)
- Test: `tests/Feature/Transcripts/IssueTranscriptTest.php`

**Interfaces:**
- Consumes: `TranscriptService` (Task 1), `Transcript` (Task 2), `AuditLog::record()`.
- Produces: `IssueTranscript::execute(StudentProfile $profile, User $issuedBy, string $generatedByRole): ?Transcript` — reuses an existing record on unchanged content (deduped by digest), else mints + audits a new one; `null` when no published results.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Transcripts/IssueTranscriptTest.php`:

```php
<?php

use App\Actions\IssueTranscript;
use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseResult;
use App\Models\StudentProfile;
use App\Models\Transcript;
use App\Models\User;

function issueFor(StudentProfile $profile): ?Transcript
{
    return app(IssueTranscript::class)->execute($profile, User::factory()->create(), 'student');
}

it('issues a signed transcript and audits it', function (): void {
    $profile = StudentProfile::factory()->create();
    $course = Course::factory()->approved()->create(['credits' => 3]);
    CourseResult::factory()->published()->create(['course_id' => $course->id, 'student_profile_id' => $profile->id, 'ca_score' => 80, 'exam_score' => 80]);

    $transcript = issueFor($profile);

    expect($transcript)->not->toBeNull()
        ->and($transcript->verifies())->toBeTrue()
        ->and($transcript->transcript_number)->toStartWith('TRN-')
        ->and((float) $transcript->cgpa)->toBe(4.0)
        ->and(AuditLog::where('action', AuditAction::TranscriptGenerated->value)->where('subject_id', $transcript->id)->exists())->toBeTrue();
});

it('returns null and issues nothing when there are no published results', function (): void {
    $profile = StudentProfile::factory()->create();

    expect(issueFor($profile))->toBeNull()
        ->and(Transcript::count())->toBe(0);
});

it('dedupes unchanged content and mints a new record when results change', function (): void {
    $profile = StudentProfile::factory()->create();
    $c1 = Course::factory()->approved()->create(['credits' => 3]);
    CourseResult::factory()->published()->create(['course_id' => $c1->id, 'student_profile_id' => $profile->id, 'ca_score' => 80, 'exam_score' => 80]);

    $first = issueFor($profile);
    $again = issueFor($profile);

    expect($again->id)->toBe($first->id)
        ->and($again->transcript_number)->toBe($first->transcript_number)
        ->and(Transcript::count())->toBe(1)
        ->and(AuditLog::where('action', AuditAction::TranscriptGenerated->value)->count())->toBe(1);

    $c2 = Course::factory()->approved()->create(['credits' => 3]);
    CourseResult::factory()->published()->create(['course_id' => $c2->id, 'student_profile_id' => $profile->id, 'ca_score' => 70, 'exam_score' => 70]);

    $third = issueFor($profile);

    expect($third->id)->not->toBe($first->id)
        ->and(Transcript::count())->toBe(2);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Transcripts/IssueTranscriptTest.php --compact`
Expected: FAIL — `Class "App\Actions\IssueTranscript" not found`.

- [ ] **Step 3: Add the audit action**

In `app/Enums/AuditAction.php`, add after the `DisputeResolved` case (last case in the enum):

```php
    case DisputeResolved = 'dispute_resolved';
    case TranscriptGenerated = 'transcript_generated';
```

- [ ] **Step 4: Write the action**

Create `app/Actions/IssueTranscript.php`:

```php
<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\StudentProfile;
use App\Models\Transcript;
use App\Models\User;
use App\Services\TranscriptService;
use Illuminate\Support\Facades\DB;

/**
 * Issues a student's official transcript: builds the snapshot, and returns an
 * immutable, signed Transcript record for it — reusing an existing record when
 * the academic content is unchanged (deduped by content digest), otherwise
 * minting a new numbered, audited one. Returns null when the student has no
 * published results (the caller declines to issue an empty document).
 */
class IssueTranscript
{
    public function __construct(private TranscriptService $transcripts) {}

    public function execute(StudentProfile $profile, User $issuedBy, string $generatedByRole): ?Transcript
    {
        $snapshot = $this->transcripts->buildSnapshot($profile, $generatedByRole);

        if ($snapshot['semesters'] === []) {
            return null;
        }

        $digest = $this->transcripts->contentDigest($snapshot);

        $existing = Transcript::query()
            ->where('student_profile_id', $profile->id)
            ->where('content_digest', $digest)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($profile, $issuedBy, $snapshot, $digest): Transcript {
            $issuedAt = now();
            $number = Transcript::nextTranscriptNumberForYear($issuedAt->year);

            $transcript = Transcript::create([
                'transcript_number' => $number,
                'student_profile_id' => $profile->id,
                'matricule' => $snapshot['student']['matricule'],
                'student_name' => $snapshot['student']['name'],
                'programme' => $snapshot['student']['programme'],
                'level' => $snapshot['student']['level'],
                'snapshot' => $snapshot,
                'content_digest' => $digest,
                'cgpa' => $snapshot['cumulative']['cgpa'],
                'credits_earned' => $snapshot['cumulative']['credits_earned'],
                'credits_attempted' => $snapshot['cumulative']['credits_attempted'],
                'signature' => Transcript::computeSignature($number, $issuedAt->toIso8601String(), $digest),
                'issued_at' => $issuedAt,
                'issued_by' => $issuedBy->id,
            ]);

            AuditLog::record(
                AuditAction::TranscriptGenerated,
                $transcript,
                ['transcript_number' => $number, 'student_profile_id' => $profile->id],
                userId: $issuedBy->id,
            );

            return $transcript;
        });
    }
}
```

> **Note (accepted minor race):** the dedupe existence check sits before the transaction, so two concurrent first-generations of identical content could both insert (two valid records, different numbers). This is benign — both verify, and the next generation dedupes to the first found — so no lock is added.

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Transcripts/IssueTranscriptTest.php --compact`
Expected: PASS (3 passed).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/IssueTranscript.php app/Enums/AuditAction.php tests/Feature/Transcripts/IssueTranscriptTest.php
git commit -m "feat(transcripts): IssueTranscript action with content dedupe + audit (#71)"
```

---

### Task 4: Public verification — controller, route, `Verify.vue`

Placed before the PDF renderer because the renderer's QR points at `route('transcripts.verify', ...)`, which this task registers.

**Files:**
- Create: `app/Http/Controllers/Transcripts/VerifyTranscriptController.php`
- Modify: `routes/web.php` (public route, next to `receipts.verify`)
- Create: `resources/js/pages/transcripts/Verify.vue`
- Test: `tests/Feature/Transcripts/VerifyTranscriptTest.php`

**Interfaces:**
- Consumes: `Transcript` (Task 2).
- Produces: named route `transcripts.verify` (param `transcript_number`); Inertia page `transcripts/Verify` with props `{ transcriptNumber, valid, transcript }`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Transcripts/VerifyTranscriptTest.php`:

```php
<?php

use App\Models\Transcript;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

it('shows the summary for an authentic transcript', function (): void {
    $transcript = Transcript::factory()->create();

    $this->get(route('transcripts.verify', $transcript->transcript_number))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('transcripts/Verify')
            ->where('valid', true)
            ->where('transcript.transcript_number', $transcript->transcript_number));
});

it('reads invalid for a tampered snapshot without leaking data', function (): void {
    $transcript = Transcript::factory()->create();
    DB::table('transcripts')->where('id', $transcript->id)->update(['snapshot' => json_encode(['semesters' => []])]);

    $this->get(route('transcripts.verify', $transcript->transcript_number))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('valid', false)->where('transcript', null));
});

it('reads invalid for an unknown number', function (): void {
    $this->get(route('transcripts.verify', 'TRN-2099-99999'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('valid', false)->where('transcript', null));
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Transcripts/VerifyTranscriptTest.php --compact`
Expected: FAIL — route `transcripts.verify` not defined.

- [ ] **Step 3: Write the controller**

Create `app/Http/Controllers/Transcripts/VerifyTranscriptController.php`:

```php
<?php

namespace App\Http\Controllers\Transcripts;

use App\Http\Controllers\Controller;
use App\Models\Transcript;
use Inertia\Inertia;
use Inertia\Response;

class VerifyTranscriptController extends Controller
{
    /**
     * Public, unauthenticated transcript verification (#71). Re-derives the HMAC
     * from the stored snapshot and shows the transcript summary only when it is
     * authentic. An unknown number and a tampered/forged record both read
     * "invalid" (no oracle for which transcript numbers exist).
     */
    public function __invoke(string $transcriptNumber): Response
    {
        $transcript = Transcript::query()
            ->where('transcript_number', $transcriptNumber)
            ->first();

        $valid = $transcript !== null && $transcript->verifies();

        return Inertia::render('transcripts/Verify', [
            'transcriptNumber' => $transcriptNumber,
            'valid' => $valid,
            'transcript' => $valid ? [
                'transcript_number' => $transcript->transcript_number,
                'student_name' => $transcript->student_name,
                'matricule' => $transcript->matricule,
                'programme' => $transcript->programme,
                'level' => $transcript->level,
                'cgpa' => (float) $transcript->cgpa,
                'credits_earned' => $transcript->credits_earned,
                'credits_attempted' => $transcript->credits_attempted,
                'issued_at' => $transcript->issued_at?->toIso8601String(),
                'semesters' => collect($transcript->snapshot['semesters'] ?? [])
                    ->map(fn (array $semester): array => [
                        'academic_year' => $semester['academic_year'],
                        'semester' => $semester['semester'],
                        'gpa' => $semester['gpa'],
                        'credits_earned' => $semester['credits_earned'],
                        'credits_attempted' => $semester['credits_attempted'],
                    ])->all(),
            ] : null,
        ]);
    }
}
```

- [ ] **Step 4: Register the route**

In `routes/web.php`, add the import next to the existing `VerifyReceiptController` import:

```php
use App\Http\Controllers\Receipts\VerifyReceiptController;
use App\Http\Controllers\Transcripts\VerifyTranscriptController;
```

And add the route immediately after the `receipts/verify/...` route block:

```php
// Public, unauthenticated transcript verification (#71). Throttled like the
// receipt endpoint; re-derives the HMAC over the stored snapshot and reveals
// only the transcript summary when authentic (no existence oracle).
Route::get('transcripts/verify/{transcript_number}', VerifyTranscriptController::class)
    ->middleware('throttle:lookups')
    ->name('transcripts.verify');
```

- [ ] **Step 5: Write the verify page**

Create `resources/js/pages/transcripts/Verify.vue`:

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ShieldAlert, ShieldCheck } from 'lucide-vue-next';

type VerifiedSemester = {
    academic_year: string;
    semester: number;
    gpa: number;
    credits_earned: number;
    credits_attempted: number;
};

type VerifiedTranscript = {
    transcript_number: string;
    student_name: string | null;
    matricule: string | null;
    programme: string | null;
    level: number | null;
    cgpa: number;
    credits_earned: number;
    credits_attempted: number;
    issued_at: string | null;
    semesters: VerifiedSemester[];
};

defineProps<{
    transcriptNumber: string;
    valid: boolean;
    transcript: VerifiedTranscript | null;
}>();

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString() : '—';
}
</script>

<template>
    <Head title="Verify transcript" />

    <div class="flex min-h-screen items-center justify-center bg-gray-50 p-4 dark:bg-gray-950">
        <div class="w-full max-w-lg overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-gray-900">
            <div
                v-if="valid && transcript"
                class="flex flex-col items-center gap-1 bg-green-50 p-6 text-center dark:bg-green-950/40"
            >
                <ShieldCheck class="size-10 text-green-600" />
                <h1 class="text-lg font-semibold text-green-800 dark:text-green-300">Authentic transcript</h1>
                <p class="font-mono text-sm text-gray-500">{{ transcript.transcript_number }}</p>
            </div>
            <div
                v-else
                class="flex flex-col items-center gap-1 bg-red-50 p-6 text-center dark:bg-red-950/40"
            >
                <ShieldAlert class="size-10 text-red-600" />
                <h1 class="text-lg font-semibold text-red-800 dark:text-red-300">Invalid transcript</h1>
                <p class="font-mono text-sm text-gray-500">{{ transcriptNumber }}</p>
            </div>

            <div v-if="valid && transcript" class="space-y-4 p-6">
                <p class="text-sm text-muted-foreground">
                    This transcript is genuine and was issued as shown below. Confirm these details
                    match the document presented.
                </p>
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">Student</dt>
                        <dd>{{ transcript.student_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Matricule</dt>
                        <dd class="font-mono">{{ transcript.matricule ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Programme</dt>
                        <dd>{{ transcript.programme ?? '—' }} · L{{ transcript.level }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Issued</dt>
                        <dd>{{ formatDate(transcript.issued_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">CGPA</dt>
                        <dd class="font-medium">{{ transcript.cgpa.toFixed(2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Credits earned</dt>
                        <dd>{{ transcript.credits_earned }} / {{ transcript.credits_attempted }}</dd>
                    </div>
                </dl>

                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left">
                            <tr>
                                <th class="p-2">Year</th>
                                <th class="p-2">Semester</th>
                                <th class="p-2">GPA</th>
                                <th class="p-2">Credits</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(s, i) in transcript.semesters" :key="i" class="border-t">
                                <td class="p-2">{{ s.academic_year }}</td>
                                <td class="p-2">{{ s.semester }}</td>
                                <td class="p-2">{{ s.gpa.toFixed(2) }}</td>
                                <td class="p-2">{{ s.credits_earned }}/{{ s.credits_attempted }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div v-else class="p-6">
                <p class="text-sm text-muted-foreground">
                    We could not verify this transcript. It may have been altered, forged, or the code
                    may be incorrect. Do not accept it as an official record — contact student affairs.
                </p>
            </div>
        </div>
    </div>
</template>
```

- [ ] **Step 6: Run tests + frontend gate**

Run: `php artisan test tests/Feature/Transcripts/VerifyTranscriptTest.php --compact`
Expected: PASS (3 passed).
Run: `npm run types:check && npm run lint:check`
Expected: no errors.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Transcripts/VerifyTranscriptController.php routes/web.php resources/js/pages/transcripts/Verify.vue tests/Feature/Transcripts/VerifyTranscriptTest.php
git commit -m "feat(transcripts): public HMAC verification endpoint + page (#71)"
```

---

### Task 5: PDF renderer + Blade template + composer deps

**Orchestrator action first:** confirm with the user, then install the two packages (the implementer subagent cannot):

```bash
composer require mpdf/mpdf simplesoftwareio/simple-qrcode
```

> mpdf needs `ext-mbstring` + `ext-gd` (present on Laragon). If the PDF test fails in CI, add `extensions: mbstring, gd` to the `shivammathur/setup-php` step in the CI workflow.

**Files:**
- Create: `app/Services/TranscriptPdfRenderer.php`
- Create: `resources/views/pdf/transcript.blade.php`
- Test: `tests/Feature/Transcripts/TranscriptPdfRendererTest.php`

**Interfaces:**
- Consumes: `Transcript` (Task 2), `route('transcripts.verify')` (Task 4).
- Produces: `TranscriptPdfRenderer::render(Transcript $transcript): string` (PDF bytes).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Transcripts/TranscriptPdfRendererTest.php`:

```php
<?php

use App\Models\Transcript;
use App\Services\TranscriptPdfRenderer;

it('renders a transcript to PDF bytes', function (): void {
    $transcript = Transcript::factory()->create();

    expect(app(TranscriptPdfRenderer::class)->render($transcript))->toStartWith('%PDF');
});

it('embeds the verify URL and transcript number in the transcript view', function (): void {
    $transcript = Transcript::factory()->create();

    $html = view('pdf.transcript', [
        'transcript' => $transcript,
        'snapshot' => $transcript->snapshot,
        'verifyUrl' => route('transcripts.verify', $transcript->transcript_number),
        'qrSvg' => '<svg></svg>',
    ])->render();

    expect($html)->toContain($transcript->transcript_number)
        ->and($html)->toContain('transcripts/verify');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Transcripts/TranscriptPdfRendererTest.php --compact`
Expected: FAIL — view `pdf.transcript` not found / renderer class missing.

- [ ] **Step 3: Write the Blade template**

Create `resources/views/pdf/transcript.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; color: #111; font-size: 11px; }
        .brand { color: #047857; font-size: 20px; font-weight: bold; }
        .institution { font-size: 12px; color: #555; margin-bottom: 2px; }
        .doc-title { text-align: center; font-size: 15px; font-weight: bold; margin: 12px 0 4px; text-transform: uppercase; letter-spacing: 1px; }
        table.identity { width: 100%; margin: 10px 0 6px; }
        table.identity td { padding: 2px 4px; font-size: 11px; }
        table.identity .label { color: #555; width: 18%; }
        .sem-heading { font-weight: bold; font-size: 11px; margin-top: 14px; color: #065f46; }
        table.results { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.results th { background: #ecfdf5; color: #065f46; text-align: left; padding: 4px 6px; border-bottom: 1px solid #a7f3d0; font-size: 10px; }
        table.results td { padding: 4px 6px; border-bottom: 1px solid #eee; font-size: 10px; }
        .sem-summary { text-align: right; font-size: 10px; color: #333; padding: 4px 6px; font-weight: bold; }
        .cumulative { margin-top: 16px; padding: 8px; background: #f0fdf4; border: 1px solid #a7f3d0; font-weight: bold; font-size: 11px; }
        .verify { margin-top: 18px; font-size: 9px; color: #555; }
        .verify .qr { float: right; width: 120px; }
    </style>
</head>
<body>
    <div>
        <span class="brand">SchuLyf</span>
        <div class="institution">SchuLyf University · Yaoundé, Cameroon</div>
    </div>
    <div class="doc-title">Official Academic Transcript</div>

    <table class="identity">
        <tr>
            <td class="label">Student</td><td>{{ $snapshot['student']['name'] ?? '—' }}</td>
            <td class="label">Transcript №</td><td>{{ $transcript->transcript_number }}</td>
        </tr>
        <tr>
            <td class="label">Matricule</td><td>{{ $snapshot['student']['matricule'] ?? '—' }}</td>
            <td class="label">Issued</td><td>{{ $transcript->issued_at?->toFormattedDateString() }}</td>
        </tr>
        <tr>
            <td class="label">Programme</td><td>{{ $snapshot['student']['programme'] ?? '—' }}</td>
            <td class="label">Current level</td><td>{{ $snapshot['student']['level'] ?? '—' }}</td>
        </tr>
    </table>

    @foreach ($snapshot['semesters'] as $semester)
        <div class="sem-heading">{{ $semester['academic_year'] }} · Semester {{ $semester['semester'] }}</div>
        <table class="results">
            <thead>
                <tr>
                    <th>Code</th><th>Course title</th><th>Credits</th><th>Score</th><th>Grade</th><th>Points</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($semester['courses'] as $course)
                    <tr>
                        <td>{{ $course['code'] }}</td>
                        <td>{{ $course['title'] }}</td>
                        <td>{{ $course['credits'] }}</td>
                        <td>{{ $course['score'] }}%</td>
                        <td>{{ $course['grade'] }}</td>
                        <td>{{ number_format($course['points'], 1) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="6" class="sem-summary">
                        Semester GPA {{ number_format($semester['gpa'], 2) }} ·
                        Credits {{ $semester['credits_earned'] }}/{{ $semester['credits_attempted'] }}
                    </td>
                </tr>
            </tbody>
        </table>
    @endforeach

    <div class="cumulative">
        Cumulative: CGPA {{ number_format($snapshot['cumulative']['cgpa'], 2) }} ·
        Credits earned {{ $snapshot['cumulative']['credits_earned'] }} /
        attempted {{ $snapshot['cumulative']['credits_attempted'] }} ·
        {{ $snapshot['cumulative']['total_courses'] }} courses
    </div>

    <div class="verify">
        <div class="qr">{!! $qrSvg !!}</div>
        <p><strong>Verify authenticity</strong> at:<br>{{ $verifyUrl }}</p>
        <p>This is an official SchuLyf transcript. Scan the QR or visit the URL above to confirm it has not been altered.</p>
    </div>
</body>
</html>
```

- [ ] **Step 4: Write the renderer**

Create `app/Services/TranscriptPdfRenderer.php`:

```php
<?php

namespace App\Services;

use App\Models\Transcript;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Renders an issued transcript to PDF bytes: the Blade template with an embedded
 * SVG QR pointing at the public verify URL, laid out by mpdf with repeating
 * table headers and a per-page footer.
 */
class TranscriptPdfRenderer
{
    public function render(Transcript $transcript): string
    {
        $verifyUrl = route('transcripts.verify', $transcript->transcript_number);

        $qrSvg = (string) QrCode::format('svg')->size(120)->margin(0)->generate($verifyUrl);

        $html = View::make('pdf.transcript', [
            'transcript' => $transcript,
            'snapshot' => $transcript->snapshot,
            'verifyUrl' => $verifyUrl,
            'qrSvg' => $qrSvg,
        ])->render();

        $tempDir = storage_path('app/mpdf');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
        ]);

        $mpdf->SetHTMLFooter(
            '<div style="text-align:center;font-size:8px;color:#999;">'
            .e($transcript->transcript_number).' · Page {PAGENO}/{nbpg}</div>'
        );

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Transcripts/TranscriptPdfRendererTest.php --compact`
Expected: PASS (2 passed).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/TranscriptPdfRenderer.php resources/views/pdf/transcript.blade.php tests/Feature/Transcripts/TranscriptPdfRendererTest.php composer.json composer.lock
git commit -m "feat(transcripts): mpdf PDF renderer + QR-verified template (#71)"
```

---

### Task 6: Student self-service — controller, route, results-page button

**Files:**
- Create: `app/Http/Controllers/Student/TranscriptController.php`
- Modify: `routes/student.php` (add `student.transcript`)
- Modify: `app/Http/Controllers/Student/CourseResultController.php` (add `hasTranscript` prop)
- Modify: `resources/js/pages/student/results/Index.vue` (download button)
- Test: `tests/Feature/Transcripts/StudentTranscriptTest.php`

**Interfaces:**
- Consumes: `IssueTranscript` (Task 3), `TranscriptPdfRenderer` (Task 5).
- Produces: named route `student.transcript`; `hasTranscript: bool` prop on `student/results/Index`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Transcripts/StudentTranscriptTest.php`:

```php
<?php

use App\Enums\RoleName;
use App\Models\Course;
use App\Models\CourseResult;
use App\Models\StudentProfile;
use App\Models\Transcript;
use Database\Seeders\RolesSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => $this->seed(RolesSeeder::class));

it('lets a student download their own transcript as a PDF', function (): void {
    $user = userWithRole(RoleName::Student);
    $profile = StudentProfile::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->approved()->create(['credits' => 3]);
    CourseResult::factory()->published()->create(['course_id' => $course->id, 'student_profile_id' => $profile->id, 'ca_score' => 80, 'exam_score' => 80]);

    $response = $this->actingAs($user)->get(route('student.transcript'));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF');
});

it('redirects back and issues nothing when the student has no published results', function (): void {
    $user = userWithRole(RoleName::Student);
    StudentProfile::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->from(route('student.results.index'))->get(route('student.transcript'))
        ->assertRedirect(route('student.results.index'));

    expect(Transcript::count())->toBe(0);
});

it('flags transcript availability on the results page', function (): void {
    $user = userWithRole(RoleName::Student);
    $profile = StudentProfile::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->approved()->create();
    CourseResult::factory()->published()->create(['course_id' => $course->id, 'student_profile_id' => $profile->id, 'ca_score' => 80, 'exam_score' => 80]);

    $this->actingAs($user)->get(route('student.results.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('hasTranscript', true));
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Transcripts/StudentTranscriptTest.php --compact`
Expected: FAIL — route `student.transcript` not defined.

- [ ] **Step 3: Write the controller**

Create `app/Http/Controllers/Student/TranscriptController.php`:

```php
<?php

namespace App\Http\Controllers\Student;

use App\Actions\IssueTranscript;
use App\Http\Controllers\Controller;
use App\Services\TranscriptPdfRenderer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class TranscriptController extends Controller
{
    /**
     * Stream the authenticated student's own official transcript as a PDF,
     * aggregating all their published results. Redirects back with a notice when
     * the student has no published results yet (no empty document is issued).
     */
    public function download(Request $request, IssueTranscript $issueTranscript, TranscriptPdfRenderer $renderer): Response
    {
        $profile = $request->user()->studentProfile;

        if ($profile === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('You need an active student enrollment to generate a transcript.')]);

            return back();
        }

        $transcript = $issueTranscript->execute($profile, $request->user(), 'student');

        if ($transcript === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('No published results yet — your transcript will be available once results are published.')]);

            return back();
        }

        return response($renderer->render($transcript), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$transcript->transcript_number.'.pdf"',
        ]);
    }
}
```

- [ ] **Step 4: Register the route**

In `routes/student.php`, add the import near the other `Student\...Controller` imports:

```php
use App\Http\Controllers\Student\TranscriptController;
```

And inside the group, after the `results/{result}/dispute` route:

```php
        Route::get('transcript', [TranscriptController::class, 'download'])
            ->middleware('throttle:lookups')
            ->name('transcript');
```

- [ ] **Step 5: Add the `hasTranscript` prop**

In `app/Http/Controllers/Student/CourseResultController.php`, replace the `index()` return block:

```php
        return Inertia::render('student/results/Index', [
            'courses' => $courses,
        ]);
```

with (compute availability over ALL published results, not just the current cohort):

```php
        $hasTranscript = $profile !== null && CourseResult::query()
            ->where('student_profile_id', $profile->id)
            ->where('status', ResultStatus::Published->value)
            ->exists();

        return Inertia::render('student/results/Index', [
            'courses' => $courses,
            'hasTranscript' => $hasTranscript,
        ]);
```

(`CourseResult` and `ResultStatus` are already imported in this controller.)

- [ ] **Step 6: Add the download button**

In `resources/js/pages/student/results/Index.vue`:

Add to the props definition:

```ts
defineProps<{ courses: CourseRow[]; hasTranscript: boolean }>();
```

Replace the `<section>` header block (the one containing `<h1>My results</h1>`) so the title row carries the button:

```vue
        <section class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p
                    class="text-xs font-semibold tracking-wider text-primary-700 uppercase dark:text-primary-400"
                >
                    Student
                </p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight">My results</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Your published CA and exam results. Raise a dispute if a score
                    looks wrong.
                </p>
            </div>
            <a v-if="hasTranscript" :href="student.transcript().url">
                <Button label="Download transcript" icon-pos="left">
                    <template #icon><FileText class="mr-2 size-4" /></template>
                </Button>
            </a>
        </section>
```

Add `FileText` to the `lucide-vue-next` import at the top:

```ts
import { FileText, GraduationCap } from 'lucide-vue-next';
```

(`Button` and `student` are already imported.)

- [ ] **Step 7: Regenerate Wayfinder, run tests + frontend gate**

Run: `php artisan wayfinder:generate` (regenerates `@/routes/student` so `student.transcript()` exists).
Run: `php artisan test tests/Feature/Transcripts/StudentTranscriptTest.php --compact`
Expected: PASS (3 passed).
Run: `npm run types:check && npm run lint:check`
Expected: no errors.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Student/TranscriptController.php routes/student.php app/Http/Controllers/Student/CourseResultController.php resources/js/pages/student/results/Index.vue tests/Feature/Transcripts/StudentTranscriptTest.php resources/js/routes
git commit -m "feat(transcripts): student self-service download + results-page button (#71)"
```

---

### Task 7: SAO students index + any-student transcript

**Files:**
- Create: `app/Http/Controllers/Sao/StudentController.php`
- Modify: `routes/sao.php` (add `sao.students.index` + `sao.students.transcript`)
- Create: `resources/js/pages/sao/students/Index.vue`
- Modify: `resources/js/components/AppSidebarNav.vue` (add "Students")
- Test: `tests/Feature/Transcripts/SaoTranscriptTest.php`

**Interfaces:**
- Consumes: `IssueTranscript` (Task 3), `TranscriptPdfRenderer` (Task 5), `StudentProfile`.
- Produces: named routes `sao.students.index`, `sao.students.transcript`; Inertia page `sao/students/Index`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Transcripts/SaoTranscriptTest.php`:

```php
<?php

use App\Enums\RoleName;
use App\Models\Course;
use App\Models\CourseResult;
use App\Models\StudentProfile;
use Database\Seeders\RolesSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => $this->seed(RolesSeeder::class));

it('lets SAO download any student transcript and blocks other roles', function (): void {
    $profile = StudentProfile::factory()->create();
    $course = Course::factory()->approved()->create(['credits' => 3]);
    CourseResult::factory()->published()->create(['course_id' => $course->id, 'student_profile_id' => $profile->id, 'ca_score' => 80, 'exam_score' => 80]);

    $this->actingAs(userWithRole(RoleName::Sao))
        ->get(route('sao.students.transcript', $profile))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs(userWithRole(RoleName::Lecturer))
        ->get(route('sao.students.transcript', $profile))
        ->assertForbidden();
});

it('renders a searchable student index for SAO', function (): void {
    StudentProfile::factory()->create(['matricule' => 'stm-2025-4242']);
    StudentProfile::factory()->create(['matricule' => 'stm-2025-9999']);

    $this->actingAs(userWithRole(RoleName::Sao))
        ->get(route('sao.students.index', ['search' => '4242']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('sao/students/Index')
            ->has('students.data', 1)
            ->where('students.data.0.matricule', 'stm-2025-4242'));
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Transcripts/SaoTranscriptTest.php --compact`
Expected: FAIL — route `sao.students.transcript` not defined.

- [ ] **Step 3: Write the controller**

Create `app/Http/Controllers/Sao/StudentController.php`:

```php
<?php

namespace App\Http\Controllers\Sao;

use App\Actions\IssueTranscript;
use App\Http\Controllers\Controller;
use App\Models\StudentProfile;
use App\Services\TranscriptPdfRenderer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class StudentController extends Controller
{
    /**
     * Searchable, paginated list of student profiles for staff — the home for
     * looking up any student and generating their transcript.
     */
    public function index(Request $request): InertiaResponse
    {
        $search = trim((string) $request->query('search', ''));

        $students = StudentProfile::query()
            ->with(['user:id,name', 'programOffering.department:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('matricule', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('matricule')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (StudentProfile $profile): array => [
                'id' => $profile->id,
                'matricule' => $profile->matricule,
                'name' => $profile->user?->name,
                'programme' => $profile->programOffering?->department?->name,
                'level' => $profile->level,
                'status' => $profile->status->value,
            ]);

        return Inertia::render('sao/students/Index', [
            'students' => $students,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Stream any student's official transcript as a PDF (staff-issued). Redirects
     * back with a notice when the student has no published results.
     */
    public function transcript(Request $request, StudentProfile $studentProfile, IssueTranscript $issueTranscript, TranscriptPdfRenderer $renderer): Response
    {
        $transcript = $issueTranscript->execute($studentProfile, $request->user(), 'sao');

        if ($transcript === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This student has no published results yet.')]);

            return back();
        }

        return response($renderer->render($transcript), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$transcript->transcript_number.'.pdf"',
        ]);
    }
}
```

- [ ] **Step 4: Register the routes**

In `routes/sao.php`, add the import:

```php
use App\Http\Controllers\Sao\StudentController;
```

And inside the group, after the disputes routes:

```php
        Route::get('students', [StudentController::class, 'index'])->name('students.index');
        Route::get('students/{studentProfile}/transcript', [StudentController::class, 'transcript'])
            ->middleware('throttle:lookups')
            ->name('students.transcript');
```

- [ ] **Step 5: Write the students index page**

Create `resources/js/pages/sao/students/Index.vue`:

```vue
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Users } from 'lucide-vue-next';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import { ref } from 'vue';
import sao from '@/routes/sao';

type StudentRow = {
    id: number;
    matricule: string | null;
    name: string | null;
    programme: string | null;
    level: number | null;
    status: string;
};

type Paginator<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

const props = defineProps<{
    students: Paginator<StudentRow>;
    filters: { search: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Students', href: sao.students.index() }],
    },
});

const search = ref(props.filters.search);

function runSearch(): void {
    router.get(
        sao.students.index().url,
        { search: search.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function transcriptUrl(id: number): string {
    return sao.students.transcript(id).url;
}

function goToPage(url: string | null): void {
    if (url) {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Students" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
        <section>
            <p class="text-xs font-semibold tracking-wider text-primary-700 uppercase dark:text-primary-400">
                Student affairs
            </p>
            <h1 class="mt-1 flex items-center gap-2 text-2xl font-bold tracking-tight">
                <Users class="size-6" /> Students
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Look up a student and download their official academic transcript.
            </p>
        </section>

        <form class="flex items-center gap-2" @submit.prevent="runSearch">
            <InputText
                v-model="search"
                placeholder="Search by matricule or name"
                class="w-full max-w-sm"
            />
            <Button type="submit" label="Search" />
        </form>

        <DataTable :value="students.data" data-key="id" size="small" class="text-sm">
            <Column field="matricule" header="Matricule">
                <template #body="{ data }">
                    <span class="font-mono">{{ data.matricule ?? '—' }}</span>
                </template>
            </Column>
            <Column field="name" header="Name">
                <template #body="{ data }">{{ data.name ?? '—' }}</template>
            </Column>
            <Column field="programme" header="Programme">
                <template #body="{ data }">{{ data.programme ?? '—' }}</template>
            </Column>
            <Column field="level" header="Level">
                <template #body="{ data }">{{ data.level ?? '—' }}</template>
            </Column>
            <Column field="status" header="Status">
                <template #body="{ data }"><span class="capitalize">{{ data.status }}</span></template>
            </Column>
            <Column header="Transcript">
                <template #body="{ data }">
                    <a :href="transcriptUrl(data.id)">
                        <Button label="Download" size="small" severity="secondary" />
                    </a>
                </template>
            </Column>
            <template #empty>
                <div class="p-6 text-center text-muted-foreground">No students found.</div>
            </template>
        </DataTable>

        <div v-if="students.total > 0" class="flex flex-wrap items-center gap-1">
            <template v-for="(link, i) in students.links" :key="i">
                <button
                    v-if="link.url"
                    class="rounded px-3 py-1 text-sm"
                    :class="link.active ? 'bg-primary text-primary-contrast' : 'hover:bg-muted'"
                    @click="goToPage(link.url)"
                    v-html="link.label"
                />
                <span v-else class="px-3 py-1 text-sm text-muted-foreground" v-html="link.label" />
            </template>
        </div>
    </div>
</template>
```

- [ ] **Step 6: Add the sidebar nav entry**

In `resources/js/components/AppSidebarNav.vue`, add the import next to the other `sao/*` route imports:

```ts
import { index as saoStudentsIndex } from '@/routes/sao/students';
```

Add a `Users` icon to the `lucide-vue-next` import block (append to the existing list), then add a nav item inside the `if (hasRole('sao') || hasRole('admin'))` block, after the "Disputes" item:

```ts
            {
                title: 'Students',
                href: saoStudentsIndex(),
                icon: Users,
            },
```

- [ ] **Step 7: Regenerate Wayfinder, run tests + frontend gate**

Run: `php artisan wayfinder:generate` (creates `@/routes/sao/students`).
Run: `php artisan test tests/Feature/Transcripts/SaoTranscriptTest.php --compact`
Expected: PASS (2 passed).
Run: `npm run types:check && npm run lint:check`
Expected: no errors.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Sao/StudentController.php routes/sao.php resources/js/pages/sao/students/Index.vue resources/js/components/AppSidebarNav.vue tests/Feature/Transcripts/SaoTranscriptTest.php resources/js/routes
git commit -m "feat(transcripts): SAO students index + any-student transcript (#71)"
```

---

### Task 8: Docs, ADRs, and demo seed

**Files:**
- Create: `docs/modules/transcripts.md`
- Create: `docs/adr/0026-transcript-gpa-scale.md`, `docs/adr/0027-pdf-generation-mpdf.md`, `docs/adr/0028-transcript-verification.md`
- Modify: `docs/adr/README.md`, `docs/index.md`, `docs/security.md`, the routes reference, the data-model + ER reference
- Modify: `database/seeders/DemoSeeder.php` (or the project's demo seeder) — a multi-semester published-results student

Run the **`docs-refresh`** skill to drive this sweep; verify every claim against the shipped code. Concrete content each doc must carry:

- [ ] **Step 1: `docs/modules/transcripts.md`** — purpose; roles & access (student → own via `student.transcript`; SAO/admin → any via `sao.students.*`; public `transcripts.verify`); data model (`transcripts` immutable + `transcript_sequences`); the 4.0 grade-point map + GPA/CGPA/credits rules; snapshot-at-issue + content-digest dedupe; HMAC verification (no oracle); the mpdf/QR pipeline; the test list; a file map.

- [ ] **Step 2: ADR 0026 — GPA scale.** *Decision:* transcripts aggregate on a 4.0 scale (`A=4,B=3,C=2,D=1,F=0`), credit-weighted per-semester GPA + cumulative CGPA; `credits_earned` excludes F. *Context:* Anglophone 0–100/A–F data; GPA is the legible standard. *Consequences:* stored marks unchanged; the map lives in `TranscriptService::GRADE_POINTS`.

- [ ] **Step 3: ADR 0027 — PDF via mpdf.** *Decision:* `mpdf/mpdf` (pure PHP) for transcript PDFs; `simplesoftwareio/simple-qrcode` (SVG). *Context:* no PDF pipeline existed; Laravel Cloud must stay Chromium-free. *Consequences:* two composer deps; `ext-gd`/`ext-mbstring` required; revisit Browsershot only if a real reference template proves visually elaborate. *Rejected:* dompdf (weak multi-page headers), Browsershot (headless Chromium in prod).

- [ ] **Step 4: ADR 0028 — transcript verification.** *Decision:* snapshot-at-issue — an immutable `transcripts` record stores the rendered snapshot + an HMAC over `number|issued_at|content_digest`; a public throttled endpoint re-derives it, no existence oracle; QR on the PDF; content-digest dedupe. *Context:* a transcript's underlying results can change after printing, so live re-derivation (as receipts use) would false-alarm. *Consequences:* mirrors the receipt-verification pattern; re-download of unchanged content reuses the record (not separately audited).

- [ ] **Step 5: Cross-doc updates.** `docs/adr/README.md` (+3 rows); `docs/index.md` (ADR count 25 → 28; mention transcripts in the module list); `docs/security.md` (public throttled verify + immutable record + no oracle); routes reference (+4 routes); data-model + ER reference (+`transcripts`, +`transcript_sequences`, with the `student_profile_id`/`issued_by` relationships).

- [ ] **Step 6: Demo seed.** In the demo seeder, create one student with published results across ≥2 semesters (mix of A/B/C plus one F) so a generated transcript and its verify page have real content for screenshots. Run `php artisan migrate:fresh --seed` to confirm it seeds cleanly.

- [ ] **Step 7: Commit**

```bash
git add docs/ database/seeders
git commit -m "docs(transcripts): module doc, ADRs 0026-0028, refs + demo seed (#71)"
```

---

## Self-Review

**Spec coverage** (against `plan/transcripts/design.md`):
- §2 grade scale (4.0) → Task 1 `GRADE_POINTS` + math; §2 verification snapshot-at-issue → Tasks 2–4; §2 access (student/SAO/public, no gating) → Tasks 4/6/7; §2 SAO students index → Task 7; §3 units → Tasks 1/3/5/2/controllers; §4 tables/model → Task 2; §5 computation → Task 1; §6 HMAC/dedupe/empty → Tasks 2/3; §7 routes (4) → Tasks 4/6/7; §8 PDF template → Task 5; §9 frontend (3 surfaces) → Tasks 4/6/7; §10 audit → Task 3; §11 tests → each task's test file; §12 docs/ADRs → Task 8; §13 deps → Task 5 orchestrator action; §14 risks noted (mpdf CI, dedupe race). All covered.
- **Placeholder scan:** every code step carries full code; the only "run the skill" step (Task 8) enumerates each doc's concrete content. No TBD/TODO.
- **Type consistency:** `buildSnapshot(StudentProfile, string): array` and `contentDigest(array): string` are used identically in Tasks 2/3/4/5; `execute(StudentProfile, User, string): ?Transcript` matches across Tasks 3/6/7; `render(Transcript): string` matches Tasks 5/6/7; `computeSignature(string, string, string)` matches model + factory + action; snapshot keys (`student/semesters/cumulative/meta`, per-course `code/title/credits/score/grade/points`) are consistent across service, blade, verify controller, and factory.

## Execution Handoff

Plan complete and saved to `plan/transcripts/plan.md`. Two execution options:

1. **Subagent-Driven (recommended)** — a fresh subagent per task, spec+quality review between tasks, fast iteration. Note: the **orchestrator** must run `composer require mpdf/mpdf simplesoftwareio/simple-qrcode` at Task 5 (implementer subagents are sandbox-restricted).
2. **Inline Execution** — execute tasks in this session via executing-plans with checkpoints.

Which approach?
