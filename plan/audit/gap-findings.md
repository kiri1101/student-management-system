# Gap Audit — Design Docs vs Implementation

**Audit date:** 2026-06-11 · **HEAD:** `e044f81` · **Sources:** `plan/context.md` (§4–§14), project `CLAUDE.md`, memory `project_admin_user_management.md`.
**Method:** 3 passes — deferred-items inventory, §4/§5 contract verification, §7 promise-vs-`route:list` check. All Pest suite facts taken from doc-recorded runs; route facts from `php artisan route:list --except-vendor` (47 routes) + targeted `--path` listings.

---

## [GAP-1] Wire admission-decision email notifications onto the ApplicationDecided event

- Severity: **Critical** / Category: **Gap-Missing** / Location: `app/Events/ApplicationDecided.php:10`, `app/Actions/Sao/DecideApplicationAction.php:80`, `app/Actions/Sao/RestorePriorEnrollment.php:97`; doc: project `CLAUDE.md` problem statement, `plan/context.md:864`, `:913`

**Problem** — The root `CLAUDE.md` problem statement makes notification a core requirement: *"they are notified via mail if they have been admitted or not."* The `ApplicationDecided` event is dispatched after every decision (decide + merge paths), but **no listener exists anywhere**: there is no `app/Listeners/` directory (verified by glob), no `Mail`/`Notification` send for applicants (the only mail in the app is `UserInvitationMail` for staff invites, `app/Actions/Admin/CreateUserAction.php:78`), and `AppServiceProvider` wires only auth-event audit listeners. Phase 9 promised "notification stubs (channel selection deferred)" and shipped only the event; Phase 10 closed the roadmap (`plan/context.md:923` — "§8 design contract is fully satisfied") without ever sending mail. User-facing impact: an admitted or rejected applicant receives **nothing** — they must log in and poll their dashboard, which reproduces the original paper-process pain the system was built to fix.

**Proposed solution** — Implement. Add a queued listener (e.g. `app/Listeners/SendApplicationDecisionNotification.php`) on `ApplicationDecided` that mails the application's `contact_email` (snapshot field, §4.9) with the decision and notes; render distinct copy for Admitted (include matricule + next steps) vs Rejected/Waitlisted vs merged-restore. Keep channel selection simple (mail only) for now; in-app channel can come later.

**Acceptance criteria**
- [ ] Listener registered for `App\Events\ApplicationDecided`; `Mail::fake()`/`Notification::fake()` feature tests assert a message is queued to `application->contact_email` for each of Admitted / Rejected / Waitlisted and for the `RestorePriorEnrollment` merge path.
- [ ] Admitted mail contains the generated matricule; no plaintext credentials in any payload.
- [ ] Listener is queued (`ShouldQueue`) so SAO decide requests don't block on SMTP.
- [ ] `plan/context.md` §14 deferred lists updated to mark this closed.

---

## [GAP-2] Fix "[Admit as new student]" crash when the applicant has a prior (soft-deleted) StudentProfile

- Severity: **High** / Category: **Gap-Divergent** (schema vs §13.4 design — code must change) / Location: `app/Actions/Sao/DecideApplicationAction.php:98-106`, `database/migrations/2026_05_05_120000_create_student_profiles_table.php:14`; doc: `plan/context.md:504` (§13.4)

**Problem** — §13.4 promises the SAO review banner offers two actions for a returning student: `[Restore prior enrollment]` **and** `[Admit as new student]`, the latter "runs the normal admit flow." But `student_profiles.user_id` is `->unique()` (migration line 14) and the unique index includes soft-deleted rows (no partial index in MySQL). `DecideApplicationAction::promoteToStudent()` does a plain `StudentProfile::create([...'user_id' => $application->user_id...])` (line 98) — for a user whose trashed profile still occupies the unique slot this throws a `QueryException` → 500, inside the exact scenario the banner exists for. The staff-profile side solved this identical collision with the restore-or-create `WritesRoleProfile` trait (memory contract), but that trait only handles Lecturer/Accountant/SAO (`app/Actions/Admin/Concerns/WritesRoleProfile.php:23`) — `StudentProfile` is not plumbed through. Test coverage masks it: the only prior-history admit test uses a fresh user with no trashed profile (`tests/Feature/Sao/DecideApplicationTest.php:157-174`).

**Proposed solution** — Implement a restore-or-create branch in `promoteToStudent()`: if `StudentProfile::withTrashed()->where('user_id', …)` exists, restore it and overwrite `matricule` (new year-scoped value), `program_offering_id`, `level`, `academic_year`, `enrolled_at`, `status` — mirroring `WritesRoleProfile::writeProfile()`. Record the prior matricule in the `ApplicationDecided` audit context (e.g. `superseded_matricule`) so history isn't silently lost. Alternative (rejected): dropping the unique on `user_id` would break the `hasOne` relation and the §13.4 restore lookup.

**Acceptance criteria**
- [ ] Feature test: user with a trashed `StudentProfile` + new submitted application → decide `admitted` with `acknowledged_prior_history` → 302 success, profile restored with a fresh matricule, Student role attached, no `QueryException`.
- [ ] Audit `ApplicationDecided` row carries the superseded matricule context key.
- [ ] Existing `RestorePriorEnrollmentTest` and same-role no-churn contract (`ManageUsersTest`) stay green.

---

## [GAP-3] Add employee_id capture to the admin staff-create/edit flow

- Severity: **High** / Category: **Gap-Missing** / Location: `app/Http/Requests/Admin/Users/StoreUserRequest.php:29-65` (no `employee_id` rule), `app/Http/Requests/Admin/Users/UpdateUserRequest.php:14-33` (same), `app/Models/User.php:17` (`#[Fillable]` omits it), `resources/js/pages/admin/users/Create.vue` (grep `employee` → 0 hits across `admin/users/*.vue`); doc: `plan/context.md:86` (§4.6), memory `project_admin_user_management.md` "Other follow-ups"

**Problem** — §4.6 (a FINAL decision): staff accounts are "created by an admin and an `employee_id` is assigned at creation; both email and employee_id work as logins from day one." The column exists (`users.employee_id`, nullable unique) and the Fortify resolver actively uses it (`app/Providers/FortifyServiceProvider.php:63`), the Login page even advertises "Email, employee ID, or matricule" — but **no UI or request path can ever set it**: it's absent from both admin user Form Requests, from all three `admin/users` Vue pages, and from `User::$fillable`. Every staff account in the system has `employee_id = NULL`, so the advertised employee-ID login silently never works for anyone.

**Proposed solution** — Implement. Add optional `employee_id` (`nullable|string|max:255|unique:users,employee_id`) to `StoreUserRequest` + `UpdateUserRequest`, apply via `forceFill()` in `CreateUserAction`/`UserController@update` (keeping it out of `$fillable` per the deliberate UserFactory note), and add the input to `Create.vue`/`Edit.vue` for staff roles.

**Acceptance criteria**
- [ ] Admin creates a Lecturer with `employee_id=emp-1234` → row persisted; POST `/login` with `emp-1234` authenticates (extends `AuthenticationTest` staff case to a UI-provisioned user).
- [ ] Duplicate `employee_id` → 422; blank stays NULL.
- [ ] `employee_id` never appears in `audit_logs.changes` beyond its non-secret value (it's not sensitive — normal audit inclusion is fine; just assert the Created audit doesn't break).

---

## [GAP-4] Surface and restore soft-deleted reference rows in the admin UI

- Severity: **Medium** / Category: **Gap-Missing** (promised for Phase 10, silently dropped from its scope) / Location: `app/Http/Controllers/Admin/References/*` (no restore endpoints in `routes/admin.php:34-63`); doc: `plan/context.md:648` ("Phase 10 will surface trashed rows in the admin UI"), `:910`

**Problem** — The Phase 4 design decision removed `whereNull('deleted_at')` from `Rule::unique(...)` so recreating a soft-deleted department/offering/document-type/requirement is **blocked with a 422**, with the documented escape hatch "they `restore()` the trashed row (Phase 10 will surface trashed rows in the admin UI)" (`context.md:648`). Phase 10 shipped without it (`:910` lists it as still deferred). Net effect today: an admin who soft-deletes a Department (or any reference row) reaches a **dead end** — they can't recreate the same name/code (422) and can't restore it (no route, no UI); only a developer with DB/tinker access can recover. The admin *users* list got exactly this treatment (status filter + `admin.users.restore` route, `routes/admin.php:28-30`; `UserController@index` trashed filter at `app/Http/Controllers/Admin/UserController.php:30-34`), so the pattern exists to copy.

**Proposed solution** — Implement, mirroring the users module: add a trashed/all filter to each reference `index()`, a `POST .../{id}/restore` route per resource (`->withTrashed()`), and a Restore row-action in the four DataTable pages. `RecordsAudit` already emits `Restored` rows for these models (retrofit shipped in 10.1).

**Acceptance criteria**
- [ ] Each of the 4 reference resources has a restore endpoint rejecting non-admins (403) and restoring trashed rows (feature tests per resource).
- [ ] Index pages can list trashed rows and trigger restore; restored row immediately editable.
- [ ] Restoring writes a `Restored` audit row (already automatic — assert it).

---

## [GAP-5] Opt User into RecordsAudit so settings/profile mutations are audited

- Severity: **Medium** / Category: **Gap-Deferred-OK escalating to Gap-Missing** / Location: `app/Models/User.php:22` (`use HasFactory, HasRoles, Notifiable, SoftDeletes, TwoFactorAuthenticatable` — no `RecordsAudit`); doc: `plan/context.md:124` §4.10, `:722`, `:912`

**Problem** — §4.10 scopes the audit log to "Eloquent created/updated/deleted/restored events on **domain models**". `User` is the most security-sensitive domain model, yet it is the only one of 12 not using `RecordsAudit` (grep: 11 models have it). Consequences today: a user changing their **email or name** at `/settings/profile` (`ProfileController@update`) or being soft-deleted via `profile.destroy` leaves **no audit row**; admin-side user edits rely on hand-rolled `AuditLog::record` calls (`CreateUserAction.php:50` has a comment explicitly noting the workaround). Email changes are identity changes in a system where email is a login identifier — this was deferred at Phase 6 (`:722`) and again at Phase 10.2 (`:912`).

**Proposed solution** — Implement: add `use RecordsAudit;` to `User`. `auditExclude()` already strips `password`, `remember_token`, `two_factor_*` and timestamps, so no secret leakage. Then simplify `CreateUserAction` (drop the manual `Created` record, keep `RoleAssigned`) and verify the §13 reactivation path doesn't double-write confusingly (the trait's automatic `Restored` will now coexist with the manual `reactivated: true` row — `context.md:670` already declared that acceptable).

**Acceptance criteria**
- [ ] Updating name/email via settings writes an `Updated` audit row with before/after diff; password change writes **nothing** sensitive (excluded → null diff → no row).
- [ ] Account self-delete writes `Deleted`; reactivation writes trait `Restored` + manual semantic row without test regressions.
- [ ] `CreateUserTest` audit-count assertions updated deliberately (not weakened).

---

## [GAP-6] Resolve the stale ProgramOfferingUpdateRequest TODO: narrowing a level range orphans child rules and in-flight applications

- Severity: **Medium** / Category: **Gap-Divergent** (open TODO referencing a phase that already shipped) / Location: `app/Http/Requests/Admin/References/ProgramOfferingUpdateRequest.php:15-19`; doc: `plan/context.md:626`

**Problem** — The TODO says "(Phase 7): re-validate that narrowing min_level/max_level does not orphan existing LevelCredentialRequirement rows… Phase 7's ApplicationStoreRequest will re-check the range… so the inconsistency stays contained until then." Phase 7 shipped over a month ago; the TODO was never revisited. Actual exposure now: (a) child `level_credential_requirements` outside the new range become inert (their level can never be selected) — confusing but harmless; (b) **already-submitted applications** with a now-out-of-range level sail through `DecideApplicationAction::promoteToStudent()` (`DecideApplicationAction.php:102` copies `$application->level` with no re-check), producing a `StudentProfile.level` outside the offering's advertised range. Nothing crashes, but reference-data integrity silently degrades.

**Proposed solution** — Implement a guard in `ProgramOfferingUpdateRequest`: when `min_level`/`max_level` narrows, fail validation (422 with a count of affected child requirements) unless no `levelCredentialRequirements` fall outside the new range — matching the existing delete-with-children refusal UX. Document (decision, not code) that decided applications keep their historical level. Either way, delete or rewrite the stale "Phase 7" comment.

**Acceptance criteria**
- [ ] PATCH narrowing the range while an out-of-range child rule exists → 422 naming the conflict; widening always succeeds.
- [ ] Stale TODO comment removed/replaced.
- [ ] Feature test in `ProgramOfferingsCrudTest` covers both directions.

---

## [GAP-7] Give Student (and Lecturer/Accountant) dashboards a non-dead-end minimum, and per-role sidebar links

- Severity: **Medium** / Category: **Gap-Deferred-OK** (design said "stubs initially", §7 `context.md:203`) **but now user-hostile for a "complete" admissions flow** / Location: `resources/js/pages/dashboards/Student.vue:31`, `Lecturer.vue:31`, `Accountant.vue:31` (all literally render `<p>Placeholder.</p>`); `resources/js/components/AppSidebar.vue:25-43` (nav = Dashboard + admin-only Users; footer links to `https://github.com/laravel/vue-starter-kit` at `:47-55`)

**Problem** — What a freshly-admitted student actually sees after login: role-priority redirect → `/student/dashboard` → a Card titled "Student Dashboard" containing the word **"Placeholder."** Nothing leaks (no props are passed to these pages — verified), but it's a complete dead end: the sidebar offers only "Dashboard" (which redirects back to the same placeholder) and starter-kit footer links to the Laravel repo/docs. The student's own application history still exists at `/applicant/dashboard` (deliberately role-unguarded, `routes/web.php:28-30`) but **no link anywhere points there** once they hold the Student role. Phase 8 deferred "sidebar/navigation polish" to Phase 10's cleanup (`context.md:810`); Phase 10 deferred it again (`:914`).

**Proposed solution** — Re-scope into a small slice now: (1) Student dashboard shows matricule, programme, level, status from `StudentProfile` + a link to past applications; (2) `AppSidebar` gains role-conditional items (SAO → Applications queue; Admin → References + Audit log; Applicant/Student → My applications / New application), reusing the existing `auth.roles` share; (3) remove or repoint the starter-kit footer links. Lecturer/Accountant placeholders may stay until their domains are designed — but say so on the card instead of "Placeholder."

**Acceptance criteria**
- [ ] Logged-in student sees their matricule/programme and can reach their application history without typing URLs.
- [ ] Sidebar items appear/disappear per role (assert via Inertia props or browser test).
- [ ] No `vue-starter-kit` footer links in production nav.

---

## [GAP-8] Refresh plan/context.md §14: status table, route counts, and the missing admin-user-management module

- Severity: **Medium** / Category: **Doc-Stale** / Location: `plan/context.md:531` ("Last updated: 2026-05-05"), `:546` (status table row "| 10 | … | ⏳ Pending | — |" contradicting §14's own Phase 10.1/10.2 sections at `:867`/`:887` and ":923" "Phases 1–10 are all shipped"), `:877` ("Total routes 36 → 38" — `route:list --except-vendor` now shows **47**)

**Problem** — context.md is the declared source of truth (§1), and §14's own process note (`:927`) requires updating it at every phase-boundary commit. It has drifted: (a) the status table still marks Phase 10 pending while later sections describe it shipped; (b) five post-Phase-10 commits are entirely absent — the whole admin user-management module (Phase A `ac997ac`, B `e99fc2e`, C `f46c02c` — 9 routes under `admin/users`, 3 Vue pages, actions/mails/requests), the login redesign `fc2324b`, and seeder fixes — that module's design contracts live **only** in a memory file; (c) route-count assertions are stale. An auditor (or future Claude session) reading context.md alone would not know `UserController` exists. Additionally the auto-memory index (`MEMORY.md`) still summarizes the module as "Phase A done, B+C pending; Vue pages are stubs awaiting Phase B" — contradicted by the memory file's own body and by the code.

**Proposed solution** — Update the doc (no code change): mark Phase 10 ✅ with its two commits, add a "Post-roadmap: Admin User Management (A/B/C)" subsection summarizing-or-linking the memory contracts, refresh route counts, fix the MEMORY.md index line.

**Acceptance criteria**
- [ ] Status table row 10 shows ✅ `359ed1f` + `9a664da`; §14 mentions commits through `e044f81`.
- [ ] context.md names the user-management module + pointer to `project_admin_user_management.md`.
- [ ] MEMORY.md index line no longer claims B+C pending.

---

## [GAP-9] Consolidate duplicated statusLabel/statusSeverity/degreeLabel helpers into a shared module

- Severity: **Low** / Category: **Gap-Deferred-OK** (tracked at `plan/context.md:915`) — duplication has since grown / Location: `resources/js/pages/applicant/applications/Show.vue:89-99`, `resources/js/pages/dashboards/Applicant.vue:67-77`, `resources/js/pages/dashboards/Admin.vue:56`, `resources/js/pages/sao/applications/Index.vue:99-109`, `resources/js/pages/sao/applications/Review.vue:131-141`, `resources/js/components/admin/AuditLogModal.vue:189`

**Problem** — The doc tracked the trio as duplicated across 3 files; it is now **5 pages + the modal** (six copies of `degreeLabel`, five of `statusLabel`/`statusSeverity`, one `actionSeverity`). A new `ApplicationStatus` case or severity tweak must be hand-synced six ways; the SAO and Applicant severity maps are only identical by convention.

**Proposed solution** — Implement when next touching any of these pages (per the doc's own rule), or do it as a one-shot: extract `resources/js/lib/labels.ts` (or `composables/useStatusDisplay.ts`) exporting the maps; import everywhere; delete local copies.

**Acceptance criteria**
- [ ] Single module owns the maps; `grep "function statusSeverity" resources/js` returns 1 hit.
- [ ] `npm run types:check` + `lint:check` + `build` clean; visual severity unchanged.

---

## [GAP-10] Decide on Pest 4 browser tests (Playwright) or strike them from the plan

- Severity: **Low** / Category: **Gap-Divergent** (doc promised, code rationalized it away — pick one) / Location: no `tests/Browser/` directory exists (verified); doc: `plan/context.md:255` (Phase 2 browser smoke), `:388` (Phase 8 happy-path browser test), `:427` (Phase 10 modal browser test), vs `:903` ("the Phase 10 plan's 'browser test' item is satisfied by the existing HTTP-level coverage")

**Problem** — Three phases of §8 explicitly list Pest browser tests as deliverables; none were ever written (Playwright never set up). Phase 10.2 unilaterally declared HTTP coverage sufficient. The cascading application form, the FileUpload slots, the audit modal's fetch plumbing, and dark-mode rendering are exactly the things HTTP tests can't see — and the memory notes the invite-link click-through "still depends on a human."

**Proposed solution** — Re-scope explicitly: either (a) set up Pest 4 browser testing (Playwright) with three smoke tests (application form cascade happy path, audit modal filter, invite/login flow), or (b) amend §8's three phase specs to remove the browser-test bullets and record the decision. Don't leave the promise dangling.

**Acceptance criteria**
- [ ] Either `tests/Browser/` exists with ≥3 passing smoke tests wired into CI/`composer test`, **or** context.md §8 no longer promises browser tests and §14 records why.

---

## [GAP-11] Audit RoleRevoked when reactivation detaches roles

- Severity: **Low** / Category: **Gap-Divergent** (code should change) / Location: `app/Actions/Fortify/CreateNewUser.php:59` (`$existing->roles()->detach();` with no audit), vs §4.10 `plan/context.md:108` ("explicit calls for non-Eloquent events (… role assigned/revoked …)")

**Problem** — §4.10 names "role revoked" as an explicitly-audited event, and `AuditAction::RoleRevoked` exists (`app/Enums/AuditAction.php:13`) — but the only writer is `ChangeUserRoleAction` (`:45`). The §13 reactivation transaction strips **all** roles from a returning user and records only the `Restored { reactivated: true }` row; which roles were removed is recoverable only by diffing history. For a security-motivated flow ("email recycling is a real risk", `context.md:500`), the revocations themselves should be first-class audit rows.

**Proposed solution** — Implement: before `detach()`, capture `$existing->roles()->pluck('name')` and write one `RoleRevoked` audit row (or one row with the list in `changes`) inside the same transaction, `userId: $existing->id`.

**Acceptance criteria**
- [ ] `RegistrationTest` reactivation case asserts a `RoleRevoked` row listing the stripped roles.
- [ ] No row written when the trashed user had no roles.

---

## [GAP-12] Fix project CLAUDE.md: there is no routes/api.php

- Severity: **Low** / Category: **Doc-Stale** / Location: project `CLAUDE.md` "Database" section ("API versioning is used for routes (follow existing convention in `routes/api.php`)"), vs actual route files `routes/{web,settings,admin,sao,console}.php` only (glob-verified); the versioned endpoints live in `routes/web.php:40-43` under `prefix('api/v1')` with session auth

**Problem** — A future contributor (human or agent) told to "follow existing convention in routes/api.php" will look for a file that doesn't exist and may create one with Sanctum/stateless auth, diverging from the deliberate same-session `web.php` pattern chosen in Phase 8 (`plan/context.md:784`, `:793`).

**Proposed solution** — Update the doc: "Versioned JSON lookups live in `routes/web.php` under the `api/v1` prefix and share the `auth, verified` session middleware; there is no `routes/api.php`."

**Acceptance criteria**
- [ ] CLAUDE.md no longer references `routes/api.php`; states the actual convention.

---

## [GAP-13] Conventions verified compliant (no action) — with two footnotes

- Severity: **Info** / Category: **Doc-Stale (minor) + verification record** / Location: cited inline

Pass-2 contract verification results — **all core §4/§5 conventions hold**:

- **(a) No native ENUMs** — `grep '->enum(' database/migrations` → **0 matches**. All constrained columns are `string` + enum cast (e.g. `2026_05_06_120000_create_applications_table.php`, `app/Models/Application.php`). ✅
- **(b) softDeletes everywhere except audit_logs + role_user** — all 12 domain tables declare `softDeletes()` (users:23, roles:15, departments:17, program_offerings:18, document_types:17, level_credential_requirements:19, 4 profile tables, applications:29, application_documents:21); `role_user` and `audit_logs` correctly omit it. ✅
- **(c) restrictOnDelete on all FKs, with exactly the two documented nullOnDelete exceptions** — 15 FKs use `restrictOnDelete()`; only `audit_logs.user_id` (`2026_05_04_120000:13`) and `applications.decided_by_user_id` (`2026_05_06_120000:26`) use `nullOnDelete()`, both per §6.5/§6.4. No `cascadeOnDelete` anywhere. ✅
- **(d) Email verification** — `User implements MustVerifyEmail` (`app/Models/User.php:19`); `verified` middleware present on the web domain group (`routes/web.php:13`), admin group (`routes/admin.php:12`), SAO group (`routes/sao.php:6`), and most of settings. *Footnote 1:* `GET/PATCH settings/profile` are deliberately `auth`-only (`routes/settings.php:7-12`) — Fortify starter behavior so a user who typo'd their email can fix it pre-verification. This is **better** than the doc's blanket rule; document the exception rather than "fixing" it.
- **(e) PrimeVue for all new UI** — every post-Phase-2 page (`admin/references/*`, `admin/users/*`, `applicant/*`, `sao/*`, `dashboards/*`, `AuditLogModal`) imports PrimeVue components; shadcn-vue remains only on starter auth/settings/layout per policy. ✅
- **(f) Login redirect priority** — `RoleDashboardResolver::PRIORITY` (`app/Services/RoleDashboardResolver.php:16-23`) is exactly Admin > Sao > Accountant > Lecturer > Student > Applicant, with roleless fallback; custom `LoginResponse` bound in `FortifyServiceProvider:27`. ✅
- **(g) Audit immutability** — `AuditLog::booted()` throws on `updating`/`deleting` (`app/Models/AuditLog.php:32-41`); table has no `softDeletes`. *Footnote 2:* immutability is model-event-level only — `AuditLog::query()->update()/delete()` (query-builder mass ops) would bypass it. No such call exists in the codebase; acceptable for now, worth a DB-privilege note at deployment time.
- **(h) Level validated against offering range at every write path** — application submit: `StoreApplicationRequest::levelWithinOfferingRange()` (`app/Http/Requests/Applications/StoreApplicationRequest.php:113-132`); admin requirement CRUD: same closure pattern in `LevelCredentialRequirement{Store,Update}Request`; client-side clamp is UX-only. The two residual holes are GAP-6 (offering narrowing) and `promoteToStudent` copying a historical level (accepted in GAP-6). ✅
- **(Pass 3) Routes vs §7 promises** — all promised routes exist: applicant dashboard/form/show/store, cascading `api/v1` lookups, document download, 6 SAO routes, admin dashboard + audit-logs + 16 reference routes + 9 user-management routes = 47 non-vendor routes; per-role dashboard stubs resolve via `Inertia\Controller` (visible with unfiltered `route:list --path=dashboard`, which is why `--except-vendor` shows 47 not 53 — **not** missing routes). Wayfinder output is current: `resources/js/actions/App/Http/Controllers/**` has a `.ts` per controller including `Admin/UserController.ts`, and `resources/js/routes/**` covers every named group (`admin/users`, `sao/applications`, `api/v1/*`, …). ✅

---

## Gap-Deferred-OK backlog (consciously deferred, still tracked)

| # | Item | Source | Current state |
|---|---|---|---|
| B1 | Payment validation + tamper-proof school receipts (signed QR, lookup) | CLAUDE.md, context.md §10 | Not started; `validate-payment` gate + `PaymentValidated` enum case pre-provisioned (`AppServiceProvider.php:30`, `AuditAction.php`) |
| B2 | Tuition deferral request/approval + exam-access gating | CLAUDE.md, §10 | Not started |
| B3 | Course management (plans, attendance, assignments, results, disputes) | CLAUDE.md, §10 | Not started; `approve-course-plan`/`mark-attendance`/`publish-results` gates exist |
| B4 | Lecturer absence notifications | CLAUDE.md, §10 | Not started |
| B5 | Receipt verification (public/staff lookup) | CLAUDE.md, §10 | Not started |
| B6 | Notification channel selection beyond mail (in-app/SMS) | §10, Phase 9 | Blocked on GAP-1's mail listener landing first |
| B7 | Multi-role role-switcher UI in header | §7 `context.md:190`, §10 | Not started; priority redirect suffices |
| B8 | Phone-as-secondary-identifier for reactivation matching | §13.5 `context.md:506` | Not started; email-only matching in `CreateNewUser.php:33` |
| B9 | Migrate shadcn-vue auth/settings pages to PrimeVue | §10 / CLAUDE.md UI policy | Only when those pages are otherwise modified (login redesign `fc2324b` already touched Login.vue) |
| B10 | Per-user audit-log drill-down on the user record | memory deferred list | Global modal only (`AuditLogModal.vue`) |
| B11 | Bulk staff import (CSV) — separate import action, do NOT widen `CREATABLE_ROLES` | memory scope contract | Not started |
| B12 | Profile-only settings edit for staff (e.g. SAO updating own `scope`) | memory deferred list | Settings expose name/email/password only (`routes/settings.php`) |
| B13 | Human click-through of the invite reset link in a real inbox | memory deferred list | Server-side path verified 2026-05-09; browser walkthrough pending (overlaps GAP-10) |
| B14 | PrimeVue `ConfirmDialog` to replace `window.confirm()` on reference deletes | `context.md:638` | Still `window.confirm()` |
| B15 | Vite bundle size (~913 KB) revisit if perf matters | `context.md:580` | `chunkSizeWarningLimit: 1000` in place; fine locally |

Items the docs list as deferred that this audit **promoted to numbered gaps** (no longer "OK" to defer silently): notification listeners → GAP-1, employee_id capture → GAP-3, trashed reference restore → GAP-4, User `RecordsAudit` → GAP-5, sidebar/dashboard polish → GAP-7, helper duplication → GAP-9, browser tests → GAP-10.
