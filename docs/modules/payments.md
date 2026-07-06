# Payments & tamper-proof school receipts

> GitHub #6 (closes #16 verification). Source of truth verified against shipped code; where the
> [plan](../../plan/payments/plan.md) and the code disagree, the code is documented and the drift is
> noted inline.

## Purpose

A student deposits tuition into one of the institute's bank accounts (UBA, AFG, AFRICLAND, SCB,
SGC), keeps the bank slip, and carries it to the on-campus accounts office, where an accountant
verifies it and issues a **school receipt** — the single proof that "this student paid." Two
real-world failures motivate this module: students lose the paper slip and get stuck (accountants
say they cannot pull bank records), and the paper school receipt is forgeable and shareable. So
this module makes the **payment record durable** (the student uploads the slip, so a lost paper copy
is not fatal) and makes the **school receipt tamper-proof and identity-bound** via an HMAC signature
that anyone can verify at a public endpoint without forgery or reuse going unnoticed. Exam/facility
access-gating and tuition deferrals are a separate module ([#8](exam-gating.md)); this module only
ships the ledger, receipts, verification, and exposes the validated-paid total.

## Roles & abilities

| Role | What they do here | How it is guarded |
|---|---|---|
| **Admin** | Configures fee schedules + installments; can do everything an accountant or owning student can | `routes/admin.php` group (`role:admin`); on every gate by design |
| **Accountant** | Reviews the queue, validates/rejects submissions, views slips | `routes/accountant.php` group (`role:accountant,admin`) |
| **Student** | Reports a deposit (uploads slip), tracks own submissions + running total, prints their receipt | `routes/student.php` group (`role:student,admin`) + per-resource ownership |
| **Anyone (public)** | Verifies a receipt number | No auth; `throttle:lookups` only |

**Authorization is enforced by `role:` middleware + per-resource ownership checks.** The payment
review routes are guarded by the `role:accountant,admin` group middleware. (There is no
`validate-payment` ability gate — the gates were retired in
[ADR-0025](../adr/0025-retire-ability-gates.md); the route group is the enforcement point.) See
[security.md](../security.md) §2 for the authorization model.

Per-resource ownership (not Gates):

- **Slip view/download** (`PaymentSlipViewController`, `PaymentSlipDownloadController`): owner-or-
  reviewer — the reporting student, or an Accountant/Admin (`403` otherwise).
- **Printable receipt** (`Student\PaymentController::receipt`): owning student or Admin only (`403`
  otherwise); an unvalidated payment has no receipt and `404`s.
- **Public verification**: exposes only non-sensitive bound fields, and only when the signature is
  authentic.

## Data model

Four owned tables plus a per-year counter. Money is **integer XAF** throughout (XAF has no minor
unit) — columns are `amount_xaf` / `total_xaf`, stored `unsignedInteger`, cast to `integer`. See
[data-model.md](../data-model.md) for full column detail.

| Model | Key columns | Notes |
|---|---|---|
| `FeeSchedule` | `program_offering_id`, `level`, `academic_year`, `total_xaf` | "What you owe." Unique `(program_offering_id, level, academic_year)` (spans trashed rows). `RecordsAudit` + `SoftDeletes`. |
| `FeeInstallment` | `fee_schedule_id`, `sequence`, `label`, `amount_xaf`, `due_date` | Ordered milestones (`orderBy('sequence')`). Σ `amount_xaf` ≤ schedule `total_xaf`; due dates monotonic by sequence. The thresholds #8 reads. No own audit/soft-delete — managed as a set via delete-and-recreate. |
| `PaymentSubmission` | `student_profile_id`, `academic_year`, `bank`, `amount_xaf`, `bank_reference`, `slip_path`, `slip_original_filename`, `slip_mime_type`, `status`, `reviewed_by`, `reviewed_at`, `rejection_reason` | One row per reported deposit. `academic_year` is **snapshotted** from the student's enrollment at submission so the validated total stays year-attributable after the student advances. `RecordsAudit` + `SoftDeletes`. Indexes `(status, created_at)` for the queue, `(student_profile_id, status)` for the student list/total. |
| `SchoolReceipt` | `payment_submission_id` (unique FK), `receipt_number` (unique), `amount_xaf`, `signature`, `issued_at` | **Immutable** — `booted()` throws on `updating`/`deleting` (like `AuditLog`). `$timestamps = false`. Exactly one per validated submission (unique FK + re-review guard). |
| `receipt_sequences` (table) | `year`, `last_number` | One row per year; `lockForUpdate()` serializes receipt-number issuance. Mirrors the matricule sequence. |

**Enums:** `Bank` (`Uba`/`Afg`/`Africland`/`Scb`/`Sgc`; lowercase `->value`, `->label()` gives the
display name e.g. `UBA`). `PaymentStatus` (`Submitted`/`Validated`/`Rejected`; lowercase `->value`;
`isTerminal()` is `true` for anything except `Submitted`).

**Derived figure** (what #8 consumes): validated-paid for a student-year = Σ `amount_xaf` over that
student's `Validated` submissions — computed inline in `Student\PaymentController::index` and by
`PaymentStandingService`.

## Routes & screens

Pages live in `resources/js/pages/`. **`receipts/Verify` and `student/payments/Receipt` are
deliberately rendered with no app shell** — `app.ts`'s `layout` switch returns `null` for those two
names because they are purpose-built print / public pages (the printable receipt is `window.print()`-
friendly; the verify page is for anonymous visitors). Every other page below uses `AppLayout`.

### Admin — fee configuration (`routes/admin.php`, `role:admin`)

| Method · URI | Name | Page / action |
|---|---|---|
| GET `admin/fees` | `admin.fees.index` | `admin/fees/Index` — schedules + installments, `?trashed=1` to include soft-deleted |
| POST `admin/fees` | `admin.fees.store` | create schedule + installment set (transaction) |
| PATCH `admin/fees/{fee_schedule}` | `admin.fees.update` | update + replace installments |
| DELETE `admin/fees/{fee_schedule}` | `admin.fees.destroy` | soft-delete (installments left for restore) |
| POST `admin/fees/{fee_schedule}/restore` | `admin.fees.restore` | restore (`withTrashed`; refuses while offering is trashed) |

### Student (`routes/student.php`, `role:student,admin`)

| Method · URI | Name | Page / action |
|---|---|---|
| GET `student/payments` | `student.payments.index` | `student/payments/Index` — schedule, validated total, standing, submissions, deferrals |
| POST `student/payments` | `student.payments.store` | report a deposit + upload slip → `Submitted` |
| GET `student/payments/{payment}/receipt` | `student.payments.receipt` | `student/payments/Receipt` — printable, QR-bearing receipt (no app shell) |

### Accountant (`routes/accountant.php`, `role:accountant,admin`)

| Method · URI | Name | Page / action |
|---|---|---|
| GET `accountant/payments` | `accountant.payments.index` | `accountant/payments/Index` — review queue (defaults to `Submitted`) |
| GET `accountant/payments/{payment}` | `accountant.payments.show` | `accountant/payments/Review` — detail + slip viewer |
| POST `accountant/payments/{payment}/validate` | `accountant.payments.validate` | validate → mint receipt |
| POST `accountant/payments/{payment}/reject` | `accountant.payments.reject` | reject with reason |

### Shared file routes (`routes/web.php`, `['auth','verified']`, `throttle:lookups`)

| Method · URI | Name | Controller |
|---|---|---|
| GET `payments/{payment}/slip` | `payments.slip` | `PaymentSlipDownloadController` (attachment) |
| GET `payments/{payment}/slip/view` | `payments.slip.view` | `PaymentSlipViewController` (inline) |

### Public (`routes/web.php`, no auth, `throttle:lookups`)

| Method · URI | Name | Controller |
|---|---|---|
| GET `receipts/verify/{receipt_number}` | `receipts.verify` | `VerifyReceiptController` → `receipts/Verify` (no app shell) |

## Flows

### Submission lifecycle

```
stateDiagram-v2
    [*] --> Submitted: student stores deposit + slip
    Submitted --> Validated: ReviewPaymentAction (Validated) → mints SchoolReceipt
    Submitted --> Rejected: ReviewPaymentAction (Rejected, reason)
    Validated --> [*]
    Rejected --> [*]
    note right of Validated: terminal — re-review blocked
    note right of Rejected: terminal — re-review blocked
```

**1. Report a deposit — `Student\PaymentController::store`** (`StorePaymentRequest`).
Validates `bank` (enum), `amount_xaf` (integer ≥ 1), `bank_reference`, and `slip` (`pdf/jpg/jpeg/png`,
≤ 8 MB). The slip is written to the default disk (`payment-slips/`) **before** the row is created and
deleted if the insert throws (mirrors the application-upload cleanup, AUD-009). `academic_year` is
snapshotted from the student's profile. Status starts `Submitted`. Requires an active student
enrollment, else a validation error.

**2a. Validate (happy path) — `Accountant\ReviewPaymentAction::execute`** with
`PaymentStatus::Validated`. Inside one `DB::transaction`:

1. **Re-fetch under `lockForUpdate()`** so a concurrent review cannot double-decide past a stale
   status check (mirrors AUD-001).
2. **Terminal re-guard** — `$submission->isTerminal()` throws `422` ("This payment has already been
   reviewed.") if already decided.
3. `saveQuietly()` sets `status`, `reviewed_by`, `reviewed_at`, clears `rejection_reason`.
4. **Mints the receipt** (`issueReceipt`, see below) — inside the same transaction.
5. Records `AuditAction::PaymentValidated`.
6. `DB::afterCommit` fires `PaymentReviewed`.

**2b. Reject (rejected path)** with `PaymentStatus::Rejected` (`RejectPaymentRequest` supplies the
reason). Same lock + re-guard. The action additionally throws `422` if the reason is empty, and `422`
if the decision is anything other than Validated/Rejected. Sets `rejection_reason`, **no receipt**,
records `AuditAction::PaymentRejected`, fires `PaymentReviewed`.

### Receipt issuance — `ReviewPaymentAction::issueReceipt`

Runs only on validation, inside the same transaction:

1. `receipt_number` = `SchoolReceipt::nextReceiptNumberForYear($year)` — the one-row-per-year
   `receipt_sequences` counter under `lockForUpdate()`, formatted `RCP-YYYY-00001`. Mirrors the
   matricule generator (AUD-006).
2. **HMAC signature** = `hash_hmac('sha256', payload, config('app.key'))` where the canonical payload
   is the four fields joined by `|`:

   ```
   receipt_number | matricule | amount_xaf | academic_year
   ```

   The key is `APP_KEY` (server-side only). `amount_xaf` here is `$submission->amount_xaf` and
   `academic_year` / `matricule` come from the submission and its student profile.
3. `SchoolReceipt::create(...)` — the unique FK + the terminal re-guard guarantee **exactly one**
   receipt per submission.
4. Records `AuditAction::ReceiptIssued`.

### Verification — `VerifyReceiptController` + `SchoolReceipt::verifies()`

```
sequenceDiagram
    actor Verifier
    Verifier->>VerifyReceiptController: GET receipts/verify/{number}
    VerifyReceiptController->>SchoolReceipt: find by receipt_number (+ bound identity)
    SchoolReceipt->>SchoolReceipt: verifies() — recompute HMAC from CURRENT identity
    Note over SchoolReceipt: expectedSignature() reads matricule + academic_year<br/>off the submission, amount off the receipt
    SchoolReceipt-->>VerifyReceiptController: hash_equals(stored, expected)
    VerifyReceiptController-->>Verifier: valid ⇒ bound identity; invalid ⇒ "invalid" (no detail)
```

`verifies()` re-derives the signature from the receipt's **currently bound** identity and compares
with `hash_equals()` (constant-time). A forged number, an edited amount, or a receipt reused by a
different student all drift the recomputed HMAC and read as invalid. An unknown number and a bad
signature both render `valid: false` with no receipt body — no oracle for which receipt numbers
exist. This is the same posture described in [security.md](../security.md) §4; do not restate the
crypto there, link to it.

## Side effects

**Audit** (`App\Enums\AuditAction`, immutable `AuditLog`):

| When | Action | Subject |
|---|---|---|
| Submission validated | `PaymentValidated` | the `PaymentSubmission` |
| Submission rejected | `PaymentRejected` | the `PaymentSubmission` |
| Receipt minted (on validate) | `ReceiptIssued` | the `SchoolReceipt` |

`FeeSchedule` and `PaymentSubmission` additionally emit generic `Created`/`Updated`/`Deleted`/
`Restored` rows via the `RecordsAudit` trait on their model lifecycle. `FeeInstallment` does **not**
audit (it has no trait; it is a managed child set).

**Events / listeners / mail:**

- `App\Events\PaymentReviewed($submission, $reviewedBy)` — fired `afterCommit` on every terminal
  decision (validate or reject), once each.
- `App\Listeners\SendPaymentReviewedNotification` (`ShouldQueue`, auto-discovered) — mails the
  student. No-ops silently if the student has no email.
- `App\Mail\PaymentReviewedMail` (queued) — markdown `mail.payment-reviewed`, subject
  `"<app> — payment validated"` or `"… rejected"` per status.

No in-app/database notification channel here — email only (the channel strategy itself is #18).

## Tests

| File | Covers |
|---|---|
| `tests/Feature/Admin/Fees/FeeScheduleCrudTest.php` | Schedule `(offering, level, year)` uniqueness, installment-sum/monotonic-date guards, restore rules, admin-only authorization |
| `tests/Feature/Student/ReportPaymentTest.php` | Deposit submission + slip upload, validation rules |
| `tests/Feature/Accountant/ReviewPaymentTest.php` | Validate/reject transitions, terminal re-guard (concurrent double-decide), authorization matrix, notification dispatched |
| `tests/Feature/Payments/SchoolReceiptIssuanceTest.php` | Receipt issued **exactly once** on validate, none on reject, per-year increment (`RCP-2026-00001`/`00002`), signature verifies, tampered amount fails, update/delete blocked |
| `tests/Feature/Payments/VerifyReceiptTest.php` | Public verify binds identity (no auth), unknown number → invalid, tampered → invalid, owning student can print, other student `403`, unvalidated payment `404` |
| `tests/Feature/Payments/PaymentStandingTest.php` | The validated-paid total figure (boundary for #8) |
| `tests/Feature/Files/InlineFileViewTest.php` | Slip inline-view hardening (auth, MIME allowlist, headers) — see [security.md](../security.md) §5 |

## File map

| File | Role |
|---|---|
| `app/Models/FeeSchedule.php` | Schedule model; `installments()` ordered; audited + soft-deletes |
| `app/Models/FeeInstallment.php` | Installment milestone (no audit/soft-delete) |
| `app/Models/PaymentSubmission.php` | The deposit row; `isTerminal()`, `schoolReceipt()` hasOne |
| `app/Models/SchoolReceipt.php` | Immutable receipt; `canonicalPayload`/`computeSignature`/`verifies`/`nextReceiptNumberForYear` |
| `app/Enums/Bank.php` | The five institute banks + `label()` |
| `app/Enums/PaymentStatus.php` | `Submitted`/`Validated`/`Rejected` + `isTerminal()` |
| `app/Actions/Accountant/ReviewPaymentAction.php` | The state machine: lock, re-guard, decide, mint receipt, audit, event |
| `app/Http/Controllers/Admin/Fees/FeeScheduleController.php` | Admin fee CRUD + restore + installment sync |
| `app/Http/Controllers/Student/PaymentController.php` | Student index/store/receipt |
| `app/Http/Controllers/Accountant/PaymentController.php` | Queue/show + validate/reject endpoints |
| `app/Http/Controllers/Payments/PaymentSlipViewController.php` | Inline slip viewer (hardened) |
| `app/Http/Controllers/Payments/PaymentSlipDownloadController.php` | Slip download (attachment) |
| `app/Http/Controllers/Receipts/VerifyReceiptController.php` | Public HMAC verification page |
| `app/Http/Requests/Admin/Fees/FeeScheduleStoreRequest.php` + `FeeScheduleUpdateRequest.php` + `Concerns/ValidatesFeeSchedule.php` | Fee validation (unique schedule, sum ≤ total, monotonic dates, level-in-range) |
| `app/Http/Requests/Student/StorePaymentRequest.php` | Deposit + slip upload rules (MIME allowlist, 8 MB) |
| `app/Http/Requests/Accountant/RejectPaymentRequest.php` | Reject reason rule |
| `app/Events/PaymentReviewed.php` | Fired afterCommit on terminal decision |
| `app/Listeners/SendPaymentReviewedNotification.php` | Queued mailer (auto-discovered) |
| `app/Mail/PaymentReviewedMail.php` | Outcome email (`mail.payment-reviewed`) |
| `resources/js/pages/admin/fees/Index.vue` | Admin fee schedules screen |
| `resources/js/pages/student/payments/Index.vue` | Student payment record |
| `resources/js/pages/student/payments/Receipt.vue` | Printable QR receipt (no app shell; uses `qrcode`) |
| `resources/js/pages/accountant/payments/Index.vue` | Accountant review queue |
| `resources/js/pages/accountant/payments/Review.vue` | Submission detail + slip viewer |
| `resources/js/pages/receipts/Verify.vue` | Public verification result (no app shell) |
| `database/migrations/2026_06_13_195821_create_fee_schedules_table.php` | `fee_schedules` |
| `database/migrations/2026_06_13_195822_create_fee_installments_table.php` | `fee_installments` |
| `database/migrations/2026_06_13_203957_create_payment_submissions_table.php` | `payment_submissions` (+ indexes) |
| `database/migrations/2026_06_13_213709_create_school_receipts_table.php` | `school_receipts` (unique FK) |
| `database/migrations/2026_06_13_213710_create_receipt_sequences_table.php` | per-year counter |
| `routes/student.php` · `routes/accountant.php` · `routes/admin.php` · `routes/web.php` | Route groups (see Routes & screens) |

The printable receipt renders its QR client-side from `verifyUrl` (the `receipts.verify` route)
using the **`qrcode`** npm package (`resources/js/pages/student/payments/Receipt.vue`); there is no
server-side QR/PHP dependency.

---

> Cross-references: [architecture.md](../architecture.md) (request lifecycle, shared inline-viewer
> foundation), [data-model.md](../data-model.md) (columns + relations), [routes.md](../routes.md)
> (full endpoint inventory), [security.md](../security.md) (§4 HMAC receipts, §5 file-viewer
> hardening, §2 authorization), [testing.md](../testing.md) (test conventions). Gating/deferrals are
> in [exam-gating.md](exam-gating.md) (#8).
