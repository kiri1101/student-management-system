# Student academic transcripts

> GitHub #71. Source of truth verified against shipped code; where the
> [plan](../../plan/transcripts/plan.md) and the code disagree, the code is documented and the drift
> is noted inline.

## Purpose

A student needs an **official academic transcript** — the credit-weighted record of every published
course result across all the years and levels they have studied — for scholarships, transfers, and
graduation. This module generates that transcript on demand as a branded PDF, computes GPA/CGPA on a
4.0 scale, and makes each issued transcript **tamper-evident and publicly verifiable** the same way
school receipts are ([#6](payments.md)) — a QR/URL on the document resolves to a public page that
confirms the transcript is authentic and unaltered.

Unlike a receipt, whose bound facts never change, a transcript aggregates results that can
legitimately change after it is printed (a late-published course, a dispute resolution). Verifying
against *live* data would therefore false-alarm on a perfectly genuine old transcript. So each
generation captures a **snapshot at issue**: an immutable `transcripts` record stores the exact
rendered content plus an HMAC over it, and verification re-derives the signature from that stored
snapshot — not from current results ([ADR-0028](../adr/0028-transcript-verification.md)).

## Roles & abilities

| Role | What they do here | How it is guarded |
|---|---|---|
| **Student** | Downloads **their own** transcript from the results page | `routes/student.php` group (`role:student,admin`); the controller resolves the caller's own profile — no id in the URL |
| **SAO / Admin** | Looks up **any** student and downloads their transcript | `routes/sao.php` group (`role:sao,admin`); staff-issued for any `StudentProfile` by design |
| **Anyone (public)** | Verifies a transcript number | No auth; `throttle:lookups` only |

**Authorization is enforced by `role:` middleware + ownership, with no ability gates** (they were
retired in [ADR-0025](../adr/0025-retire-ability-gates.md)). The student route carries no student id —
the caller can only ever reach `$request->user()->studentProfile`, so there is no IDOR surface. The
SAO route group (`role:sao,admin`) is the whole authorization for staff issuance: a Lecturer hitting
`sao.students.transcript` gets `403` from the group middleware, no per-route check needed. Staff
issuing a transcript for *any* student is intended, not a leak. See [security.md](../security.md) §4.1.

## Data model

One immutable owned table plus a per-year counter. See [data-model.md](../data-model.md#transcripts)
for full column detail.

| Model | Key columns | Notes |
|---|---|---|
| `Transcript` | `transcript_number` (unique), `student_profile_id`, `matricule`, `student_name`, `programme`, `level`, `snapshot` (json), `content_digest`, `cgpa`, `credits_earned`, `credits_attempted`, `signature`, `issued_at`, `issued_by` | **Immutable** — `booted()` throws on `updating`/`deleting` (like `SchoolReceipt`/`AuditLog`). `$timestamps = false`. The `snapshot` JSON is the source of truth for both the PDF and the verify page; `content_digest` drives dedupe. |
| `transcript_sequences` (table) | `year` (PK), `last_number` | One row per year; `lockForUpdate()` serializes number issuance. Query-builder only — a counter, not domain data. Mirrors `receipt_sequences` (AUD-006). |

**Audit:** one new `App\Enums\AuditAction` case, `TranscriptGenerated` (`'transcript_generated'`).

## Computation — `TranscriptService`

`buildSnapshot(StudentProfile $profile, string $generatedByRole): array` is pure computation (no
persistence). It reads the student's `CourseResult`s where **`status = Published`** and both marks are
present (a real `final_score` and grade — draft/unscored rows never appear), joins course metadata,
orders by academic year → semester → course code, and groups into semesters.

**Grade points (4.0 scale, whole points, no +/− tiers)** — `TranscriptService::GRADE_POINTS`
([ADR-0026](../adr/0026-transcript-gpa-scale.md)):

| Grade | A | B | C | D | F |
|---|---|---|---|---|---|
| Points | 4.0 | 3.0 | 2.0 | 1.0 | 0.0 |

Letter grades come from `CourseResult::grade` (weighted final = `0.3·ca + 0.7·exam`; A ≥ 80, B ≥ 70,
C ≥ 60, D ≥ 50, else F).

- **Per-course quality points** = grade points × course credits.
- **Semester GPA** and **cumulative CGPA** = Σ(quality points) ÷ Σ(credits), rounded to 2 dp
  (`weightedAverage`, guards divide-by-zero).
- **`credits_earned`** sums credits of passed courses (excludes F); **`credits_attempted`** sums all
  included courses' credits.

The snapshot shape: `student{matricule,name,programme,level}`, `semesters[]{academic_year, semester,
courses[]{code,title,credits,score,grade,points}, gpa, credits_earned, credits_attempted}`,
`cumulative{cgpa, credits_earned, credits_attempted, total_courses}`, and `meta{generated_at,
generated_by_role}`. `programme` is the student's department + degree
(`"{department} ({degree})"`, e.g. `Computer Science (Bachelors)`, via `DegreeProgram::label()`) —
the same programme the SAO students index shows, so the transcript and the lookup table agree.

## Issuance & dedupe — `IssueTranscript`

`execute(StudentProfile $profile, User $issuedBy, string $generatedByRole): ?Transcript`:

1. Build the snapshot. If `semesters === []` (no published results), **return `null`** — no empty
   document is issued, no row, no audit.
2. Compute `content_digest = TranscriptService::contentDigest(snapshot)` — a SHA-256 over the
   snapshot **with the `meta` block removed**, so the digest depends only on identity + academic
   content, not on who generated it or when.
3. **Dedupe:** if a `Transcript` already exists for this `(student_profile_id, content_digest)`,
   return it unchanged — no new number, **no second audit row**. (The DB index on that pair is
   non-unique; this application check is the dedupe.)
4. Otherwise, inside a `DB::transaction`: allocate `Transcript::nextTranscriptNumberForYear($year)`
   (`TRN-{year}-{00001}`, per-year `transcript_sequences` counter under `lockForUpdate()`), create the
   immutable record with its HMAC signature, and record `AuditAction::TranscriptGenerated`. The
   number allocation and create **must** share one transaction — the lock only serializes concurrent
   callers when the caller holds the transaction.

A benign race remains by design: two concurrent *first-time* generations of identical content can both
mint (the dedupe check is outside the transaction). Re-downloading unchanged content reuses the
existing record, so this is rare and harmless.

## Verification — `VerifyTranscriptController` + `Transcript::verifies()`

```mermaid
sequenceDiagram
    actor Verifier
    Verifier->>VerifyTranscriptController: GET transcripts/verify/{number}
    VerifyTranscriptController->>Transcript: find by transcript_number
    Transcript->>Transcript: verifies() — recompute HMAC over STORED snapshot
    Note over Transcript: expectedSignature() = HMAC(number|issued_at|contentDigest(snapshot))
    Transcript-->>VerifyTranscriptController: hash_equals(stored, expected)
    VerifyTranscriptController-->>Verifier: valid ⇒ summary; invalid ⇒ "invalid" (no detail)
```

The HMAC signs `transcript_number | issued_at_iso | content_digest` keyed by `APP_KEY`
(`Transcript::computeSignature`). `verifies()` recomputes the digest from the **currently stored
`snapshot`** and compares with `hash_equals()` (constant-time). Tampering with the stored snapshot,
number, or date drifts the expected signature and reads invalid. The public page's payload is built
**only** inside a `$valid ? [...] : null` branch, so an unknown number, a forged record, and a
tampered snapshot all render `valid: false` with `transcript: null` — no oracle for which numbers
exist. Same posture as the receipt endpoint ([security.md](../security.md) §4).

## PDF pipeline — `TranscriptPdfRenderer`

`render(Transcript $transcript): string` returns PDF bytes. It renders the Blade template
`pdf.transcript` from the **stored snapshot** (never live models, so an old transcript re-renders its
snapshot-at-issue), laid out by **mpdf** ([ADR-0027](../adr/0027-pdf-generation-mpdf.md)) with a
repeating table header and a per-page footer. The QR code is an SVG generated **directly** with
`bacon/bacon-qr-code` (`Writer` + `ImageRenderer` + `SvgImageBackEnd` + `RendererStyle(120, 0)`); its
XML prolog is stripped so mpdf can embed it inline. The QR and a printed URL both point at
`route('transcripts.verify', $transcript->transcript_number)`.

## Routes & screens

Pages live in `resources/js/pages/`. **`transcripts/Verify` renders with no app shell** (a standalone
public page). The transcript PDF is streamed as an attachment, not an Inertia page.

### Public (`routes/web.php`, no auth, `throttle:lookups`)

| Method · URI | Name | Controller |
|---|---|---|
| GET `transcripts/verify/{transcript_number}` | `transcripts.verify` | `Transcripts\VerifyTranscriptController` (invokable) → `transcripts/Verify` (no app shell) |

### Student (`routes/student.php`, `role:student,admin`)

| Method · URI | Name | Page / action |
|---|---|---|
| GET `student/transcript` | `student.transcript` | `Student\TranscriptController@download` — streams the caller's own transcript PDF (`throttle:lookups`) |

The student results page (`student/results/Index`) shows a "Download transcript" button when the
`hasTranscript` prop is true (computed as: the student has ≥1 published result, over **all** results,
not just the current cohort).

### SAO (`routes/sao.php`, `role:sao,admin`)

| Method · URI | Name | Page / action |
|---|---|---|
| GET `sao/students` | `sao.students.index` | `Sao\StudentController@index` — `sao/students/Index`, searchable/paginated student list |
| GET `sao/students/{studentProfile}/transcript` | `sao.students.transcript` | `Sao\StudentController@transcript` — streams that student's transcript PDF (`throttle:lookups`) |

A "Students" item appears in the sidebar for SAO + Admin (`AppSidebarNav.vue`). The index eager-loads
`user` + `programOffering.department` (no N+1) and searches matricule **or** name.

## Side effects

**Audit** (`App\Enums\AuditAction`, immutable `AuditLog`):

| When | Action | Subject |
|---|---|---|
| A new transcript is minted (not on dedupe reuse) | `TranscriptGenerated` | the `Transcript` |

No events, listeners, mail, or notifications — transcript issuance is pull/download-based, not pushed.

## Tests

| File | Covers |
|---|---|
| `tests/Feature/Transcripts/TranscriptServiceTest.php` | 4.0 GPA/CGPA + credits math, Published-only filtering, digest stability across role, multi-key sort ordering, empty → no semesters |
| `tests/Feature/Transcripts/TranscriptModelTest.php` | Immutability (update/delete throw), per-year `TRN-{year}-{00001}` sequence, HMAC `verifies()` |
| `tests/Feature/Transcripts/IssueTranscriptTest.php` | Mint + single audit, `null` on no published results, dedupe (same content → same record + no second audit; changed → new record) |
| `tests/Feature/Transcripts/VerifyTranscriptTest.php` | Authentic → summary; tampered snapshot and unknown number → `valid:false`, `transcript:null` (no oracle) |
| `tests/Feature/Transcripts/TranscriptPdfRendererTest.php` | Renders real `%PDF` bytes (full mpdf + QR pipeline); view embeds the number + verify path |
| `tests/Feature/Transcripts/StudentTranscriptTest.php` | Student downloads own PDF (`application/pdf`), redirect + zero rows on no results, `hasTranscript` flag |
| `tests/Feature/Transcripts/SaoTranscriptTest.php` | SAO downloads any student's PDF + Lecturer `403`; searchable index filters correctly |

## File map

| File | Role |
|---|---|
| `app/Services/TranscriptService.php` | Snapshot builder; `GRADE_POINTS`; GPA/CGPA/credits; `contentDigest` (meta-stripped) |
| `app/Models/Transcript.php` | Immutable record; `computeSignature`/`expectedSignature`/`verifies`; `nextTranscriptNumberForYear` |
| `app/Actions/IssueTranscript.php` | Dedupe-or-mint + audit inside a transaction; `null` on empty |
| `app/Services/TranscriptPdfRenderer.php` | mpdf render of the Blade template + inline bacon QR |
| `app/Http/Controllers/Transcripts/VerifyTranscriptController.php` | Public HMAC verification page (no oracle) |
| `app/Http/Controllers/Student/TranscriptController.php` | Student self-service download (own profile only) |
| `app/Http/Controllers/Sao/StudentController.php` | SAO students index + any-student transcript |
| `app/Enums/AuditAction.php` | `TranscriptGenerated` case |
| `resources/views/pdf/transcript.blade.php` | The PDF template (reads the snapshot only) |
| `resources/js/pages/transcripts/Verify.vue` | Public verification result (no app shell) |
| `resources/js/pages/sao/students/Index.vue` | SAO students index (PrimeVue DataTable) |
| `resources/js/pages/student/results/Index.vue` | Results page + "Download transcript" button |
| `resources/js/components/AppSidebarNav.vue` | "Students" nav item (SAO + Admin) |
| `database/migrations/2026_07_06_100000_create_transcripts_table.php` | `transcripts` (immutable) |
| `database/migrations/2026_07_06_100001_create_transcript_sequences_table.php` | per-year counter |
| `database/seeders/TranscriptDemoSeeder.php` | A demo student with multi-semester published results (local/testing) |

---

> Cross-references: [architecture.md](../architecture.md) (request lifecycle),
> [data-model.md](../data-model.md#transcripts) (columns + relations), [routes.md](../routes.md)
> (full endpoint inventory), [security.md](../security.md) (§4.1 transcript verification, §2
> authorization), [testing.md](../testing.md). The HMAC receipt sibling this mirrors is
> [payments.md](payments.md) (#6).
