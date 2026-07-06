# Design — Student transcript generation (#71 / B16)

- **Issue:** #71 — Aggregate a student's published results → official, verifiable PDF transcript
- **Date:** 2026-07-06
- **Size:** L (new domain service + immutable model + PDF pipeline + public verification + a new SAO screen; two new composer deps)
- **Status:** Approved (all five kickoff decisions locked in the 2026-07-06 brainstorm), pending implementation plan

## 1. Problem

The app shows a student their **per-course** published results for their **current** cohort only
(`Student\CourseResultController@index`). There is no cross-semester aggregation, no GPA, and no
document pipeline. A student cannot obtain the one artifact every institution is expected to
produce: an **official academic transcript** — a credit-weighted record of every published result
across all years and levels, with per-semester and cumulative standing, that a third party
(employer, scholarship board, another university) can trust.

A student's complete multi-year history is fully reconstructable from existing data: each
`course_results` row joins a `Course` carrying its own `code`, `title`, `credits`, `semester`,
`level`, `academic_year`. So the transcript comes from the student's `CourseResult`s alone — the
`StudentProfile`'s single `level`/`academic_year` is only their *current* cohort, and no
enrollment-history table is needed.

Three gaps to close: (1) no grade-point / GPA layer; (2) no cross-semester aggregation; (3) no
PDF/verification pipeline anywhere in the app.

## 2. Locked decisions

From the issue (2026-06-22): PDF via **`mpdf/mpdf`** (pure PHP — no Chromium/Node in the runtime,
strong Unicode, native repeating table headers + per-page footers/page-numbers); QR via
**`simplesoftwareio/simple-qrcode`** (SVG output — no gd/imagick, embeds natively in mpdf);
official verification **in scope**.

From this brainstorm (2026-07-06):

1. **Template:** sensible **SchuLyf-branded default** now; refine visuals against a real reference
   image later. Data model, GPA math, verification, and routes are independent of the exact visual.
2. **Grade scale:** **4.0 GPA + CGPA.** Grade→point map `A=4, B=3, C=2, D=1, F=0` (whole points —
   grades have no +/− tiers). Per-course rows show **score % + letter + points**; per-semester and
   cumulative **summary** lines show **GPA/CGPA + credits earned/attempted**. Stored marks are
   unchanged; this only decides the transcript's summary vocabulary.
3. **Verification model:** **snapshot-at-issue.** Unlike a receipt (whose bound facts are
   immutable), a transcript aggregates results that can *legitimately* change after printing (a
   dispute revises a grade). So each generation writes an **immutable `transcripts` record** storing
   the exact rendered snapshot + an HMAC. The public verifier re-derives the HMAC over the *stored
   snapshot* and shows the document **as it was issued** (issue date shown for currency). A later
   grade change never turns an old transcript into a false "tampered" alarm.
4. **Access & policy:** student → their **own** transcript (all published results, every
   year/level); SAO/admin → **any** student; verify endpoint → **public, unauthenticated**.
   **No gating** (option A) — an academic record is decoupled from finance and current status; any
   authenticated student can always obtain their own.
5. **Staff-side home:** the issue assumed a SAO "student detail view" that **does not exist**. Add a
   **minimal SAO "Students" index** (option A) — a searchable, paginated list of student profiles
   with a per-row "Download transcript" action. Bounded new screen; also the natural future home for
   per-student staff actions.

## 3. Architecture — small, well-bounded units

| Unit | Responsibility | Depends on |
|---|---|---|
| `TranscriptService` (`app/Services`) | **Pure computation.** Build the snapshot array from a profile's published results; grade→point map; content digest. No DB writes. | `CourseResult`, `Course` (read) |
| `IssueTranscript` (`app/Actions`) | **Orchestration.** Build snapshot → dedupe by content digest → persist immutable `Transcript` (locked sequence) or reuse → audit-log. Returns the `Transcript` or `null` (no results). | `TranscriptService`, `Transcript` |
| `TranscriptPdfRenderer` (`app/Services`) | **Rendering.** `Transcript` → PDF bytes via mpdf + Blade view + embedded QR. | `Transcript`, Blade view |
| `Transcript` model | **Persistence + integrity.** Immutable (update/delete throw); per-year locked number sequence; HMAC compute/verify. | — |
| Controllers (student / SAO / verify) | **Thin.** Resolve subject, authorize (route middleware + ownership), call action + renderer, return the PDF response — or redirect on empty. | action, renderer |

Each unit is independently testable: `TranscriptService` from a fixture with no HTTP, `IssueTranscript`
for dedupe/immutability, the renderer for QR/heading presence, controllers for authz/response.

## 4. Data model

### 4.1 New table `transcripts` (immutable, mirrors `school_receipts`)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint pk | |
| `transcript_number` | string, **unique** | `TRN-{year}-{00001}` from a per-year locked sequence |
| `student_profile_id` | FK → student_profiles, indexed | the subject |
| `matricule` | string | **snapshot** of bound identity (verification must not depend on the live profile) |
| `student_name` | string | snapshot of the student's name at issue |
| `programme` | string, nullable | snapshot of department/programme name |
| `level` | unsignedInteger, nullable | snapshot of current level at issue |
| `snapshot` | json (longText) | the full rendered transcript (see §5.2); source of truth for PDF + verify |
| `content_digest` | string(64), indexed | `sha256` of the canonical snapshot — drives dedupe lookup |
| `cgpa` | decimal(3,2) | denormalized for the verify page summary |
| `credits_earned` | unsignedInteger | denormalized |
| `credits_attempted` | unsignedInteger | denormalized |
| `signature` | string | HMAC-SHA256 (see §6) |
| `issued_at` | datetime | issue date (shown on document + verify) |
| `issued_by` | FK → users, nullable | actor who generated it (student / SAO / admin) |

No `timestamps` (`public $timestamps = false;`) — issuance time is `issued_at`, and the row is
immutable after insert (like `SchoolReceipt` / `AuditLog`).

### 4.2 New table `transcript_sequences` (mirrors `receipt_sequences`)

`year` (unique) + `last_number` (int). Drives `Transcript::nextTranscriptNumberForYear()` with the
same `insertOrIgnore` seed + `lockForUpdate()` serialization proven in
`SchoolReceipt::nextReceiptNumberForYear()` / `StudentProfile::nextMatriculeForYear()` (AUD-006).

### 4.3 `Transcript` model

- `$timestamps = false`; casts `snapshot => 'array'`, `issued_at => 'datetime'`, `cgpa => 'decimal:2'`,
  credits → integer.
- `booted()`: `updating`/`deleting` throw `RuntimeException` (immutable proof, like `SchoolReceipt`).
- Relations: `studentProfile()`, `issuer()` (`belongsTo User, 'issued_by'`).
- Statics/instance HMAC helpers in §6; `nextTranscriptNumberForYear(int $year): string` per §4.2.

### 4.4 Enum

Add `AuditAction::TranscriptGenerated = 'transcript_generated'`.

## 5. Computation (`TranscriptService`)

### 5.1 Selection & grouping

- Source rows: `CourseResult` where `student_profile_id = profile.id`, `status = Published`, **and both
  marks present** (`final_score` non-null → a real grade). Draft/unpublished never appear.
- We do **not** filter on `Course.plan_status`: a *published result* is the authority that the course
  ran and was graded (plan review is an orthogonal, earlier lifecycle). (Rationale recorded so the
  divergence from the current-cohort results page is intentional.)
- Join each result's `Course` for `code/title/credits/semester/academic_year/level`.
- Group by `academic_year` (asc) → `semester` (asc); order courses within a semester by `code` (asc).

### 5.2 Snapshot shape (stored in `transcripts.snapshot`)

```
[
  'student'    => ['matricule' => 'stm-2025-0007', 'name' => '…', 'programme' => '…', 'level' => 300],
  'semesters'  => [
    [
      'academic_year' => '2025/2026', 'semester' => 1,
      'courses' => [
        ['code' => 'CSC101', 'title' => 'Introduction to Programming',
         'credits' => 3, 'score' => 82, 'grade' => 'A', 'points' => 4.0],
        // …
      ],
      'gpa' => 3.22, 'credits_earned' => 9, 'credits_attempted' => 9,
    ],
    // …
  ],
  'cumulative' => ['cgpa' => 3.05, 'credits_earned' => 42, 'credits_attempted' => 45, 'total_courses' => 15],
  'meta'       => ['generated_at' => '2026-07-06T…Z', 'generated_by_role' => 'student'],
]
```

### 5.3 GPA math (grade→point map `A=4,B=3,C=2,D=1,F=0`)

- Per course: `points = GRADE_POINTS[grade]`; `quality_points = points × credits`.
- Semester GPA = `Σ quality_points ÷ Σ credits` over that semester's courses, rounded **2 dp**
  (round-half-up).
- `credits_attempted` (sem/cumulative) = `Σ credits` of all included courses.
- `credits_earned` (sem/cumulative) = `Σ credits` where `grade !== 'F'` (F counts toward attempted
  and pulls GPA down with 0 points, but earns no credit).
- CGPA = `Σ quality_points ÷ Σ credits` over **all** courses, rounded 2 dp.
- `meta.generated_at` is excluded from the content digest so re-issuing identical academic content
  is deduped (see §6).

### 5.4 Content digest & empty case

- `contentDigest(array $snapshot): string` = `hash('sha256', json_encode($stable))` where `$stable`
  is the snapshot **minus `meta`** (identity + academic content only), with deterministic key order.
  Identity is included, so two different students never collide and the same student's unchanged
  results reproduce the same digest.
- **Empty:** if the student has zero qualifying results, `buildSnapshot` returns a snapshot with no
  semesters; `IssueTranscript` returns `null`. Controllers translate `null` into a friendly
  redirect-back with an error flash ("No published results yet"), **never** an issued 0.00-CGPA
  document.

## 6. Verification (snapshot-at-issue, mirrors the receipt pattern)

`Transcript` HMAC helpers (server-side; no third party needs the key — they hit the public endpoint):

- `canonicalPayload(string $number, string $issuedAtIso, string $digest): string`
  = `implode('|', [$number, $issuedAtIso, $digest])`.
- `computeSignature(...)` = `hash_hmac('sha256', canonicalPayload(...), config('app.key'))`.
- `expectedSignature(): string` — recompute the digest from the **stored snapshot**
  (`TranscriptService::contentDigest($this->snapshot)`), then sign `transcript_number` +
  `issued_at->toIso8601String()` + that digest.
- `verifies(): bool` = `hash_equals($this->signature, $this->expectedSignature())`.

Because the signature binds a digest **recomputed from the stored snapshot**, any tampering with the
`snapshot` column (or the number/date) changes the expected signature → `verifies()` is false. The
stored `content_digest` column is a fast dedupe index, not the integrity source — integrity comes
from re-hashing the snapshot at verify time.

**`IssueTranscript` flow:**
1. `snapshot = TranscriptService::buildSnapshot($profile)`; if no semesters → return `null`.
2. `digest = TranscriptService::contentDigest($snapshot)`.
3. Look up an existing `Transcript` for this `student_profile_id` with `content_digest = digest`.
   If found → **reuse** it (re-render PDF from its stored snapshot; no new row, no audit).
4. Else, in a `DB::transaction`: mint `transcript_number` (locked sequence), compute `signature`,
   insert the immutable record (denormalized cgpa/credits/identity for the verify page), and
   `AuditLog::record(TranscriptGenerated, $transcript, [...], userId: $issuedBy->id)`.

**Public verify endpoint** (`Transcripts\VerifyTranscriptController`, mirrors
`VerifyReceiptController`): load by `transcript_number`; `$valid = $t !== null && $t->verifies();`
render `transcripts/Verify.vue` with the snapshot summary (identity + per-semester GPA + CGPA + issue
date) **only when valid**. Unknown number and bad signature both read "invalid" — no existence
oracle. Throttled (`throttle:lookups`) like the receipt route.

## 7. Routes

| Method | URI | Controller | Name | Group |
|---|---|---|---|---|
| GET | `student/transcript` | `Student\TranscriptController@download` | `student.transcript` | `role:student,admin` (`routes/student.php`), `throttle:lookups` |
| GET | `sao/students` | `Sao\StudentController@index` | `sao.students.index` | `role:sao,admin` (`routes/sao.php`) |
| GET | `sao/students/{studentProfile}/transcript` | `Sao\StudentController@transcript` | `sao.students.transcript` | `role:sao,admin`, `throttle:lookups` |
| GET | `transcripts/verify/{transcript_number}` | `Transcripts\VerifyTranscriptController` | `transcripts.verify` | public (`routes/web.php`), `throttle:lookups` |

Authorization is the existing model — `role:*` route middleware + ownership. The student route
resolves the caller's own profile (no id in the URL, so no IDOR surface); the SAO route is guarded
by `role:sao,admin`.

## 8. PDF template

`resources/views/pdf/transcript.blade.php`, rendered by `TranscriptPdfRenderer` via mpdf:

- **Header:** SchuLyf brand + institution identity (placeholder name/address until the real reference
  arrives), document title "Official Academic Transcript".
- **Identity block:** student name, matricule, programme, current level, transcript number, issue date.
- **Body:** one table per semester (academic year + semester heading), columns
  `Code · Title · Credits · Score · Grade · Points`; mpdf **repeating header row** + per-page footer
  with page number. Per-semester footer line: `Semester GPA · Credits earned/attempted`.
- **Summary:** cumulative `CGPA · Credits earned / attempted · Total courses`.
- **Footer / verification:** signature line + an embedded **QR (SVG)** pointing at the public
  `transcripts.verify` URL, with the URL and transcript number printed beside it ("Verify authenticity
  at …"). Optional light watermark.

mpdf `tempDir` is set to a writable `storage_path('app/mpdf')` (created if absent) to keep Laravel
Cloud / CI happy.

## 9. Frontend

- **`resources/js/pages/student/results/Index.vue`** — add a PrimeVue `Button` "Download transcript"
  (links to `student.transcript`). Shown when the student has **any** published result. Because the
  page's `courses` prop is *current-cohort only*, the controller passes a dedicated
  `hasTranscript: bool` prop (a cheap `exists()` over all published results across years) — do **not**
  infer availability from `courses.length`.
- **`resources/js/pages/sao/students/Index.vue`** (new) — PrimeVue `DataTable` (paginated, server
  search by matricule/name) with columns matricule/name/programme/level/status + a per-row "Download
  transcript" action linking to `sao.students.transcript`. Wire into the SAO sidebar nav.
- **`resources/js/pages/transcripts/Verify.vue`** (new) — public verify result page mirroring
  `resources/js/pages/receipts/Verify.vue`: valid → identity + per-semester GPA table + CGPA + issue
  date; invalid → a single generic "could not be verified" state (no oracle).

## 10. Audit

`IssueTranscript` records `AuditAction::TranscriptGenerated` on the `Transcript` subject
(`{student_profile_id, transcript_number}`, `userId = issuer`) **only when a new record is created**
(content changed). Re-downloads that reuse an existing snapshot are not separately audited (avoids
noise; matches the issue's "audit-log generation"). Public verification is not audited.

## 11. Tests (Pest feature, SQLite)

- **Service/GPA math** — seed a student with known results across two semesters (including an F and a
  cross-year course); assert snapshot `gpa`, `cgpa`, `credits_earned`, `credits_attempted` exactly.
- **Only-published** — a draft result and a result with missing marks are excluded from the snapshot.
- **Student self-service** — own download → `200`, `application/pdf`, attachment; no published
  results → redirect back with the error flash (no record created).
- **Authorization** — `sao/students/{profile}/transcript`: sao/admin → `200`; lecturer/student/guest
  → `403`/redirect. `sao/students` index guarded the same way.
- **Verification** — valid transcript → summary shown; tampered `snapshot` column → invalid; unknown
  number → invalid (no oracle); forged signature → invalid.
- **Immutability** — `Transcript` update and delete both throw.
- **Dedupe** — generating twice with unchanged results reuses the same `transcript_number` and writes
  no second audit row; publishing a new result then regenerating mints a new number.
- **PDF/QR content** — render the Blade view directly (à la `NotificationGreetingTest`) and assert the
  transcript number + the `transcripts.verify` URL appear.
- **SAO students index** — renders; search filters by matricule/name.

## 12. Docs (docs-refresh)

- **New:** `docs/modules/transcripts.md` (purpose, roles, data model, routes, flow, verification,
  tests, file map); demo seed for a multi-semester student (screenshots).
- **Update:** routes reference (+4 routes); data-model + ER (+`transcripts`, +`transcript_sequences`);
  `docs/security.md` (public throttled verify, no oracle, immutable record); `docs/index.md` (ADR
  count 25 → 28).
- **New ADRs** (continuing from 0025): **0026** GPA scale (4.0 + grade-point map + snapshot-at-issue
  rationale), **0027** PDF generation via mpdf (dependency choice; rejected dompdf/Browsershot),
  **0028** transcript verification (public HMAC/QR, content-digest dedupe, snapshot vs live).

## 13. Dependencies

`composer require mpdf/mpdf simplesoftwareio/simple-qrcode` — both pure PHP (fine on Windows/Laragon
+ Laravel Cloud). Approved in principle 2026-06-22; **confirm the add at task 1 kickoff** (dependency
changes need approval per project rules). Revisit toward `spatie/browsershot` only if the eventual
real reference transcript proves visually elaborate beyond mpdf's fidelity.

## 14. Risks / known limitations

- **mpdf under test/CI:** pure PHP, runs under SQLite/`testing`; set `tempDir` to a storage path to
  avoid permission issues. Slight per-test cost for the PDF-response tests (kept few).
- **Re-registration / multiple profiles:** a transcript is per `StudentProfile` and aggregates *that*
  profile's `CourseResult`s. A user who re-registered under a new profile won't see the old profile's
  results on the new profile's transcript. Out of scope for v1 (noted; no cross-profile merge).
- **Retakes:** the system has no formal retake concept — a repeated course code across years appears
  as separate lines and both count toward CGPA. Accepted for v1.

## 15. File map

**Create:**
`app/Services/TranscriptService.php`, `app/Services/TranscriptPdfRenderer.php`,
`app/Actions/IssueTranscript.php`, `app/Models/Transcript.php`,
`app/Http/Controllers/Student/TranscriptController.php`,
`app/Http/Controllers/Sao/StudentController.php`,
`app/Http/Controllers/Transcripts/VerifyTranscriptController.php`,
`database/migrations/*_create_transcripts_table.php`,
`database/migrations/*_create_transcript_sequences_table.php`,
`database/factories/TranscriptFactory.php`,
`resources/views/pdf/transcript.blade.php`,
`resources/js/pages/sao/students/Index.vue`, `resources/js/pages/transcripts/Verify.vue`,
`docs/modules/transcripts.md`, `docs/adr/0026-*.md`, `docs/adr/0027-*.md`, `docs/adr/0028-*.md`,
plus Pest tests under `tests/Feature/Transcripts/`.

**Modify:**
`app/Enums/AuditAction.php` (+`TranscriptGenerated`),
`app/Http/Controllers/Student/CourseResultController.php` (+`hasTranscript` prop),
`routes/student.php` (+`student.transcript`), `routes/sao.php` (+students index + transcript),
`routes/web.php` (+public `transcripts.verify`),
`resources/js/pages/student/results/Index.vue` (+ download button),
SAO sidebar nav (add "Students"), `composer.json` (+2 deps),
`docs/index.md`, `docs/security.md`, routes + data-model reference docs, `docs/adr/README.md`.

**Delete:** none.
