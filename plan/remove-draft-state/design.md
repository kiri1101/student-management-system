# Design — Remove the `ApplicationStatus::Draft` dead state (#82)

- **Issue:** #82 — `ApplicationStatus::Draft` is a dead state — ship save-as-draft or remove it
- **Date:** 2026-07-05
- **Size:** S (dead-code removal + one ADR)
- **Status:** Approved (owner chose *delete*), pending implementation plan

## 1. Problem

The schema and state machine support a `Draft` application status, but **nothing ever creates a
`Draft` row**. `ApplicationController::store()` writes every application directly as `Submitted`
with `submitted_at = now()`, uploading all required documents in the same request. `Draft` survives
only as: the enum case, the migration column default, a member of `OPEN_STATUSES`, an unreachable
`Draft → Submitted` branch in `canTransitionTo()` (AUD-010), an SAO prior-applications exclusion
filter, the factory default, and the frontend status map. The AUD-010 transition can never fire in
production.

The owner chose **delete the dead state** over building save-as-draft. Save-as-draft was rejected
because the admission application is a one-time, submit-once artifact: supporting partial drafts would
require relaxing the `applications` table's `NOT NULL` columns (nullable everywhere, taxing every
consumer) plus a draft-file storage/expiry lifecycle and "block vs replace" logic for the
one-open-application guard — disproportionate cost for marginal cross-session-resume value. It can be
built deliberately later if the product ever wants it.

## 2. Goals / non-goals

**Goals**
- Remove the `Draft` case and every reference so the application state machine reflects reality:
  applications are **born `Submitted`**.
- Preserve all existing behavior (the one-open-application guard, triage/decide transition guards)
  and keep the full suite green.
- Record the decision in an ADR.

**Non-goals (out of scope)**
- No save-as-draft, resume UI, draft persistence, or draft expiry (the rejected alternative).
- No change to how applications are submitted, triaged, or decided.
- No touching the unrelated `draft` values of `CoursePlanStatus` / `ResultStatus` (different enums,
  still live).

## 3. Deletion footprint

### 3.1 Backend

- **`app/Enums/ApplicationStatus.php`** — remove `case Draft = 'draft';`. (The enum has no `label()`
  method; the Vue pages render `status.value`, so no PHP label removal is needed.)
- **`app/Models/Application.php`:**
  - `OPEN_STATUSES` (currently `[ApplicationStatus::Draft, ...self::INTERIM_STATUSES]`) — drop the
    `Draft` member, leaving `[...self::INTERIM_STATUSES]`. **Keep the constant** as a semantic alias:
    "counts as an open application for the one-open-application rule" is a distinct concept from
    "interim triage state," even though the two sets now coincide. Update its PHPDoc (remove "Draft
    plus").
  - `canTransitionTo()` — remove the `if ($this->status === ApplicationStatus::Draft) { return $next
    === ApplicationStatus::Submitted; }` branch. The interim path's `return $next !==
    ApplicationStatus::Draft;` becomes `return true;` (behaviorally identical — `Draft` no longer
    exists as a possible `$next`). Update the method PHPDoc and the class-level PHPDoc that reference
    the `Draft → Submitted` lifecycle.
- **`database/migrations/2026_05_06_120000_create_applications_table.php`** — change
  `$table->string('status')->default(ApplicationStatus::Draft->value)` to
  `->default(ApplicationStatus::Submitted->value)`. Edit the migration in place (local-only project)
  and re-run `php artisan migrate:fresh --seed`. *(Decided: keep a column default, set to `Submitted`
  — it cleanly expresses "born Submitted." Dropping the default entirely was considered and rejected
  as needlessly strict, since `store()` and the factory always set status explicitly anyway.)*
- **`app/Http/Controllers/Sao/ApplicationReviewController.php`** — in the `priorApplications` query
  (the review screen), remove the `->whereNotIn('status', [ApplicationStatus::Draft])` clause; with
  no drafts it excludes nothing. The SAO queue's `index()` builds `statusOptions` from
  `ApplicationStatus::cases()`, so `Draft` drops from the filter dropdown automatically — no change
  there.
- **`database/factories/ApplicationFactory.php`** — `definition()` currently defaults `'status' =>
  ApplicationStatus::Draft->value` with `'submitted_at' => null`. Change to `'status' =>
  ApplicationStatus::Submitted->value` and `'submitted_at' => now()`. **Keep the `submitted()`
  state** unchanged (it now duplicates the default, but keeping it avoids churning every existing
  `->submitted()` call site and reads explicitly). Both `Draft` and `Submitted` are in
  `OPEN_STATUSES`, so existing "an open application already exists" tests still see an open
  application.

### 3.2 Frontend

- **`resources/js/lib/statusDisplay.ts`** — remove the `draft: { label: 'Draft', severity:
  'secondary' }` line from the **`APPLICATION_STATUS`** map only. The two other `draft:` lines belong
  to the course-plan and result maps (live) — leave them.

### 3.3 Tests

- **`tests/Feature/Sao/DecideApplicationTest.php`** — remove the `it('refuses to decide a draft
  application', …)` case (it creates a factory-default Draft and asserts decide is refused; that
  scenario ceases to exist).
- **`tests/Feature/Sao/TriageApplicationTest.php`** — remove the `it('refuses to triage a draft
  application', …)` case (same reasoning).
- The guard those cases exercised — a non-interim source cannot be triaged/decided — remains covered
  by `TriageApplicationTest`'s *"refuses to triage a terminal application"* and
  `DecideApplicationTest`'s *"refuses a decision when the application was finalized concurrently."*
- Run the full `--testsuite=Unit,Feature` suite and fix any incidental fallout from the factory
  default flip: a bare `Application::factory()->create()` now yields a `Submitted` application with
  `submitted_at` set (previously a `Draft` with null `submitted_at`). Any test that relied on the
  bare-create being non-submitted must set `['status' => …]` / `['submitted_at' => null]` explicitly.

### 3.4 ADR + docs

- **`docs/adr/0024-applications-born-submitted.md`** (new, Accepted) — context: schema + state machine
  supported `Draft`, but `store()` always created `Submitted` and nothing else persisted a draft;
  decision: remove the dead `Draft` state, applications are born `Submitted`; consequences: simpler
  state machine, `OPEN_STATUSES` == interim set, the AUD-010 `Draft → Submitted` transition is gone;
  rejected alternative: save-as-draft (nullable schema + draft-file lifecycle), revisitable later. Add
  the row to `docs/adr/README.md`.
- **`docs/data-model.md`** — the `ApplicationStatus` enum reference drops `draft`.
- **`docs/modules/admissions.md`** — §3 status-enum list drops `Draft`; the `OPEN_STATUSES`
  description ("Draft + the interim trio") and the `canTransitionTo` matrix update; the §5.1
  register→submit flow (which already shows Registered → Submitted) stays accurate — verify no prose
  still calls `Draft` the first status.
- **`docs/index.md`** — ADR count 23 → 24.

## 4. Testing

The change is behavior-preserving, so the gate is the existing suite staying green after the two
draft-specific cases are removed and the factory default is flipped:

- `php artisan test --compact --testsuite=Unit,Feature` — all green (≈ 731 minus the 2 removed draft
  cases, plus any adjusted tests).
- `vendor/bin/pint --format agent`; `npm run build && npm run types:check && npm run lint:check`
  (no chunk-size warning — the only frontend change is the one map line).
- `php artisan migrate:fresh --seed --no-interaction` — clean (verifies the changed column default).

## 5. Risk

Low. The only behavioral surface is `canTransitionTo()`, whose change is provably a no-op (removing a
branch keyed on a status that can no longer exist), and the factory default flip, whose fallout the
full suite surfaces mechanically. No data migration is needed — no `Draft` rows exist to migrate.

## 6. File map

**Modify:** `app/Enums/ApplicationStatus.php`, `app/Models/Application.php`,
`database/migrations/2026_05_06_120000_create_applications_table.php`,
`app/Http/Controllers/Sao/ApplicationReviewController.php`,
`database/factories/ApplicationFactory.php`, `resources/js/lib/statusDisplay.ts`,
`tests/Feature/Sao/DecideApplicationTest.php`, `tests/Feature/Sao/TriageApplicationTest.php`,
`docs/data-model.md`, `docs/modules/admissions.md`, `docs/index.md`, `docs/adr/README.md`.
**Create:** `docs/adr/0024-applications-born-submitted.md`.
