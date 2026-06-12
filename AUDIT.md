# Global Code Review & Audit — Student Management System

- **Date:** 2026-06-11
- **Audited commit:** `e044f81` (branch `master`, clean working tree)
- **Test baseline:** 380 passed / 1248 assertions (Pest, SQLite in-memory)
- **Method:** four parallel domain audits (security, performance, plan-vs-implementation gap, code quality & logic) per the `codebase-audit` skill; Critical/High findings spot-verified against source before publication.
- **Detailed source reports:** `plan/audit/security-findings.md`, `plan/audit/performance-findings.md`, `plan/audit/gap-findings.md`, `plan/audit/quality-findings.md` (cross-referenced below as SEC-n / PERF-n / GAP-n / QUAL-n).
- **Status convention:** every finding starts `Open`. Update to `Fixed in <sha>` as fixes land. Each `## [AUD-nnn]` section is written to be pasted directly into a GitHub issue.
- **Remediation progress:** Fix Phase 1 (`1956bf2`), Phase 2 (`fa56b44`), Phase 3 (`512a97c`), Phase 4 (`a93f9ba`), Phase 5 (`e1255e3`) landed — 433/433 tests green. Fix Phase 6 in progress: AUD-033 done; AUD-034, 029, 026, 032 pending (AUD-029/032 carry user decisions).

## Executive summary

| Severity | Count |
|---|---|
| Critical | 1 |
| High | 8 |
| Medium | 16 |
| Low | 9 |
| **Total findings** | **34** |
| Tracked backlog (consciously deferred, not defects) | 15 — see end of file |

**Top 3 risks in plain terms:**

1. **Concurrent SAO decisions corrupt admission state (AUD-001).** Two staff deciding the same application simultaneously can produce a *Rejected* application whose applicant nevertheless holds an active StudentProfile, a matricule, and the Student role.
2. **The returning-student path is a guaranteed crash (AUD-003).** Admitting a re-registered former student via "Admit as new student" throws a unique-constraint QueryException — the decision is lost with a 500. The covering test only exercises fresh users, so the suite is green while the feature is broken.
3. **Applicants are never notified of decisions (AUD-002).** The `ApplicationDecided` event has no listeners and no mail exists for applicants — the original problem statement's "they are notified via mail" requirement is unmet even though the roadmap was marked complete.

**Verified clean (no action needed, recorded so refactors don't regress):** document-download authorization incl. mismatched-pair 404s; `Application::show` ownership; mass assignment (`#[Fillable]` discipline); no SQL injection, no unsafe `v-html`; audit-log immutability and secret exclusion; no committed secrets; invitation-flow token handling; eager loading across controllers (no N+1 found); Wayfinder bundles current; all §5 conventions (no native ENUMs, soft-delete/restrictOnDelete discipline) fully compliant.

---

## Critical

## [AUD-001] Lock and re-check application status inside SAO decision transactions

- **Severity:** Critical · **Category:** Quality/Security · **Source:** QUAL-1
- **Location:** `app/Actions/Sao/DecideApplicationAction.php:47` (also `TriageApplicationAction`, `RestorePriorEnrollment`)
- **Status:** Fixed in `1956bf2`

**Problem** — All three SAO actions validate `isTerminal()` / transition legality on the model instance resolved *before* `DB::transaction`, and never re-read or lock the row inside it. Interleaving: SAO-A and SAO-B both load the same `Submitted` application; A admits (creates StudentProfile, assigns Student role, commits); B rejects — B's stale `isTerminal()` check passed, so the application ends `Rejected` while the applicant keeps an active StudentProfile, matricule, and Student role. Same race lets triage overwrite a terminal decision.

**Proposed solution** — Inside each action's transaction, re-fetch with `Application::whereKey($application->id)->lockForUpdate()->firstOrFail()` and re-run the status guard on the locked instance before mutating. Throw the same `ValidationException` on conflict so the second SAO gets a clean 422 ("already finalized") instead of silent corruption.

**Acceptance criteria**
- [ ] All three actions guard status on a `lockForUpdate()`-fetched row inside the transaction.
- [ ] Test: deciding an application whose status changed between fetch and execute yields 422 and no StudentProfile/role side effects.
- [ ] Existing 380-test suite stays green.

---

## High

## [AUD-002] Implement applicant notification on application decision (no listeners exist)

- **Severity:** High · **Category:** Gap · **Source:** GAP-1
- **Location:** `app/Actions/Sao/DecideApplicationAction.php:80`, `app/Events/ApplicationDecided.php`
- **Status:** Fixed in `a93f9ba` (queued listener + `ApplicationDecisionMail` to `contact_email`; per-decision copy incl. matricule on admit; restore-prior merge sends its own variant)

**Problem** — `ApplicationDecided` is dispatched after commit but has zero listeners (`app/Listeners/` does not exist; the only Mailable is the staff `UserInvitationMail`). Admitted/rejected/waitlisted applicants receive no email or in-app notification — the core CLAUDE.md requirement ("they are notified via mail if they have been admitted or not") is unmet despite the roadmap claiming the design contract fully satisfied.

**Proposed solution** — Add a queued `SendApplicationDecisionNotification` listener for `ApplicationDecided` dispatching a Mailable (or Laravel Notification, which also gives the in-app `database` channel for free) to `application->contact_email`, with per-decision copy (admitted incl. matricule / rejected incl. notes / waitlisted). Register in `AppServiceProvider` or via event discovery. Queue it (`ShouldQueue`) — see AUD-009 note on queue worker.

**Acceptance criteria**
- [ ] Each terminal decision triggers exactly one notification to the application's contact email.
- [ ] `RestorePriorEnrollment` merge path sends an appropriate variant (or is explicitly excluded — documented).
- [ ] Tests with `Mail::fake()`/`Notification::fake()` for each decision status.

## [AUD-003] Fix promoteToStudent crash for returning students (unique user_id collides with trashed profile)

- **Severity:** High · **Category:** Quality/Gap · **Source:** QUAL-2, GAP-2
- **Location:** `app/Actions/Sao/DecideApplicationAction.php:98`
- **Status:** Fixed in `1956bf2` (trashed profiles restored with a fresh matricule; active profiles keep theirs)

**Problem** — `student_profiles.user_id` is UNIQUE and soft-deletes don't release the slot. `promoteToStudent()` does a plain `StudentProfile::create()`. The §13.4 "[Admit as new student]" path for a returning student (who by design has a trashed StudentProfile) therefore throws a `QueryException` → 500, and the decision transaction rolls back. The only covering test admits a fresh user, so the suite stays green. The admin module already solved this exact problem with `WritesRoleProfile::writeProfile()` restore-or-create.

**Proposed solution** — Restore-or-create: `StudentProfile::withTrashed()->firstWhere('user_id', ...)` — if found, `restore()` + `fill()` with the new matricule/offering/level/year; otherwise `create()`. Decide deliberately whether a *new* matricule is issued or the prior one retained on this path (recommend: new matricule, since "admit as new" is the explicit alternative to "restore prior enrollment"), and record the choice in the audit context.

**Acceptance criteria**
- [ ] Test: user with a trashed StudentProfile is admitted via the normal decide path — succeeds, profile active, no exception.
- [ ] Matricule policy for this path documented in the action and asserted in the test.
- [ ] `acknowledged_prior_history` context still recorded.

## [AUD-004] Harden the soft-deleted-account reactivation flow against anonymous takeover of deactivated accounts

- **Severity:** High · **Category:** Security · **Source:** SEC-1 · **Attacker model:** anonymous
- **Location:** `app/Actions/Fortify/CreateNewUser.php:48-69`
- **Status:** Fixed in `512a97c` (verify-first via the reset flow: register never touches trashed rows and 422s identically to active emails; `PasswordBrokerUserProvider` + `ResetUserPassword` restore on mailbox proof; trashed staff/admin excluded — admin restore only)

**Problem** — Registering with a soft-deleted user's email immediately `restore()`s the row, overwrites name/password, and detaches roles — before any proof of mailbox ownership. Consequences: (a) an admin's deactivation of a user (incl. a soft-deleted staff/admin account) is reversible by any anonymous party who knows the email; (b) the row's identity (id, audit history, prior profiles linkage) is claimed by whoever registers first, breaking the SAO re-attach path for the legitimate returning student; (c) response differences leak account state (active → 422, soft-deleted → success, unknown → success). Contained by `verified` middleware + role detachment — the attacker gets no privileged session — hence High rather than Critical.

**Proposed solution** — Verify-first reactivation: on trashed-email registration, do *not* restore inline. Create nothing; send the verification email; perform restore + overwrite + role-detach only in the `Verified` event listener (or a signed-URL claim flow). Alternatively (smaller change): keep inline restore but exclude users who ever held staff/admin roles from self-service reactivation (require admin restore via the user-management module). Normalize responses so all three cases are indistinguishable.

**Acceptance criteria**
- [ ] An unverified registration against a trashed email leaves the row trashed (or otherwise unclaimed).
- [ ] Soft-deleted staff/admin accounts cannot be reactivated via public `/register`.
- [ ] Registration responses do not distinguish active/trashed/unknown emails.
- [ ] §13 of `plan/context.md` updated to match the revised policy.

## [AUD-005] Prevent duplicate/concurrent applications per applicant

- **Severity:** High · **Category:** Quality · **Source:** QUAL-4
- **Location:** `app/Http/Controllers/Applications/ApplicationController.php` (store), `app/Http/Requests/Applications/StoreApplicationRequest.php`
- **Status:** Fixed in `1956bf2` (one open application per applicant; re-apply allowed after any terminal decision)

**Problem** — No uniqueness rule, DB constraint, or status check limits applications per user. An applicant (or an already-admitted Student — also unblocked) can submit unlimited applications, including doubles of the same offering. Two pending twins admitted by different SAOs triggers the AUD-003 crash on the second; even sequentially it produces conflicting StudentProfiles/decisions.

**Proposed solution** — Two layers: (1) validation in `StoreApplicationRequest` — reject when the user already has an application in a non-terminal status (or same `(user, offering)` pair non-terminal); (2) optionally a partial-style guard at controller level inside the existing transaction with a `lockForUpdate` on the user's open applications to close the concurrent-submit race. Decide and document whether Students may apply again (e.g. level upgrade) — if yes, scope the rule accordingly.

**Acceptance criteria**
- [ ] Second submission while one is pending → 422 with a clear message.
- [ ] Concurrency test or in-transaction guard documented.
- [ ] Policy for already-admitted Students decided and asserted.

## [AUD-006] Replace count-based matricule generation with a sequence table

- **Severity:** High · **Category:** Quality/Performance · **Source:** QUAL-5, PERF-2
- **Location:** `app/Actions/Sao/DecideApplicationAction.php:91-96`, `app/Models/StudentProfile.php` (`nextMatriculeForYear`)
- **Status:** Fixed in `1956bf2` (`matricule_sequences` counter table, lazy-seeded per year)

**Problem** — Generation = `lockForUpdate()->get()` of *every* profile of the year, then `withTrashed()->count()+1`. Three defects: (1) the first admit of a year locks zero rows — MySQL gap locks taken by two concurrent first-admits are compatible until insert → deadlock/duplicate; (2) any `forceDelete` of a year's profile makes `count+1` collide with an existing matricule **permanently** — every admit for that year 500s until manual repair; (3) O(n) lock set fully serializes admits and grows with enrollment. SQLite tests cannot exercise any of this.

**Proposed solution (structural)** — One-row-per-year `matricule_sequences (year PK, last_number)` table: inside the existing transaction, `INSERT ... ON DUPLICATE KEY UPDATE last_number = LAST_INSERT_ID(last_number + 1)` (or `lockForUpdate` the sequence row + increment). Constant-time, gap-proof, force-delete-proof. Keep `nextMatriculeForYear` as a thin wrapper for format only.

**Acceptance criteria**
- [ ] Sequence survives force-deleted profiles (test: force-delete then admit — no collision).
- [ ] Lock scope is a single sequence row.
- [ ] Existing matricule format (`stm-YYYY-0001`) and year-scoped numbering preserved; migration seeds `last_number` from existing data.

## [AUD-007] Make employee_id settable in the admin staff-create/edit flow (employee-ID login currently can never work)

- **Severity:** High · **Category:** Gap · **Source:** GAP-3
- **Location:** `app/Providers/FortifyServiceProvider.php:63` (only reader); no writer exists anywhere in `app/`
- **Status:** Fixed in `a93f9ba` (optional employee_id on admin create/edit; lowercase-normalized, unique, format guard blocks `@` and `stm-` prefix; persisted via forceFill; end-to-end create+login test)

**Problem** — The login screen advertises "Email, employee ID, or matricule" and the Fortify resolver queries `users.employee_id`, but no Form Request, Vue form, or fillable path ever assigns it — every user's `employee_id` is NULL. The §4.6 FINAL decision ("an employee_id is assigned at creation; both work as logins from day one") is silently void. Tests pass because `UserFactory::staff()` sets it directly.

**Proposed solution** — Add optional `employee_id` (nullable, unique, normalized lowercase to match the canonicalized login path) to `StoreUserRequest`/`UpdateUserRequest` and the admin user Create/Edit Vue forms; persist via `forceFill` in `CreateUserAction` (the column is deliberately outside `$fillable` — keep that). Show it in the users DataTable.

**Acceptance criteria**
- [ ] Admin creates staff with an employee_id → that staff member can log in with it immediately.
- [ ] Uniqueness 422 on duplicates; case-insensitive match with Fortify's `CanonicalizeUsername`.
- [ ] Feature test covering create-with-id + login-by-id end-to-end.

## [AUD-008] Add composite indexes to audit_logs and tame modal pagination cost

- **Severity:** High · **Category:** Performance · **Source:** PERF-1 · **Scale assumption:** ~1M+ rows (every write + every login/logout/failed login appends)
- **Location:** `database/migrations/2026_05_04_120000_create_audit_logs_table.php`; `app/Http/Controllers/Admin/AuditLogController.php`
- **Status:** Fixed in `fa56b44` (composite (occurred_at,id)/(user_id,occurred_at)/(action,occurred_at)/(subject_type,occurred_at) indexes; pagination left as `paginate()` — modal shows total/last_page)

**Problem** — `audit_logs` is the fastest-growing table but carries only single-column indexes. The modal's combined filters + `ORDER BY occurred_at, id` cannot be served by any of them → filesort over the filtered set; `paginate()` additionally re-runs `COUNT(*)` on every page/filter change.

**Proposed solution (quick win)** — Edit the migration in place (local-workflow convention): composite indexes `(occurred_at, id)`, `(user_id, occurred_at)`, `(action, occurred_at)`, `(subject_type, occurred_at)`; drop the now-redundant single-column ones. Optionally switch the endpoint to `simplePaginate()`/cursor pagination — the modal UI only needs prev/next.

**Acceptance criteria**
- [ ] `EXPLAIN` on the filtered+sorted query shows index-backed ordering (no filesort) for each single-filter case.
- [ ] Modal behavior unchanged (existing 9 `AuditLogIndexTest` cases green).

## [AUD-009] Move uploaded-file storage out of the application-submit DB transaction

- **Severity:** High · **Category:** Performance/Quality · **Source:** PERF-3, QUAL-9
- **Location:** `app/Http/Controllers/Applications/ApplicationController.php:146-179` (store)
- **Status:** Fixed in `fa56b44` (files stored before the transaction, deleted in a `catch` on rollback; cleanup covered by test)

**Problem** — `$file->store()` runs per-document *inside* `DB::transaction`, holding the connection and any locks across multi-MB disk I/O (3+ files × 8MB). On rollback (e.g. role-attach failure), already-stored files are orphaned on disk with no cleanup; on storage failure mid-loop, earlier files orphan too.

**Proposed solution (quick win)** — Store all files first, collect paths; run the DB transaction afterwards (create application + document rows + role attach); on transaction failure, delete the stored files in a `catch`/`finally`. Keeps the transaction milliseconds-long and disk state consistent.

**Acceptance criteria**
- [ ] No filesystem I/O inside the transaction closure.
- [ ] Test: forced transaction failure leaves zero files in the applications directory.
- [ ] Happy-path submission test still green.

---

## Medium

## [AUD-010] Enforce canTransitionTo() in Decide/RestorePrior (Draft hole)

- **Severity:** Medium · **Category:** Quality · **Source:** QUAL-3
- **Location:** `app/Actions/Sao/DecideApplicationAction.php:47`, `app/Actions/Sao/RestorePriorEnrollment.php`
- **Status:** Fixed in `1956bf2` (transition matrix: Draft → Submitted only; enforced in decide + restore-prior)

**Problem** — Both actions only check `isTerminal()`, never `canTransitionTo()`, so a `Draft` application can be Admitted/Withdrawn directly. Today no UI creates persistent Drafts (store() goes straight to Submitted), so this is latent — but the endpoints are live and the moment draft-saving ships it becomes an armed bypass of the submission (and document-validation) flow.

**Proposed solution** — Route the status guard in both actions through `canTransitionTo($decision)` (extend the matrix to define Draft → interim only), keeping the friendlier "already finalized" message for the terminal case. Combine with the AUD-001 in-transaction re-check.

**Acceptance criteria**
- [ ] Test: deciding a Draft application → 422.
- [ ] Transition matrix documented on the model.

## [AUD-011] Rate-limit registration, password-reset, and verification-notification endpoints

- **Severity:** Medium · **Category:** Security · **Source:** SEC-2 · **Attacker model:** anonymous
- **Location:** `app/Providers/FortifyServiceProvider.php` (only `login`/`two-factor` limiters exist — confirmed via `route:list -v`)
- **Status:** Fixed in `512a97c` (named limiters: register 5/min/IP; forgot-password + reset-password 3/min per email+IP; verification 3/min per user via `fortify.limiters.verification`)

**Problem** — `POST /register`, `/forgot-password`, `/reset-password`, `/email/verification-notification` have no throttle: enables email enumeration at scale (especially combined with AUD-004's response oracle), reset-token brute force, and mail-bombing arbitrary addresses.

**Proposed solution** — Named limiters in `FortifyServiceProvider::boot()` (e.g. `register`: 5/min/IP; `forgot-password`: 3/min per email+IP; `verification-notification`: 3/min per user) wired via Fortify's limiter config / route middleware.

**Acceptance criteria**
- [ ] 429 after threshold on each endpoint (feature tests).
- [ ] Legitimate single-user flows unaffected.

## [AUD-012] Guard credential-bearing seeders against non-local environments

- **Severity:** Medium · **Category:** Security · **Source:** SEC-3
- **Location:** `database/seeders/DatabaseSeeder.php`, `database/seeders/LocalStaffSeeder.php`
- **Status:** Fixed in `fa56b44` (credential accounts in `DatabaseSeeder` gated to local/testing; `LocalStaffSeeder` was already guarded)

**Problem** — `migrate --seed` unconditionally provisions `admin@example.com` / `password` (Admin role, pre-verified) plus known staff accounts. Run against a deployed database, this mints a known-credential admin.

**Proposed solution** — Wrap credential seeding in `if (app()->environment('local', 'testing'))` (or move to a dedicated `DevSeeder` not called by `DatabaseSeeder`). Reference-data seeders (roles, document types) stay unconditional — they're required in prod.

**Acceptance criteria**
- [ ] Seeding in `production` env creates no user accounts.
- [ ] Local `migrate:fresh --seed` DX unchanged.

## [AUD-013] Handle soft-deleted ProgramOffering on applications (500s on dashboards/review)

- **Severity:** Medium · **Category:** Quality · **Source:** QUAL-6
- **Location:** `app/Http/Controllers/Applications/ApplicationController.php` (dashboard/show), `app/Http/Controllers/Sao/ApplicationReviewController.php` (index/show); `app/Http/Controllers/Admin/References/ProgramOfferingController.php` (destroy guard)
- **Status:** Fixed in `e1255e3` (destroy refuses while applications reference the offering; `Application`/`StudentProfile::programOffering()` resolve withTrashed so drifted data still renders)

**Problem** — `ProgramOfferingController::destroy()` only refuses deletion when `levelCredentialRequirements()->exist()` — it ignores live applications. Soft-deleting an offering referenced by applications makes the default `belongsTo` resolve null, and the serializers deref `programOffering->department` → 500 on the applicant dashboard, application show, SAO queue and review pages.

**Proposed solution** — Two parts: (1) extend the destroy guard to also refuse when `applications()->exists()` (relation may need adding); (2) defense-in-depth: use `->withTrashed()` on the `programOffering` relation reads in the serializers (or null-safe shaping) so historical applications still render if data drifts.

**Acceptance criteria**
- [ ] Destroy with dependent applications → refused with toast, row intact.
- [ ] Test: application whose offering is force-trashed in DB still renders dashboard/show without 500.

## [AUD-014] Protect always-required document types (NID/BIRTH) from deletion

- **Severity:** Medium · **Category:** Quality · **Source:** QUAL-7
- **Location:** `app/Http/Controllers/Admin/References/DocumentTypeController.php` (destroy), `app/Http/Requests/Applications/StoreApplicationRequest.php` (`ALWAYS_REQUIRED_CODES`)
- **Status:** Fixed in `e1255e3` (`DocumentType::PROTECTED_CODES` shared constant; destroy and code-rename refused for NID/BIRTH)

**Problem** — NID/BIRTH are required by hardcoded code-string, not by `level_credential_requirements` rows, so the destroy guard (children-only) lets an admin soft-delete them. Result: the application form stops rendering the slot while server validation still demands it → every new application 422s with no visible slot (and an undefined-array-key 500 path if a file does arrive).

**Proposed solution** — Refuse destroy (and code-renames) for codes in a shared `DocumentType::PROTECTED_CODES = ['NID', 'BIRTH']` constant; have `StoreApplicationRequest::ALWAYS_REQUIRED_CODES` reference the same constant so the two can't drift.

**Acceptance criteria**
- [ ] Deleting/renaming NID or BIRTH → refused with explanatory toast.
- [ ] Single source of truth for the protected codes.

## [AUD-015] Fix DOB off-by-one for UTC+ timezones in the application form

- **Severity:** Medium · **Category:** Quality · **Source:** QUAL-8
- **Location:** `resources/js/pages/applicant/applications/Create.vue` (date transform)
- **Status:** Fixed in `fa56b44` (`toLocalDateString()` formats from local date components; literal-date assertion added to submission test)

**Problem** — The submit transform uses `toISOString()` on the DatePicker's local-midnight Date. For any UTC+ timezone (Cameroon is UTC+1), local midnight converts to the *previous* UTC day — every applicant's date of birth is stored one day early.

**Proposed solution (quick win)** — Format from local components instead: `` `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}` `` (or use the DatePicker's `dateFormat` string output directly).

**Acceptance criteria**
- [ ] A birth date picked as 2000-01-01 in UTC+1 persists as 2000-01-01.
- [ ] Assertion added to the submission feature test (server receives the literal date).

## [AUD-016] Add error feedback to Create.vue cascading fetches

- **Severity:** Medium · **Category:** Quality · **Source:** QUAL-10
- **Location:** `resources/js/pages/applicant/applications/Create.vue` (offerings/level-requirements `fetch()` calls)
- **Status:** Fixed in `fa56b44` (catch + inline error `Message` with retry button on both lookups, mirroring AuditLogModal)

**Problem** — The cascading lookups use `try/finally` with no `catch` and no `response.ok` check: a failed/expired-session fetch leaves the form silently dead (departments never populate, required document slots never render) with zero user feedback. `AuditLogModal.vue` already implements the correct pattern (inline `Message` on failure) — siblings drifted.

**Proposed solution (quick win)** — Mirror the AuditLogModal pattern: check `response.ok`, `catch` network errors, surface an inline PrimeVue `Message`/toast with a retry affordance, and reset the dependent selects' loading state.

**Acceptance criteria**
- [ ] Simulated 500/network failure on either endpoint shows visible feedback and allows retry.
- [ ] No unhandled promise rejections in console.

## [AUD-017] Handle concurrent-registration unique violation as 422

- **Severity:** Medium · **Category:** Quality · **Source:** QUAL-11
- **Location:** `app/Actions/Fortify/CreateNewUser.php:33-76`
- **Status:** Fixed in `512a97c` (UniqueConstraintViolationException re-thrown as the standard email-taken 422; reactivation branch removed entirely by AUD-004)

**Problem** — Validate-then-create: two concurrent registrations with the same email both pass the unique rule; the loser hits the DB unique index → raw 500 instead of the standard 422. Same check-then-act applies to the trashed-row reactivation branch (unlocked read).

**Proposed solution** — Wrap the create in a `try/catch (UniqueConstraintViolationException)` re-thrown as the standard email-taken `ValidationException`; in the reactivation branch, re-fetch the trashed row `lockForUpdate()` inside the transaction. (If AUD-004's verify-first redesign lands, fold this in there.)

**Acceptance criteria**
- [ ] Unique-violation path returns 422 with the standard email message (unit-testable by faking the throw).

## [AUD-018] Stop re-querying roles multiple times per request

- **Severity:** Medium · **Category:** Performance · **Source:** PERF-4 · **Scale assumption:** every authenticated request
- **Location:** `app/Http/Middleware/HandleInertiaRequests.php:45`, `app/Models/Concerns/HasRoles.php`, `app/Services/RoleDashboardResolver.php`, `app/Http/Middleware/EnsureUserHasRole.php`
- **Status:** Fixed in `e1255e3` (in-memory relation reuse + single eager load in the Inertia share and login resolver; one `role_user` query per request asserted by `RoleQueryEfficiencyTest`)

**Problem** — A single authenticated navigation triggers 3–8 separate role queries: the Inertia share lazy-loads `roles`, `EnsureUserHasRole` runs an EXISTS, each Gate check queries again, and `RoleDashboardResolver` loops up to six `hasRole()` queries on login redirect — none reuse the loaded relation.

**Proposed solution (quick win)** — Make `hasRole`/`hasAnyRole` use the in-memory relation when loaded (`$this->relationLoaded('roles')` → collection check, else EXISTS query); add `$user->loadMissing('roles')` once early (Inertia middleware), so all downstream checks are free. Keep the middleware EXISTS as-is or convert similarly.

**Acceptance criteria**
- [ ] One roles query per request on an authenticated page load (assert with `DB::enableQueryLog()` in a test or `expectsDatabaseQueryCount`).
- [ ] All gate/middleware behavior unchanged (suite green).

## [AUD-019] Add composite index for the SAO application queue

- **Severity:** Medium · **Category:** Performance · **Source:** PERF-6 · **Scale assumption:** 10k+ applications
- **Location:** `database/migrations/2026_05_06_120000_create_applications_table.php`; `app/Http/Controllers/Sao/ApplicationReviewController.php` (index)
- **Status:** Fixed in `fa56b44` (composite (status,submitted_at) + (user_id,status); note: multi-status `IN` still filesorts the index-filtered subset — MySQL limitation, single-status is sort-free)

**Problem** — The queue runs `whereIn(status, [...]) ORDER BY submitted_at` against separate single-column indexes → filesort; `created_at`/`level` sort options have no index at all.

**Proposed solution (quick win)** — Edit the migration in place: composite `(status, submitted_at)`; evaluate `(user_id, status)` for the duplicate-guard query from AUD-005 at the same time.

**Acceptance criteria**
- [ ] `EXPLAIN` shows no filesort for the default queue query.

## [AUD-020] Trim global PrimeVue registrations out of the main bundle

- **Severity:** Medium · **Category:** Performance · **Source:** PERF-7
- **Location:** `resources/js/app.ts` (global registrations), `vite.config.ts` (`chunkSizeWarningLimit: 1000`)
- **Status:** Fixed in `e1255e3` (per-page imports everywhere; main chunk 936.29 kB → 451.06 kB, gzip 208.02 → 106.49 kB; default warning limit restored)

**Problem** — DataTable, FileUpload, Dialog, Select etc. are registered globally, landing in the ~913KB main chunk that even the login/welcome pages download. The chunk-size warning was raised to mask it rather than fix it.

**Proposed solution (structural)** — Keep only `Toast` (and anything truly app-shell-level) global; convert pages to per-page imports (most already import half their components per-page — finish the pattern). Restore the default `chunkSizeWarningLimit` as the regression guard.

**Acceptance criteria**
- [ ] Login page JS payload drops measurably (record before/after from `npm run build` output).
- [ ] No "component not registered" runtime errors across all pages (smoke-check each page).

## [AUD-021] Surface trashed reference rows with a restore action in admin CRUD

- **Severity:** Medium · **Category:** Gap · **Source:** GAP-4 (promised in Phase 4 decision record, silently dropped from Phase 10)
- **Location:** `app/Http/Controllers/Admin/References/*`, `resources/js/pages/admin/references/*`
- **Status:** Fixed in `a93f9ba` (show-deleted toggle + restore route/action on all four reference CRUDs; parent-trashed restores refused; key re-take impossible — unique indexes span trashed rows, so the conflict-422 criterion is satisfied at the DB layer)

**Problem** — Soft-deleting a reference row blocks recreating the same key (422 by design since Phase 4) AND there is no restore UI — an admin who deletes a department/offering/doc-type can only recover via tinker. Operational dead-end.

**Proposed solution** — Add a "show deleted" toggle to each reference DataTable (`withTrashed()` query param on index), a Restore row action hitting a new `restore` route per resource (`role:admin`), with audit via the existing `RecordsAudit` restored hook.

**Acceptance criteria**
- [ ] Delete → toggle deleted → restore → row usable again; audit `Restored` row written.
- [ ] Restore of a row whose unique key was since re-taken → clean 422.

## [AUD-022] Opt User into RecordsAudit (settings changes are unaudited)

- **Severity:** Medium · **Category:** Security/Gap · **Source:** SEC-5, GAP-5
- **Location:** `app/Models/User.php`
- **Status:** Fixed in `a93f9ba` (User uses RecordsAudit; new `auditRedact()` masks password/2FA values in diffs; CreateUserAction's manual Created row removed — single save, single row; reactivation `restoreQuietly()` keeps one contextual Restored row)

**Problem** — `User` is the only domain model without `RecordsAudit`. Email changes (which also null `email_verified_at` — a takeover precursor), name changes, password changes, and self-deletion leave no audit trail, in a system whose audit log is a headline feature.

**Proposed solution** — Add `use RecordsAudit;` to `User` (the trait's `auditExclude()` already strips password/remember_token/2FA columns — verify). Check interplay with the reactivation flow's manual `Restored` row (two rows with different purposes is the documented pattern — keep, but confirm no duplicate noise) and with admin CreateUserAction's manual `Created` row (suppress one or accept both deliberately).

**Acceptance criteria**
- [ ] Email/name/password changes write `Updated` rows with sensitive keys excluded.
- [ ] No double-logging surprises in registration/admin-create tests (assert exact row counts).

## [AUD-023] Guard ProgramOffering level-range narrowing against orphaning child requirements and live applications

- **Severity:** Medium · **Category:** Gap · **Source:** GAP-6 (stale "TODO (Phase 7)")
- **Location:** `app/Http/Requests/Admin/References/ProgramOfferingUpdateRequest.php`
- **Status:** Fixed in `e1255e3` (after-hook fails narrowing that orphans requirement levels or open-application levels, naming the blockers; widening unrestricted)

**Problem** — Narrowing `min_level`/`max_level` is unvalidated against existing `level_credential_requirements` rows (they orphan silently, and the rules engine stops matching them) and against live applications at now-out-of-range levels. The TODO deferred to Phase 7 was never picked up; Phase 7's re-check only covers *new* submissions.

**Proposed solution** — In the Update request, fail validation when the new range would exclude any existing requirement's level or any non-terminal application's level for that offering; the error message lists the blocking levels.

**Acceptance criteria**
- [ ] Narrow-with-orphans → 422 naming the conflicting levels; widen always allowed.
- [ ] Tests for both child types (requirements + applications).

## [AUD-024] Replace placeholder dashboards and starter-kit navigation remnants

- **Severity:** Medium · **Category:** Gap · **Source:** GAP-7
- **Location:** `resources/js/pages/dashboards/{Student,Lecturer,Accountant}.vue`, sidebar footer links
- **Status:** Fixed in `a93f9ba` (Student: enrollment summary + application history via new controller; Lecturer/Accountant: profile cards + coming-soon states; starter-kit links and branding removed from sidebar/header/logo)

**Problem** — Student/Lecturer/Accountant dashboards literally render placeholder text; a freshly admitted student logs in (the system's happy-path climax) to a dead end with no link to their application history. The sidebar footer still links to the `laravel/vue-starter-kit` GitHub/docs.

**Proposed solution** — Minimum viable: Student dashboard shows profile summary (matricule, programme, level, status) + application history table; Lecturer/Accountant get an honest "module coming soon" with their profile card; remove/replace starter-kit footer links. Full dashboards arrive with their domains (payments, courses).

**Acceptance criteria**
- [ ] Student sees matricule + programme + applications after admission (feature test on the dashboard props).
- [ ] No starter-kit external links remain in app navigation.

## [AUD-025] Collapse the three-query login resolver and equalize timing

- **Severity:** Medium · **Category:** Performance/Security · **Source:** PERF-5, SEC-6 · **Attacker model:** anonymous (timing oracle)
- **Location:** `app/Providers/FortifyServiceProvider.php:58-73`
- **Status:** Fixed in `512a97c` (single OR query incl. matricule EXISTS; dummy bcrypt check on the not-found path; query count asserted in test)

**Problem** — Identifier resolution runs up to 3 sequential queries (email → employee_id → matricule); failed logins (the attacker-controlled path) always pay all three plus the audit INSERT. `Hash::check` only runs when a user is found → response-time distinguishes valid from invalid identifiers (enumeration oracle across all three identifier namespaces).

**Proposed solution** — Single query with OR conditions across the three identifier columns (left-join or `whereExists` on `student_profiles.matricule`; all indexed — verify the OR doesn't defeat index use, otherwise `UNION` the three indexed lookups). On miss, run `Hash::check($password, $dummyBcryptHash)` before returning null to flatten timing.

**Acceptance criteria**
- [ ] One resolver query per login attempt (assert query count in test).
- [ ] Dummy hash check on the not-found path.
- [ ] All three identifier login tests still green.

---

## Low

## [AUD-026] Add throttling to api/v1 lookups, document download, and audit-log endpoints

- **Severity:** Low · **Category:** Security · **Source:** SEC-4
- **Location:** `routes/web.php` (api/v1 group), `routes/sao.php`/document download route, `routes/admin.php` (audit-logs)
- **Status:** Open

**Problem** — Authenticated-but-unthrottled endpoints allow load amplification (the audit-log endpoint especially, given AUD-008). Authorization is correct on all of them.

**Proposed solution** — `throttle:60,1`-style middleware on the api/v1 group and download route; a tighter named limiter on the audit-log endpoint.

**Acceptance criteria** — 429 past threshold; normal UI flows unaffected.

## [AUD-027] Consolidate duplicated label/severity maps and validation closures

- **Severity:** Low · **Category:** Quality · **Source:** QUAL-12/13/14, GAP-9, PERF-Low
- **Location:** status/severity maps in `dashboards/Applicant.vue`, `sao/applications/Index.vue`, `Review.vue`, `AuditLogModal.vue`, admin users pages (6 files); status whitelists hand-written in `TriageApplicationRequest`/`DecideApplicationRequest` vs `Application` model constants (private); `levelWithinOfferingRange()` triplicated across `LevelCredentialRequirement{Store,Update}Request` and `StoreApplicationRequest`; `roleLabel()` in `UserInvitationMail` (memory note says lift to `RoleName`)
- **Status:** Fixed in `e1255e3` (`resources/js/lib/statusDisplay.ts` single definition site for status/enrollment/degree/role labels+severities — also fixed Student.vue's stale `excluded` key vs the real `withdrawn`; `Application` status constants public and driving both Form Requests + SAO default filter; shared `LevelWithinOfferingRange` rule; `RoleName::label()`. AuditLogModal's action map intentionally stays: its labels come from server options, severity is heuristic on a different enum)

**Problem** — Each copy has already begun to drift (the audit modal's action map vs page maps). Next enum case added = 6 frontend edits + 3 backend edits or silent inconsistency.

**Proposed solution** — Frontend: one `resources/js/lib/statusDisplay.ts` (status/degree/role → label + severity). Backend: make `Application::TERMINAL_STATUSES`/`INTERIM_STATUSES` public and reference them from the Form Requests; extract `levelWithinOfferingRange` to a shared invokable rule class; add `RoleName::label()`.

**Acceptance criteria** — single definition site each; grep shows no残 duplicates; suite + types/lint green.

## [AUD-028] Record RoleRevoked audit rows when reactivation detaches roles

- **Severity:** Low · **Category:** Gap/Security · **Source:** GAP-11
- **Location:** `app/Actions/Fortify/CreateNewUser.php:59`
- **Status:** Fixed in `512a97c` (one RoleRevoked row per detached role with context reactivated=true, written in `ResetUserPassword::reactivate()`)

**Problem** — `roles()->detach()` during reactivation writes no `RoleRevoked` audit rows — the one place roles vanish without trace, in the flow where forensics matter most (see AUD-004).

**Proposed solution** — Capture the role list before detaching; write one `RoleRevoked` row (or one per role) with `context: ['reactivated' => true]`. Fold into the AUD-004 rework.

**Acceptance criteria** — reactivation test asserts the revocation rows.

## [AUD-029] Decide: implement Pest browser tests or strike them from the plan

- **Severity:** Low · **Category:** Gap · **Source:** GAP-10
- **Location:** plan/context.md Phases 2, 8, 10 test sections; no `tests/Browser` exists
- **Status:** Open

**Problem** — Browser tests are promised in three phases and were each time waved through ("satisfied by HTTP-level coverage"). Either commitment is real (set up Playwright + Pest 4 `visit()` smoke suite) or the doc should stop promising it.

**Proposed solution** — Recommend implementing a minimal smoke suite (login, applicant form happy path, admin modal) — the cascading form and modal are exactly the JS-heavy surfaces HTTP tests can't cover (AUD-015/016 would have been caught). Otherwise amend the doc.

**Acceptance criteria** — either `tests/Browser` with 3+ green smoke tests in CI, or context.md updated.

## [AUD-030] Pin the document download disk to the disk used at store time

- **Severity:** Low · **Category:** Quality · **Source:** QUAL-Low
- **Location:** `app/Http/Controllers/Applications/DocumentDownloadController.php` (`Storage::disk('local')`) vs `ApplicationController::store` (default disk)
- **Status:** Fixed in `fa56b44` (download uses the default disk via `Storage::download()`, matching the store path)

**Problem** — Upload uses the default disk; download hardcodes `local`. They coincide today; changing `FILESYSTEM_DISK` (e.g. to s3 at deployment — the plan explicitly says "revisit at deployment") silently 404s every historical download.

**Proposed solution** — Use the same `Storage::disk(config('filesystems.default'))` (or store the disk name on `application_documents`) in both paths.

**Acceptance criteria** — single source for the disk choice; download test still green.

## [AUD-031] Shape Inertia props instead of serializing whole models

- **Severity:** Low · **Category:** Quality/Security · **Source:** QUAL-Low
- **Location:** SAO review / admin users pages passing raw `User`/profile models
- **Status:** Fixed in `e1255e3` (shared `auth.user` prop and admin Edit staff profiles shaped to the fields the pages consume; SAO review was already shaped by an earlier phase)

**Problem** — Full models are serialized where pages need a handful of fields. `#[Hidden]`/`$hidden` covers the secrets today, so no leak — but every future column added to these models ships to the browser by default.

**Proposed solution** — Shape arrays (or API Resources) for props on the affected pages, matching the pattern `dashboard()`/`show()` already use.

**Acceptance criteria** — affected pages' props contain only consumed fields (assert prop shape in existing feature tests).

## [AUD-032] Bound the applicant dashboard query and define an audit-log retention path

- **Severity:** Low · **Category:** Performance · **Source:** PERF-Low
- **Location:** `ApplicationController::dashboard` (`->get()` unbounded); `app/Models/AuditLog.php` (mutation guard blocks Eloquent pruning)
- **Status:** Open

**Problem** — A single applicant's list realistically stays small (low severity), but it's the only unbounded `->get()` on a growing table. Separately, the audit log has no retention strategy and the model's `deleting` guard means even an intentional archival job can't use Eloquent.

**Proposed solution** — Paginate or cap the dashboard list. For audit logs: document the intended retention (e.g. yearly DB-level archival via `DB::table('audit_logs')` bypassing the model guard, by design) — decision needed, not necessarily code.

**Acceptance criteria** — dashboard paginated; retention decision recorded in plan/context.md.

## [AUD-033] Refresh stale project documentation (context.md §14, MEMORY index, CLAUDE.md api.php reference)

- **Severity:** Low · **Category:** Gap/Docs · **Source:** GAP-8, GAP-12
- **Location:** `plan/context.md` (§14 status table says Phase 10 pending; route count 38 vs actual 47; admin user-management module absent), project `CLAUDE.md` (references nonexistent `routes/api.php` convention), memory index
- **Status:** Fixed in `ea3b426` (§14 status table completed with Phase 10 + UM-A/B/C rows, supersession note pointing at §15, UM module summary, route count 54; CLAUDE.md Database section rewritten to the real route layout; memory index refreshed)

**Problem** — The "source of truth" doc no longer reflects three shipped commits (`ac997ac`, `e99fc2e`, `f46c02c`) and misstates phase status; CLAUDE.md tells future sessions to follow a convention in a file that doesn't exist.

**Proposed solution** — Update §14 with the user-management phases + current route count; fix the CLAUDE.md database section to describe the actual route layout (web.php + settings/admin/sao.php + api/v1 sub-group).

**Acceptance criteria** — docs match `git log` and `route:list` output at time of edit.

## [AUD-034] Document a production hardening baseline

- **Severity:** Low · **Category:** Security · **Source:** SEC-8
- **Location:** `.env.example` (`APP_DEBUG=true`, no `SESSION_SECURE_COOKIE`), deployment docs (none)
- **Status:** Open

**Problem** — No production checklist exists; the example env defaults are dev-oriented. With debug on in prod, exceptions (e.g. AUD-003's QueryException) leak schema/paths.

**Proposed solution** — Add a deployment section to the docs: `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, HTTPS-only, queue worker required (AUD-002/invite mail are queued), `FILESYSTEM_DISK` decision (AUD-030), seeder policy (AUD-012).

**Acceptance criteria** — checklist exists and cross-references the related findings.

---

## Tracked backlog (consciously deferred — not defects)

Carried from the phase docs and confirmed still-pending; listed for completeness so GitHub milestones can pick them up. Items already promoted to findings above are excluded.

| # | Item | Source |
|---|---|---|
| B1 | Payment validation + tamper-proof school receipts (signed QR, verification endpoint) | context.md §10 |
| B2 | Tuition deferral request/approval flow + access gating | context.md §10 |
| B3 | Course management (planning, attendance, assignments, results, disputes) | context.md §10 |
| B4 | Lecturer absence notifications | context.md §10 |
| B5 | Public/staff receipt verification lookup | context.md §10 |
| B6 | Notification channel strategy (email vs in-app vs SMS) — AUD-002 implements the minimum mail slice | context.md §10 |
| B7 | Multi-role role-switcher UI | context.md §10 |
| B8 | shadcn-vue → PrimeVue migration of auth/settings pages (only when otherwise modified) | context.md §10 |
| B9 | Phone-as-secondary-identifier matching | context.md §13.5 |
| B10 | Per-user audit-log drill-down on the user record | admin module memory |
| B11 | Bulk staff import (CSV) | admin module memory |
| B12 | Profile-only settings edit for staff (e.g. SAO scope) | admin module memory |
| B13 | Per-role sidebar/navigation polish beyond AUD-024's minimum | Phase 8/10 deferrals |
| B14 | Real browser walkthrough of the invite flow (Mailtrap click-through) | admin module memory |
| B15 | Reference-data caching (blocked on non-database cache store; invalidation = the 4 reference controllers) | PERF report |

---

## Suggested fix order

1. **Correctness of the core flow (one PR each):** AUD-001 + AUD-010 (same files), AUD-003, AUD-005, AUD-006 — these four interact; land them in this order.
2. **Quick wins, low risk:** AUD-009, AUD-015, AUD-016, AUD-019, AUD-008, AUD-030, AUD-012.
3. **Auth hardening slice:** AUD-004 + AUD-017 + AUD-028 (same file), AUD-011, AUD-025.
4. **Feature gaps:** AUD-002 (highest user value), AUD-007, AUD-021, AUD-024, AUD-022.
5. **Structural cleanups:** AUD-018, AUD-020, AUD-027, AUD-031, AUD-013, AUD-014, AUD-023.
6. **Docs & process:** AUD-033, AUD-034, AUD-029, AUD-026, AUD-032.
