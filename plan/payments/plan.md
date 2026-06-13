# Plan — #6 Payment Validation + Tamper-Proof School Receipts

**Status:** design agreed 2026-06-13; implementation starts at P1 next session. Backlog item B1 / GH issue #6 (also closes #16 verification in P3).

## Problem (from CLAUDE.md project overview)

A student deposits tuition into one of the institute's bank accounts (UBA, AFG,
AFRICLAND, SCB, SGC), receives a **bank transaction receipt**, and carries it to
the on-campus accounts office, where an accountant verifies it and issues a
**school receipt** — the single source of truth for "this student paid." Two
real-world failures:

1. **Lost bank slip → stuck.** Students lose the paper slip; accountants say they
   cannot pull the bank's records, so no school receipt can be issued. Recurring
   source of conflict.
2. **The school receipt is forgeable / shareable.** It can be tampered with, and
   one student's receipt can be reused by another to enter restricted areas/exam
   halls.

So #6 has two jobs: make the **payment record durable** (a lost slip is not fatal)
and make the **school receipt tamper-proof and identity-bound** (cannot be forged
or shared).

## Agreed design decisions (2026-06-13)

1. **Lost-slip fix = slip upload.** Student uploads the bank slip (image/PDF) at
   submission; it is stored durably so a lost paper copy no longer matters; the
   accountant validates against the upload. Bank-statement CSV reconciliation is a
   possible later enhancement; real bank API integration is out of scope.
2. **Admin owns fee config** (schedules + installments — institutional config like
   the reference data they already manage). Accountants only validate payments.
3. **HMAC-signed receipts, verified server-side.** Signature over a canonical
   payload keyed by APP_KEY; the public verify endpoint re-derives it. (Asymmetric
   signing is unnecessary — no offline third-party verification requirement.)
4. **#8 gating stays out of scope.** #6 ships the ledger + receipts + verification
   and exposes "total validated paid per year"; exam/facility access-gating and
   deferrals belong to #8.
5. **Three phases**, each its own audit + commit (per the phased-implementation
   convention).
6. **Flat fee schedule** per (program offering, level, academic year) — confirmed
   no scholarship/nationality/new-vs-returning tiers for now.

## Conventions applied

String columns + PHP enum casts (never native ENUM); `RecordsAudit` on mutable
models; immutable receipt rows (block update/delete like `AuditLog`); money as
**integer XAF** (`amount_xaf`, XAF has no minor unit); accountant validation
mirrors the hardened SAO decision flow (`DecideApplicationAction`: `lockForUpdate`
re-fetch + status re-guard inside a transaction + audit + queued mail); PrimeVue
per-page imports; named routes / Wayfinder; new route files `require`d from
`routes/web.php`; applicant/student-facing JSON under `api/v1` with
`throttle:lookups`; slip files on the default disk (`$file->store()` /
`Storage::download()`, per AUD-030); per-role authorization via `role:` middleware
+ policies.

## Data model

| Model | Key columns | Notes |
|---|---|---|
| `FeeSchedule` | `program_offering_id` FK, `level` tinyint, `academic_year`, `total_xaf` | Unique `(program_offering_id, level, academic_year)`. The "what you owe." `RecordsAudit`, soft deletes. |
| `FeeInstallment` | `fee_schedule_id` FK, `sequence` tinyint, `label`, `amount_xaf`, `due_date` | Ordered milestones; `Σ amount_xaf` ≤ schedule total. The thresholds #8 will read. |
| `PaymentSubmission` | `student_profile_id` FK, `bank` (enum), `amount_xaf`, `bank_reference`, `slip_path`, `status` (enum), `reviewed_by` FK nullable, `reviewed_at` nullable, `rejection_reason` nullable | One row per bank deposit. Slip on default disk. `RecordsAudit`, soft deletes. |
| `SchoolReceipt` | `payment_submission_id` FK unique, `receipt_number` unique, `amount_xaf`, `signature`, `issued_at` | **Immutable** (booted guard blocks update/delete). Exactly one per validated submission. |

**Enums:** `Bank` (Uba, Afg, Africland, Scb, Sgc); `PaymentStatus` (Submitted,
Validated, Rejected). **AuditAction:** `PaymentValidated` already exists; add
`PaymentRejected`, `ReceiptIssued`.

**Derived figure:** total validated paid for a student-year = `Σ amount_xaf` over
that student's `Validated` submissions for the academic year — the single number
#8 compares against installment thresholds.

## Phase P1 — Fee configuration (Admin)

- Migrations + models `FeeSchedule`, `FeeInstallment` (+ factories; demo rows in
  `DemoReferencesSeeder`).
- Admin CRUD under `routes/admin.php` (mirror the reference-data CRUDs):
  list/create/edit/destroy + soft-delete/restore toggle. Form Requests:
  `total_xaf > 0`, `Σ installments ≤ total`, unique `sequence` per schedule,
  monotonic `due_date`.
- Vue pages `resources/js/pages/admin/fees/` (PrimeVue DataTable + forms).
- Tests: schedule `(offering, level, year)` uniqueness; installment-sum guard;
  restore rules; admin-only authorization.
- Audit + commit.

## Phase P2 — Payment submission + accountant validation

- Migration + model `PaymentSubmission` (+ factory states submitted/validated/rejected).
- **Student** (`routes/student.php`, NEW, `role:student,admin`): record a deposit
  (bank, amount, reference, **slip upload**) → `Submitted`; list own submissions
  with status + running total vs schedule. Slip download via a controller (the
  ApplicationDocument auth pattern).
- **Accountant** (`routes/accountant.php`, NEW, `role:accountant,admin`, mirrors
  `sao.php`): queue (status filter), detail with slip viewer, **Validate** /
  **Reject(reason)** — transaction with `lockForUpdate` re-fetch + status re-guard
  (concurrency-safe), audited, queued notification to the student. New invokable
  `AccountantDashboardController` replaces the placeholder `accountant.dashboard`.
- Tests: submit + upload; validate/reject transitions; concurrent-validate 422;
  authorization matrix; slip-download authorization; notification dispatched on
  terminal state.
- Audit + commit.

## Phase P3 — Tamper-proof school receipt + public verification (#6 + #16)

- Migration + immutable `SchoolReceipt`, issued inside the Validate transaction
  (one per submission). `receipt_number` = year + zero-padded counter (counter
  table, like the matricule generator). `signature` = HMAC over
  `receipt_number|matricule|amount_xaf|academic_year` keyed by APP_KEY.
- Student: download/print receipt carrying a **QR** → verification URL.
- **Public verification** (`GET receipts/verify/{receipt_number}`, no auth):
  re-derives the HMAC; authentic ⇒ shows the **bound identity** (matricule, name,
  amount, date) so reuse-by-another-student is visible; tampered/unknown ⇒
  "invalid." Closes **#16**.
- Tests: receipt issued exactly once on validate; signature verifies; tampered
  payload fails; verify page binds identity; immutability (update/delete blocked).
- Audit + commit, then PR to `main`.

## Cross-cutting

- **Authorization:** students see only their own submissions/receipts; accountants
  validate; admins configure; verification exposes only non-sensitive bound fields.
- **Notifications:** queued mail on validate/reject (auto-discovered listener, like
  `SendApplicationDecisionNotification`). Channel-strategy itself is #18.
- **Storage:** slips on the default disk.
- **Scope guard:** no exam/facility gating, no deferrals (#8); P-phases only expose
  the paid-total.

## Sequencing

P1 → P2 → P3, ~3 phase commits + a docs/PR commit. Each phase gated (Pint, Pest,
build, vue-tsc, eslint) and `migrate:fresh --seed` locally. P1 small; P2 largest
(state machine + uploads + two role surfaces); P3 medium (crypto + verify page).
