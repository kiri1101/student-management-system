# Plan — #8 Tuition Deferral + Payment-Standing Access Gating

**Status:** design agreed 2026-06-14; implementation starts at G1. Backlog item B2 / GH issue #8 (context.md §10). Builds directly on #6 (PR #56, merged): consumes the `FeeInstallment` thresholds + `due_date`s and the validated-paid total.

## Problem (from CLAUDE.md project overview)

During the examination period only students who have paid enough of their tuition
are admitted to exam halls / certain facilities. Tuition is paid in installments,
each with a defined amount and a deadline. After a deadline passes, a student whose
total validated payment is below the cumulative threshold loses access — unless an
**accountant grants a payment deferral**.

## Agreed design decisions (2026-06-14)

1. **Deferral = request → approve.** Student submits a deferral request (reason,
   optional desired new deadline); the accountant approves (sets the extended
   deadline) or rejects (reason). Reuses the #6 review pattern. An *approved*
   deferral whose `new_deadline ≥ today` lifts the gate.
2. **Gating output = standing, not enforcement.** Compute a payment-standing
   (`Cleared` / `Blocked` / `Deferred`) shown to the student, plus a staff
   standing-check page (matricule lookup) for sao/accountant/admin. No hard
   enrollment-status change — payment standing stays decoupled from the
   SAO-owned enrollment lifecycle.
3. **Threshold = #6 installment due-dates (continuous).** `Cleared` when
   `validated_paid ≥ Σ(installment.amount_xaf where due_date ≤ today)` for the
   student's matched schedule. No new period/ExamSession config.

## The standing rule (single source of truth — `PaymentStandingService`)

Given a `StudentProfile` and "as of" date (default today):
- Resolve `FeeSchedule` by `(program_offering_id, level, academic_year)`.
- `required_so_far = Σ installment.amount_xaf where due_date ≤ asOf`.
- `validated_paid = Σ PaymentSubmission.amount_xaf where status=Validated, this
  student, this academic_year`.
- `shortfall = max(required_so_far − validated_paid, 0)`.
- No schedule configured ⇒ `Cleared` (nothing owed yet defined).
- `shortfall == 0` ⇒ **Cleared**.
- `shortfall > 0` and an **active deferral** exists (Approved, `new_deadline ≥
  asOf`, same student-year) ⇒ **Deferred** (until `new_deadline`).
- otherwise ⇒ **Blocked**.

Result shape: `{ status, total_xaf, required_so_far, validated_paid, shortfall,
active_deferral_deadline? }`. Always computed live (date-dependent) — never stored.

## Data model

| Model | Key columns | Notes |
|---|---|---|
| `TuitionDeferral` | `student_profile_id` FK, `academic_year`, `reason`, `requested_new_deadline` (nullable), `status` (enum), `new_deadline` (nullable, set on approve), `reviewed_by` FK users nullable, `reviewed_at` nullable, `decision_notes` (nullable) | One row per request. `RecordsAudit`, soft deletes. Indexes `(status, created_at)`, `(student_profile_id, status)`. |

**Enums:** `DeferralStatus` (Requested, Approved, Rejected) with `label()` +
`isTerminal()`; `PaymentStanding` (Cleared, Blocked, Deferred) — computed only,
with `label()`. **AuditActions:** add `DeferralApproved`, `DeferralRejected`
(request creation is the model's `Created` audit).

## Phases

### G1 — Standing engine + deferral data model
- `DeferralStatus` + `PaymentStanding` enums; `TuitionDeferral` model + migration
  + factory (requested/approved/rejected states); audit actions.
- `PaymentStandingService` computing Cleared/Blocked/Deferred (deferral-aware).
- Tests: the service across all three states + no-schedule + as-of-date boundaries.
- No UI. Audit + commit.

### G2 — Deferral request + accountant review flow
- **Student** (`routes/student.php`): request a deferral (reason, optional desired
  deadline) → `Requested`; list own requests. Refuse a duplicate open request.
- **Accountant** (`routes/accountant.php`): deferral queue (status filter), detail,
  **Approve(new_deadline)** / **Reject(reason)** via `ReviewDeferralAction`
  (lockForUpdate re-fetch + status re-guard in a transaction, audited, queued
  `DeferralReviewed` → mail to the student). Dashboard gains deferral counts.
- Tests: request + duplicate guard, approve/reject transitions, re-review guard,
  authz matrix, notification dispatch.
- Audit + commit.

### G3 — Standing surfaces + close-out
- **Student** payments page: standing badge + figures (required-so-far, validated,
  shortfall) + "Request deferral" CTA when Blocked (wired to G2).
- **Staff standing-check** (`role:sao,accountant,admin`): matricule lookup →
  standing result. Sensitive ⇒ staff-only (not public like receipt verify).
- Tests: standing in the student payload, staff lookup authz + results (cleared/
  blocked/deferred), unknown matricule handling.
- Audit + commit, PR to `main`, close #8.

## Conventions applied

String columns + PHP enum casts; `RecordsAudit` + soft deletes on `TuitionDeferral`;
money as integer XAF; accountant review mirrors `ReviewPaymentAction`
(lockForUpdate + re-guard + audit + queued mail); PrimeVue per-page imports; named
routes / Wayfinder; new surfaces under the existing `routes/student.php` +
`routes/accountant.php` groups; queued mail via an auto-discovered listener; staff
standing-check guarded by `role:` middleware. Out of scope: course management,
lecturer absence, notification-channel selection (#18).
