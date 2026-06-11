# Code Quality & Logic-Flaw Audit — Findings

Project: student-management-system (Laravel 13, PHP 8.4, Inertia v3 + Vue 3)
Audit date: 2026-06-11 · Test suite at time of audit: **380 passed / 1248 assertions** (SQLite in-memory)

Ordering: Critical → High → Medium → Low → Info.

---

## [QUAL-1] Lock and re-check application status inside SAO action transactions to prevent stale-state decisions

- Severity: Critical / Category: Quality (race condition) / Location: `app/Actions/Sao/DecideApplicationAction.php:47`, `app/Actions/Sao/DecideApplicationAction.php:59-84`, `app/Actions/Sao/TriageApplicationAction.php:23-34`, `app/Actions/Sao/RestorePriorEnrollment.php:47-51`

**Problem** — All three SAO actions check the application's status on the model instance that route binding loaded *before* the transaction opens, and never lock or re-read the row inside the transaction. Concrete interleaving with two SAOs on the same application:

1. SAO-A opens `/sao/applications/42` and clicks **Admit**; SAO-B has the same page open and clicks **Reject**.
2. Both requests load Application 42 with `status = under_review`; both pass `isTerminal()` (`DecideApplicationAction.php:47`).
3. A's transaction commits: status → `admitted`, a `StudentProfile` is created, the `Student` role is attached.
4. B's transaction then commits: `fill(...)->saveQuietly()` (`DecideApplicationAction.php:60-65`) blindly overwrites status → `rejected`. Nothing rolls back A's side effects.

End state: a **Rejected application whose applicant holds an active StudentProfile, a matricule, and the Student role** — privileged inconsistent state that contradicts the "terminal statuses are final" invariant and the audit trail (two `ApplicationDecided` rows with conflicting decisions). The Admit+Admit variant instead crashes on the `student_profiles.user_id` unique index (see QUAL-2). `TriageApplicationAction` has the same stale-read pattern (terminal-then-triage overwrites `decision_notes` on a freshly decided app), and `RestorePriorEnrollment` can re-withdraw an application decided between page load and click.

This is unguarded at every level: there is no optimistic-lock column, no `lockForUpdate`, and the status checks run outside the transaction. Note also that the `status` column is mass-assignable (`app/Models/Application.php:25`) and the model enforces nothing on save — the state machine lives only in the callers, so any future write path (e.g. an application-edit endpoint) can flip status freely.

**Proposed solution**
- Inside each action's `DB::transaction`, re-fetch the row with `Application::whereKey($application->id)->lockForUpdate()->firstOrFail()` and re-run the guard (`isTerminal()` / `canTransitionTo()`) against the locked instance before writing. Throw the existing `ValidationException` when the re-check fails.
- Move the pre-transaction guards inside the transaction (keep an early cheap check for UX if desired, but the authoritative check must be on the locked row).
- Optionally add a model-level safety net: an `updating` hook (or dedicated `transitionTo()` mutator) that throws when `status` changes from a terminal value, so no future code path can resurrect a finalized application.

**Acceptance criteria**
- [ ] `DecideApplicationAction`, `TriageApplicationAction`, and `RestorePriorEnrollment` all acquire `lockForUpdate()` on the application row and re-validate status inside the transaction.
- [ ] Feature test: deciding an application whose status was changed to a terminal value after instantiation (simulate by mutating the DB row between binding and action call) throws `ValidationException` and writes nothing.
- [ ] Direct attempts to save a terminal→anything status change outside the actions are rejected (unit test on the model hook, if implemented).
- [ ] Existing 380 tests still pass.

---

## [QUAL-2] Guard Admit against an existing StudentProfile (returning students crash with a 500)

- Severity: High / Category: Quality (logic flaw) / Location: `app/Actions/Sao/DecideApplicationAction.php:87-106`, `database/migrations/2026_05_05_120000_create_student_profiles_table.php:14`

**Problem** — `student_profiles.user_id` is UNIQUE (including soft-deleted rows). `promoteToStudent()` unconditionally `StudentProfile::create(...)`. Two realistic scenarios crash:

1. **Returning student** (the exact §13.4 case the Review page is built for): the applicant has a soft-deleted prior profile, which the SAO sees under "prior history" (`ApplicationReviewController.php:132-146`). If the SAO clicks **Admit** instead of **Restore prior enrollment** — both buttons are enabled — `create()` violates the `user_id` unique index → `QueryException`, HTTP 500, decision lost. Nothing in the UI or backend prevents choosing Admit here.
2. **Double admission**: a user with two pending applications (allowed today, see QUAL-4) is admitted on the first; admitting the second 500s the same way.

**Proposed solution** — In `promoteToStudent()`, look up `StudentProfile::withTrashed()->where('user_id', ...)->first()` inside the transaction:
- Active profile exists → throw `ValidationException` ("Applicant is already an enrolled student.").
- Trashed profile exists → either throw with a message directing the SAO to the Restore-prior flow, or (product decision) restore-and-update it in place. Throwing is the minimal safe fix.

**Acceptance criteria**
- [ ] Admitting an application whose applicant has an active StudentProfile returns a 422 validation error, not a 500.
- [ ] Admitting an application whose applicant has a soft-deleted StudentProfile returns a 422 pointing at the restore-prior flow (or performs the documented merge).
- [ ] No `StudentProfile` row, role change, or `ApplicationDecided` audit row is written in the rejected cases.
- [ ] Feature tests cover both branches.

---

## [QUAL-3] Close the Draft hole in DecideApplicationAction and RestorePriorEnrollment

- Severity: High / Category: Quality (state machine) / Location: `app/Actions/Sao/DecideApplicationAction.php:47-57`, `app/Actions/Sao/RestorePriorEnrollment.php:47-51`, `app/Models/Application.php:75-110`

**Problem** — The transition matrix as implemented:

| From \ To | Submitted | UnderReview | DocsRequested | Admitted | Rejected | Waitlisted | Withdrawn |
|---|---|---|---|---|---|---|---|
| **Draft** | `canTransitionTo`: NO — but `decide`/`restorePrior` don't call it | — | — | **YES via decide** | **YES via decide** | **YES via decide** | **YES via restorePrior** |
| Submitted/UnderReview/DocsRequested | yes | yes | yes | yes | yes | yes | yes (restore-prior only) |
| Terminal (Admitted/Rejected/Waitlisted/Withdrawn) | no | no | no | no | no | no | no |

`TriageApplicationAction.php:23` correctly delegates to `canTransitionTo()`, which excludes Draft (not in `INTERIM_STATUSES`, `Application.php:87-91`). But `DecideApplicationAction.php:47` and `RestorePriorEnrollment.php:47` only check `isTerminal()` — Draft is not terminal, so **a Draft (never-submitted) application can be Admitted, Rejected, Waitlisted, or Withdrawn directly**, skipping submission entirely. An admit of a Draft creates a StudentProfile for someone who never submitted documents.

Exposure today: no production endpoint creates Draft rows (`ApplicationController::store` always writes `Submitted`, `ApplicationController.php:157`), but `ApplicationFactory` defaults to Draft (`database/factories/ApplicationFactory.php:33`), the enum case ships, the SAO index accepts `status[]=draft` as a filter (`ApplicationReviewController.php:67`), and the SAO show page renders Draft applications with action buttons enabled (`is_terminal` is false, `ApplicationReviewController.php:168`). The moment a "save draft" feature lands — clearly planned given the enum and the `whereNotIn('status', [ApplicationStatus::Draft])` carve-out at `ApplicationReviewController.php:151` — this becomes a live exploit (applicant gets a colluding SAO to admit an empty draft, or simply a crash/confusion source).

**Proposed solution** — Replace the bare `isTerminal()` guards with `canTransitionTo($decision)` in `DecideApplicationAction` and `canTransitionTo(ApplicationStatus::Withdrawn)` in `RestorePriorEnrollment`, so all transition authority flows through the one matrix on the model. Additionally exclude Draft from the SAO index status whitelist (or keep it visible but read-only) so drafts never surface as actionable.

**Acceptance criteria**
- [ ] `decide` on a Draft application returns 422; no profile/role/audit-decision writes occur.
- [ ] `restorePrior` on a Draft application returns 422.
- [ ] `canTransitionTo()` is the single guard used by all three SAO actions (asserted by feature tests for each action × Draft).
- [ ] Decision made and documented (test) on whether Draft rows appear in the SAO index.

---

## [QUAL-4] Add a duplicate/pending-application guard to submission

- Severity: High / Category: Quality (logic flaw) / Location: `app/Http/Controllers/Applications/ApplicationController.php:141-199`, `app/Http/Requests/Applications/StoreApplicationRequest.php:29-57`, `database/migrations/2026_05_06_120000_create_applications_table.php`

**Problem** — Nothing limits how many applications a user may have in flight. There is no unique index on `applications(user_id, program_offering_id)`, no validation rule, and no controller check. Consequences:

- An applicant can double-click submit (or replay the POST) and create two identical `Submitted` applications for the same offering — both land in the SAO queue, doubling triage work and risking contradictory decisions (Admit one, Reject the twin).
- A user who is **already an admitted Student** can keep submitting applications (the `auth + verified` route group at `routes/web.php:33` has no role restriction, and the role auto-attach at `ApplicationController.php:183` simply skips users who already have roles). Admitting any of those crashes per QUAL-2.
- Two concurrent submissions are two independent transactions; there is no constraint for the DB to enforce, so even a "check-then-insert" fix must be paired with an index.

**Proposed solution**
- Business rule (minimal): reject submission when the user already has an application in a non-terminal status (`whereIn('status', [...interim, draft])`) — implement as a validation `Rule` in `StoreApplicationRequest::rules()` plus a re-check inside the `DB::transaction` in `store()`.
- Back it with a partial-style DB guard: MySQL lacks filtered unique indexes, so either a generated column (`active_key = IF(status IN (...), user_id, NULL)` with a unique index) or at minimum re-checking inside the transaction after `lockForUpdate()` on the user's application rows.
- Decide and encode whether an admitted Student may apply again (next academic year scenario) — if yes, the rule should be "no concurrent *pending* application" rather than "one application ever".

**Acceptance criteria**
- [ ] Submitting while a pending (Draft/Submitted/UnderReview/DocumentsRequested) application exists returns a 422 with a clear message.
- [ ] Replayed/double-clicked POSTs cannot create two pending rows (concurrent-safe via constraint or in-transaction locked re-check).
- [ ] Behavior for already-admitted Students is explicitly tested (allowed or blocked per product decision).

---

## [QUAL-5] Make matricule generation collision-proof (count-based scheme + gap-lock edge cases)

- Severity: High / Category: Quality (race condition / availability) / Location: `app/Models/StudentProfile.php:68-75`, `app/Actions/Sao/DecideApplicationAction.php:89-96`

**Problem** — `nextMatriculeForYear()` computes `COUNT(matricule LIKE 'stm-{year}-%') + 1`; `promoteToStudent()` precedes it with a `SELECT … FOR UPDATE` over the same rows. Careful analysis of whether this is actually safe under MySQL InnoDB REPEATABLE READ:

- **N ≥ 1 existing rows for the year — safe.** T1's locking read takes exclusive next-key locks on the matching `matricule` index records; T2's identical locking read blocks on them. After T1 commits (having inserted row N+1), T2 resumes. Crucially T2's subsequent `count()` is its *first consistent read*, so its snapshot is established *after* T1's commit and sees N+1 rows → generates N+2. The serialization works, but only because of the fragile accident that no consistent read happens earlier in T2's transaction. Any future code that reads anything before the lock (e.g. an added query in `execute()`) silently breaks this and reintroduces duplicate `count+1` values (then caught as a unique-violation 500 by `student_profiles.matricule` unique).
- **N = 0 (first admit of the year) — not safe.** A locking read over an empty range takes only *gap locks*, and gap locks are mutually compatible: T1 and T2 both acquire them and both proceed. Both `count()` = 0, both generate `stm-{year}-0001`, both INSERT. Each insert's insert-intention lock conflicts with the *other* transaction's gap lock → InnoDB deadlock (error 1213); one SAO's decision dies with a 500 and is rolled back. So every academic year's first concurrent admit window is a crash risk.
- **Force-delete poisons the sequence permanently.** `withTrashed()` covers soft deletes, but if any `student_profiles` row for the year is ever hard-deleted (manual cleanup, GDPR purge), `count` drops by 1 while the max suffix stays — `count+1` now equals an existing matricule, and **every subsequent admit for that year fails on the unique index, forever**, until someone manually reconciles.
- **Tests cannot catch any of this**: SQLite ignores `lockForUpdate()` and the suite runs single-connection (`phpunit.xml:26-27`). The "sequential matricules" test (`tests/Feature/Sao/DecideApplicationTest.php:68`) only proves the serial case.

**Proposed solution** — Replace count-based numbering with one of:
1. `MAX(CAST(SUBSTRING_INDEX(matricule,'-',-1) AS UNSIGNED)) + 1` computed *in the same locking read* (still has the N=0 gap-lock deadlock, but is immune to force-deletes), or — preferred —
2. a `matricule_sequences (year PK, next int)` table: `UPDATE … SET next = LAST_INSERT_ID(next + 1) WHERE year = ?` (or `SELECT … FOR UPDATE` on the seeded row). A single-row exclusive lock has no empty-range/gap-lock problem, serializes cleanly, and survives force-deletes.
Keep the `matricule` unique index as the last-resort backstop, and catch the unique violation to retry once.

**Acceptance criteria**
- [ ] Matricule generation no longer derives from `COUNT(*)`.
- [ ] First-admit-of-year concurrent path acquires a record lock (sequence row), not just gap locks — documented in the code.
- [ ] Force-deleting a profile row does not affect subsequent matricule generation (regression test).
- [ ] A comment/test documents that SQLite cannot exercise the locking behavior, so the MySQL semantics are explained inline.

---

## [QUAL-6] Block (or handle) soft-deleting a ProgramOffering that live applications/profiles reference

- Severity: Medium / Category: Quality (logic flaw → 500s) / Location: `app/Http/Controllers/Admin/References/ProgramOfferingController.php:68-84`, `app/Http/Controllers/Applications/ApplicationController.php:43-51` and `:108-116`, `app/Http/Controllers/Sao/ApplicationReviewController.php:94-102` and `:188-196`

**Problem** — `destroy()` only refuses deletion when `levelCredentialRequirements()->exists()`. Applications and student profiles referencing the offering are not checked — `restrictOnDelete` FKs only stop *hard* deletes, and this is a *soft* delete. After an admin soft-deletes an offering that has applications:

- Applicant dashboard (`ApplicationController.php:45`: `$application->programOffering->id`) → `programOffering` relation resolves to `null` (SoftDeletes global scope) → "Attempt to read property on null" → 500 for the applicant on their own dashboard.
- Applicant Show page (`:109`), SAO queue (`ApplicationReviewController.php:96`), and SAO Review page (`:189`) all break the same way.

**Proposed solution** — In `ProgramOfferingController::destroy()`, also refuse when `Application::where('program_offering_id', $id)->exists()` or `StudentProfile::where('program_offering_id', $id)->exists()` (mirroring the existing LCR guard and its toast). Alternatively/additionally, make the read paths resilient: load the relation `withTrashed()` (`->programOffering()->withTrashed()`) wherever applications are rendered, so historical records keep displaying even if the guard is bypassed by tinker/seeders.

**Acceptance criteria**
- [ ] Deleting an offering with applications or student profiles returns the error toast and does not delete.
- [ ] Applicant dashboard/show and SAO index/review render correctly for an application whose offering was soft-deleted directly in the DB (withTrashed display), or such state is provably unreachable.
- [ ] Feature tests cover both the guard and one render path.

---

## [QUAL-7] Protect the always-required NID/BIRTH document types from deletion (submission bricker)

- Severity: Medium / Category: Quality (logic flaw) / Location: `app/Http/Controllers/Admin/References/DocumentTypeController.php:55-71`, `app/Http/Requests/Applications/StoreApplicationRequest.php:19` and `:105-111`, `app/Http/Controllers/Applications/ApplicationController.php:75-78` and `:172`

**Problem** — `ALWAYS_REQUIRED_CODES = ['NID', 'BIRTH']` are hardcoded by code-string, *not* represented as `level_credential_requirements` rows. `DocumentTypeController::destroy()` only refuses deletion when LCR rows reference the type — so NID/BIRTH are freely deletable. After an admin soft-deletes the NID type:

1. The application form no longer renders an NID upload slot (`ApplicationController::create()` queries non-trashed types, `:75-78`).
2. `StoreApplicationRequest` still demands `documents.NID` (`requiredDocumentCodes()` always merges the hardcoded pair) → **every application submission fails validation with an error about a field the form cannot show**. Admissions are silently bricked platform-wide.
3. If a client submits an NID file anyway, `documentTypeIdMap()` (trashed-excluded query) lacks the `NID` key → undefined array key at `ApplicationController.php:172` → 500.

**Proposed solution**
- Add the always-required codes as a guarded list in `DocumentTypeController::destroy()` (refuse with a toast), and also refuse when `application_documents` reference the type (same orphaned-render concern as QUAL-6).
- Defensive: in `ApplicationController::store()`, skip/throw cleanly when `$documentTypeIds[$code]` is missing instead of relying on array access.
- Consider deriving `ALWAYS_REQUIRED_CODES` from a flag column on `document_types` (e.g. `is_core`) so the invariant lives in data, not two hardcoded constants (`StoreApplicationRequest.php:19` duplicates the same pair queried at `ApplicationController.php:77`).

**Acceptance criteria**
- [ ] Deleting NID/BIRTH (or any type referenced by application_documents) is refused with an explanatory toast.
- [ ] Submission flow never hits an undefined-array-key path for missing document types (422 with a clear message instead).
- [ ] Feature test: soft-deleting NID then visiting/submitting the application form behaves gracefully.

---

## [QUAL-8] Fix the date-of-birth timezone shift in the application form

- Severity: Medium / Category: Quality (bug now) / Location: `resources/js/pages/applicant/applications/Create.vue:232-234`

**Problem** — `new Date(data.date_of_birth).toISOString().slice(0, 10)` converts the DatePicker's *local-midnight* Date to UTC before slicing the date. Cameroon is UTC+1: local `1999-05-10T00:00:00+01:00` becomes `1999-05-09T23:00:00Z` → the submitted DOB is **one day earlier than the user picked** for every user in a positive-offset timezone (i.e. the entire target audience). The stored value then renders wrong on the Show/Review pages and in any future eligibility logic.

**Proposed solution** — Format using local date parts instead of `toISOString()`; the project already has the correct pattern in `resources/js/components/admin/AuditLogModal.vue:73-83` (`toIsoDate()` building `YYYY-MM-DD` from `getFullYear/getMonth/getDate`). Extract that helper into `resources/js/lib/` and reuse it in `Create.vue`.

**Acceptance criteria**
- [ ] Picking 1999-05-10 in a UTC+1 browser persists `1999-05-10` (browser test or unit test on the shared helper).
- [ ] The helper is shared, not re-duplicated (see QUAL-13).

---

## [QUAL-9] Don't write uploaded files inside the DB transaction (orphaned files on rollback)

- Severity: Medium / Category: Quality (transaction boundary) / Location: `app/Http/Controllers/Applications/ApplicationController.php:146-194` (file write at `:168`)

**Problem** — `$file->store('applications')` executes inside the `DB::transaction` closure. If anything later in the closure throws — a subsequent document insert, the `AuditLog::record` insert, the role attach (`:183-190`), or a deadlock — the DB rolls back completely (good: no orphaned rows) but **every file already written stays on disk forever** with no DB row pointing at it. There is no cleanup job, so retries by the user double the orphans. The inverse failure (storage write throws mid-loop) is handled correctly by the rollback.

**Proposed solution** — Collect stored paths in an array and wrap the transaction in try/catch: on exception, `Storage::delete($paths)` before rethrowing. (Alternative: store files first, then run the DB transaction, with the same compensating delete on failure — slightly cleaner separation.) Keep using the default disk consistently (see QUAL-16).

**Acceptance criteria**
- [ ] A forced failure after the first document write leaves zero files in the applications directory (test with `Storage::fake`).
- [ ] Successful submission behavior unchanged.

---

## [QUAL-10] Add error handling to Create.vue's cascading-lookup fetches

- Severity: Medium / Category: Quality (error handling) / Location: `resources/js/pages/applicant/applications/Create.vue:139-206` (watchers), `:117-137` (`fetchJson`)

**Problem** — The `degree_program` and `level` watchers call `fetchJson` inside `try { … } finally { … }` with **no catch**. When `/api/v1/program-offerings` or `/api/v1/level-requirements` fails (session expiry → redirected HTML, network error, 500), the error becomes an unhandled promise rejection: the department dropdown just stays empty/disabled with zero feedback, and the user is stuck on a form that looks broken. The sibling component `AuditLogModal.vue:117-147` handles the identical pattern correctly (catch → `errorMessage` ref → `<Message severity="error">`), so the two fetch sites have drifted.

Secondary: rapid `degree_program` changes have no stale-response guard — a slow earlier response can overwrite the offerings for the currently selected programme (no AbortController/sequence token).

**Proposed solution** — Mirror the AuditLogModal pattern: catch, set an `errorMessage` ref rendered via PrimeVue `Message` (with a retry affordance), and clear it on success. Add an in-flight request token (increment per watch invocation; ignore responses whose token is stale) or `AbortController`.

**Acceptance criteria**
- [ ] A failed offerings/requirements fetch shows a visible error message and does not leave an unhandled rejection in the console.
- [ ] Out-of-order responses cannot populate the dropdown for a previously selected programme.

---

## [QUAL-11] Harden CreateNewUser against concurrent registration (unique-violation 500 and unlocked restore)

- Severity: Medium / Category: Quality (race condition) / Location: `app/Actions/Fortify/CreateNewUser.php:31-76`

**Problem** — Two check-then-act windows:

1. **Fresh email, two concurrent registrations**: both requests pass the `unique` validation rule (no row exists yet), both reach `User::create` (`:72`); the `users.email` unique index lets one through and the other dies with a raw `QueryException` → HTTP 500 instead of the friendly 422 the validator would have produced a millisecond earlier.
2. **Soft-deleted email, two concurrent re-registrations**: both find the trashed row (`:33`), both enter the transaction (`:49`), both `restore()` + `forceFill()->save()` — last writer's name/password wins silently and two `Restored` audit rows are recorded for one logical event. The trashed row is never locked (`withTrashed()->first()`, no `lockForUpdate`), and a concurrent **admin restore** (`UserController::restore`, `app/Http/Controllers/Admin/UserController.php:158-169`) interleaved with a public re-registration can produce a user whose password was overwritten by a stranger-timing artifact. Low probability, but registration is the one unauthenticated write path in the app.

**Proposed solution** — Wrap the whole decision in the transaction: inside `DB::transaction`, re-fetch `User::withTrashed()->where('email', …)->lockForUpdate()->first()` and branch on that locked row. Catch `UniqueConstraintViolationException` from the create branch and convert it to the same `ValidationException` the `unique` rule produces.

**Acceptance criteria**
- [ ] A duplicate-email race produces a 422 with the standard "email already taken" message, never a 500 (testable by pre-inserting the row between validation and create via a hook, or unit-testing the catch).
- [ ] The restore branch operates on a row fetched with `lockForUpdate()` inside the transaction.
- [ ] Exactly one `Restored` audit row per successful reactivation.

---

## [QUAL-12] Centralize the duplicated status/severity/degree/role label maps in the Vue layer

- Severity: Medium / Category: Quality (duplication that will drift) / Location: `resources/js/pages/applicant/applications/Show.vue:58-97`, `resources/js/pages/sao/applications/Review.vue:88-143`, `resources/js/pages/sao/applications/Index.vue:66-111`, `resources/js/pages/dashboards/Sao.vue:15-23`, `resources/js/pages/dashboards/Applicant.vue:36-79`, `resources/js/pages/dashboards/Admin.vue:48-57`, `resources/js/pages/admin/users/Index.vue:66-94`, `resources/js/pages/admin/users/Edit.vue:66-88`

**Problem** — `STATUS_LABELS` + `STATUS_SEVERITY` + `statusLabel()` + `statusSeverity()` are copy-pasted verbatim into **5 pages**; `DEGREE_LABELS` + `degreeLabel()` into **5 pages**; `ROLE_LABELS` + `roleLabel()` into **2 pages**; `formatDate`/`formatDateTime` variants are similarly repeated. Adding one `ApplicationStatus` case (e.g. `deferred` — explicitly on the roadmap) requires editing five files; missing one yields raw `snake_case` values rendered to users with no compile-time signal, because every map is `Record<string, string>` with a fallback. The PHP side has already partially diverged (`UserInvitationMail::roleLabel`, `app/Mail/UserInvitationMail.php:46-65`, labels SAO as "SAO" while role options elsewhere use enum case names via `->name`, `app/Http/Controllers/Admin/UserController.php:210-218`).

**Proposed solution** — Create `resources/js/lib/labels.ts` exporting the maps + helper functions (typed against a union of the enum string values), import it in all eight pages, and delete the local copies. Optionally share the date-format helpers in the same module (also fixes QUAL-8's helper placement). For PHP, expose labels from one place (enum methods `label()` on `ApplicationStatus`/`RoleName`/`DegreeProgram`) and have controllers/mailables consume them.

**Acceptance criteria**
- [ ] One source of truth per map in the frontend; grep finds no page-local `STATUS_LABELS`/`DEGREE_LABELS`/`ROLE_LABELS`.
- [ ] Type error (not silent fallback) when a new enum value is missing from the shared map.
- [ ] PHP role/degree/status labels come from enum methods.

---

## [QUAL-13] Derive server-side status whitelists from the enum/model instead of raw snake_case strings

- Severity: Medium / Category: Quality (duplication that will drift) / Location: `app/Http/Requests/Sao/TriageApplicationRequest.php:17-21`, `app/Http/Requests/Sao/DecideApplicationRequest.php:18-32`, `app/Http/Controllers/Sao/ApplicationReviewController.php:31-35`, `app/Models/Application.php:75-91`

**Problem** — The interim set (`submitted`, `under_review`, `documents_requested`) exists **three times** as raw strings (`TriageApplicationRequest::ALLOWED_STATUSES`, `ApplicationReviewController::DEFAULT_STATUS_FILTER`) and once as enums (`Application::INTERIM_STATUSES`, which is `private` and therefore *cannot* be reused). The terminal-decision set is duplicated between `DecideApplicationRequest::ALLOWED_STATUSES` (strings) and `DecideApplicationAction::ALLOWED_DECISIONS` (enums). The snake_case values `under_review`/`documents_requested` are exactly the kind of string a typo or a future rename (`UnderReview` → `in_review`) silently breaks: `Rule::in` would just start rejecting valid statuses, and the dashboard filter would silently show an empty queue. (Raw-string *comparisons* elsewhere are clean — casts and `->value` are used consistently; the duplication is in these constant lists.)

**Proposed solution** — Make the two lists public canonical API: either public constants on `Application` (`public const INTERIM_STATUSES`, `TERMINAL_STATUSES`) or static methods on `ApplicationStatus` (`interimCases()`, `terminalCases()`, `decidableCases()`), then build every string list via `array_map(fn ($c) => $c->value, …)` / `Rule::enum(ApplicationStatus::class)->only([...])`. Delete the three hand-written string arrays.

**Acceptance criteria**
- [ ] Grep for `'under_review'` in `app/` returns zero hits outside the enum definition.
- [ ] Triage/Decide request whitelists and the SAO default filter are derived from the same constants the state machine uses.
- [ ] Existing transition tests still pass.

---

## [QUAL-14] Extract the triplicated `levelWithinOfferingRange` closure into a shared rule

- Severity: Medium / Category: Quality (duplication that will drift) / Location: `app/Http/Requests/Applications/StoreApplicationRequest.php:113-136`, `app/Http/Requests/Admin/References/LevelCredentialRequirementStoreRequest.php:48-71`, `app/Http/Requests/Admin/References/LevelCredentialRequirementUpdateRequest.php:51`

**Problem** — The same ~24-line closure (offering lookup + min/max range check, with intentional silent bail when `program_offering_id` is missing/invalid so the `exists` rule reports first) is copy-pasted into three Form Requests. The silent-bail rationale is documented in only one copy (`LevelCredentialRequirementStoreRequest.php:42-47`); a future fix applied to one (e.g. validating against trashed offerings, or changing the bail behavior) will not propagate. Note the bail interacts with `ProgramOffering::find()` excluding trashed rows — if the offering is soft-deleted, the level rule silently passes and only the `exists … whereNull('deleted_at')` rule reports, which is correct *today* but only by coincidence of rule ordering across three files.

**Proposed solution** — `php artisan make:rule LevelWithinOfferingRange` implementing `ValidationRule` + `DataAwareRule` (needs `program_offering_id` from the payload); move the closure body and the bail-behavior docblock there; replace all three usages.

**Acceptance criteria**
- [ ] Single `LevelWithinOfferingRange` rule class used by all three requests; closures deleted.
- [ ] Existing level-range tests (submit + admin LCR CRUD) still pass.

---

## [QUAL-15] Unify the storage disk used for application document upload and download

- Severity: Low / Category: Quality (trap for future change) / Location: `app/Http/Controllers/Applications/ApplicationController.php:168` vs `app/Http/Controllers/Applications/DocumentDownloadController.php:28`

**Problem** — Upload uses the **default** disk (`$file->store('applications')` → `config('filesystems.default')`), download hardcodes `Storage::disk('local')`. These coincide only while `FILESYSTEM_DISK=local`. The day the default moves to `s3`/`r2` (a standard production step for user uploads), new uploads land on the cloud disk while downloads keep reading `local` → every download 404s, and nothing in tests catches it because tests inherit the default-local coincidence.

**Proposed solution** — Define a named disk semantic for application documents (e.g. `config('filesystems.application_documents', 'local')` or just always use `Storage::disk(config('filesystems.default'))` symmetrically); reference it from both controllers. One-line change each side.

**Acceptance criteria**
- [ ] Upload and download resolve the disk from the same config expression.
- [ ] A test sets a fake non-default disk as the documents disk and round-trips upload→download.

---

## [QUAL-16] Slim over-exposed Inertia props (full User model and raw profile models)

- Severity: Low / Category: Quality (prop over-exposure) / Location: `app/Http/Middleware/HandleInertiaRequests.php:44`, `app/Http/Controllers/Admin/UserController.php:253-257`

**Problem** — No secrets leak: `password`, `two_factor_secret`, `two_factor_recovery_codes`, `remember_token` are stripped by `#[Hidden]` (`app/Models/User.php:18`). But:
- `auth.user` shares the **entire User model** on every page: `employee_id`, `email_verified_at`, `two_factor_confirmed_at`, `created_at`, `updated_at`, `deleted_at` ride along to all pages (own-user only, so confidentiality impact is nil — but every column added to `users` later, e.g. national-ID or phone, auto-leaks into every page payload until someone remembers this middleware).
- `admin/users/Edit` ships raw `LecturerProfile`/`AccountantProfile`/`SaoProfile` models (`UserController.php:253-257`) — all columns including ids/timestamps — inconsistent with the carefully hand-shaped arrays used everywhere else in this codebase (e.g. `ApplicationReviewController::show`).

**Proposed solution** — Shape `auth.user` to the fields the frontend actually consumes (`id`, `name`, `email`, `email_verified_at`, `two_factor_confirmed_at` if Settings needs it) and shape the three profile payloads explicitly in `UserController::transform()`, matching the sibling-file convention.

**Acceptance criteria**
- [ ] `auth.user` payload contains an explicit field list, not a serialized model.
- [ ] Edit-page profile props are explicit arrays; frontend types updated accordingly.
- [ ] No page regression (TS types compile, existing tests pass).

---

## [QUAL-17] ApplicationDecided event has no listeners — applicant decision notifications are missing

- Severity: Low / Category: Quality (dead code / planned-but-missing) / Location: `app/Events/ApplicationDecided.php`, `app/Actions/Sao/DecideApplicationAction.php:79-81`, `app/Actions/Sao/RestorePriorEnrollment.php:96-98`

**Problem** — Both SAO actions carefully dispatch `ApplicationDecided` via `DB::afterCommit` (correct pattern: the event with its `fresh()` reload only fires on commit, so no listener can ever see uncommitted state). But a grep shows **zero listeners are registered** — the event is currently dead weight, and more importantly the product behavior it exists for (the project brief: "they are notified via mail if they have been admitted or not") is not implemented. Applicants learn their decision only by polling their dashboard. The `$application->fresh()` call inside `afterCommit` also runs an extra query per decision for no current consumer.

**Proposed solution** — Implement the listener that was clearly planned: `SendApplicationDecisionMail` (queued) rendering per-decision templates to `application->contact_email`, registered for `ApplicationDecided`. If notifications are deliberately deferred to a later phase, add a tracking note and keep the event (it is the right seam) — but record the gap so it isn't forgotten.

**Acceptance criteria**
- [ ] Deciding an application queues a decision email to the applicant's contact email (assert with `Mail::fake` in the existing decide tests).
- [ ] Restore-prior (Withdrawn-as-merged) either sends an appropriate variant or is explicitly excluded by test.

---

## [QUAL-18] Role auto-attach and profile restore-or-create are benign-but-noisy check-then-act patterns

- Severity: Low / Category: Quality (race condition, mitigated) / Location: `app/Http/Controllers/Applications/ApplicationController.php:183-191`, `app/Actions/Admin/Concerns/WritesRoleProfile.php:34-46`

**Problem** — For completeness of the concurrency review:
- `store()` checks `roles()->doesntExist()` then `assignRole()`. Two concurrent first submissions both pass the check; actual duplication is prevented by `syncWithoutDetaching` (`app/Models/Concerns/HasRoles.php:43`) plus the `role_user` unique index (`2026_05_01_095452_create_role_user_table.php:17`) — the only artifact is a **duplicate `RoleAssigned` audit row** (two rows claiming the same role grant). Not a corruption risk.
- `WritesRoleProfile::writeProfile()` does `withTrashed()->first()` then restore-or-create. A concurrent double-create would collide on the profiles' `user_id` unique index → `QueryException`. In practice this is an admin-only, single-actor path, so risk is minimal — but the pattern is the same one that bites in QUAL-2/QUAL-11, and a `firstOrCreate`-style retry or `lockForUpdate` would make it uniform.

**Proposed solution** — Either accept and document (a code comment noting the pivot-unique backstop and the possible duplicate audit row), or make the audit write conditional on `assignRole` actually attaching (have `assignRole` return whether a row was inserted, from `syncWithoutDetaching`'s result array). For `writeProfile`, fetch with `lockForUpdate()` inside the calling transaction.

**Acceptance criteria**
- [ ] `assignRole` exposes whether an attachment occurred and the audit row in `ApplicationController::store` is written only on actual attach, OR a comment documents the accepted duplicate-audit possibility.
- [ ] `writeProfile` lookup is lock-protected within its callers' transactions.

---

## [QUAL-19] Close test blind spots on critical paths

- Severity: Low / Category: Quality (test coverage) / Location: `tests/Feature/Sao/DecideApplicationTest.php`, `tests/Feature/Applications/DocumentDownloadTest.php`, `tests/Feature/Applications/SubmitApplicationTest.php`, `tests/Feature/Auth/RegistrationTest.php:77-90`

**Problem** — The 380-test suite is strong on happy paths and authorization, but the following critical-path gaps exist (several correspond to live findings above):

1. **Concurrency: zero coverage** — expected and unavoidable on SQLite in-memory (single connection; `lockForUpdate` is a no-op), but worth a permanent note in the test suite so nobody assumes the matricule/decide locking is proven by tests (QUAL-1, QUAL-5).
2. **Draft edge cases** — only one Draft assertion exists in the whole suite (`tests/Feature/Sao/SaoDashboardTest.php:27`, a dashboard count). No test posts `decide`/`restore-prior` against a Draft application (QUAL-3), and no test pins the SAO index behavior for `status[]=draft`.
3. **Document download for soft-deleted applications** — `DocumentDownloadTest` covers owner/SAO/admin/forbidden/guest/mismatch, but never a trashed Application or trashed ApplicationDocument; the implicit-binding-404 behavior is unpinned and would silently change if `withTrashed()` were added to the route.
4. **Admit when a StudentProfile already exists** (active or trashed) — untested; currently a 500 (QUAL-2).
5. **Double submission** — `SubmitApplicationTest` never posts twice for the same user/offering (QUAL-4).
6. **Reference soft-deletes vs. live references** — no test renders an application whose ProgramOffering was soft-deleted (QUAL-6) or submits after NID/BIRTH DocumentType deletion (QUAL-7).
7. **Audit log with force-deleted subject** — `AuditLogController` only reads `subject_type`/`subject_id` columns (safe by construction, `app/Http/Controllers/Admin/AuditLogController.php:48-61`), but no test pins that the index renders rows whose subject no longer exists.
8. **Staff reactivation via public registration** is partially covered (`RegistrationTest.php:77-90` asserts role count 2→0) — good; an explicit assertion that a reactivated ex-Admin cannot reach `/admin/dashboard` would pin the privilege-strip end-to-end.

**Proposed solution** — Add the eight tests above alongside the fixes for QUAL-2/3/4/6/7 (most are 10–20 lines each using existing factories/states). Add a comment block in `tests/Pest.php` documenting the SQLite locking limitation.

**Acceptance criteria**
- [ ] Each numbered gap has at least one feature test (or a documented decision to skip).
- [ ] The SQLite/locking limitation is documented in the test bootstrap.

---

## [QUAL-20] employee_id login is wired but nothing ever assigns an employee_id

- Severity: Info / Category: Quality (planned-but-missing / inconsistency) / Location: `app/Providers/FortifyServiceProvider.php:63`, `app/Actions/Admin/CreateUserAction.php:38-43`, `app/Http/Requests/Admin/Users/StoreUserRequest.php:29-65`, `database/factories/UserFactory.php:62-73`

**Problem** — The login resolver accepts `users.employee_id` as an identifier and `AuthenticationTest` proves it works — but only via the factory's `staff()` state. The actual admin user-creation flow (`CreateUserAction`, `StoreUserRequest`) never sets `employee_id`; the column stays NULL for every real staff account, so the documented behavior ("staff log in with email or employee_id from day one", `plan/context.md:86`) is unreachable in production. The `UserFactory.php:64` comment says "Phase 10 will plumb it via Form Requests" — Phase 10 shipped without it.

**Proposed solution** — Add `employee_id` (nullable, unique-validated) to `StoreUserRequest`/`UpdateUserRequest` and the admin Create/Edit pages, pass it through `CreateUserAction` (it must move into `#[Fillable]` or be `forceFill`ed alongside `email_verified_at`), and surface it in the invitation email so staff know their identifier.

**Acceptance criteria**
- [ ] Admin can assign/edit an employee_id when provisioning staff; uniqueness validated with a friendly message.
- [ ] Feature test: a staff user created through the real admin flow can authenticate with their employee_id.
- [ ] Invitation email mentions the employee_id when present.

---

## Summary

| Severity | Count | IDs |
|---|---|---|
| Critical | 1 | QUAL-1 |
| High | 4 | QUAL-2, QUAL-3, QUAL-4, QUAL-5 |
| Medium | 9 | QUAL-6 … QUAL-14 |
| Low | 5 | QUAL-15 … QUAL-19 |
| Info | 1 | QUAL-20 |

Test suite: **380 passed, 1248 assertions** (`php artisan test --compact`, SQLite in-memory) — all findings above are latent in passing code.
