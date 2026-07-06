# Retire the Ability Gates — Implementation Plan (#83)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the 8-gate ability abstraction (only 3 ever invoked, all redundant with route middleware) so authorization is a single honest model — `role:*` route middleware + per-resource ownership checks — with the suite staying green.

**Architecture:** Behavior-preserving deletion. Each gate's role set equals the roles of the route group it sits behind, so removing the gate changes no authorization outcome; the `authorizeOwnership()` check that actually distinguishes `mark-attendance` is retained. ADR-0025 supersedes ADR-0022; the docs that described gates as an enforcement layer are corrected.

**Tech Stack:** Laravel 13 / PHP 8.4, Pest v4. No frontend change.

## Global Constraints

- **Behavior-preserving.** No request that was denied becomes allowed; no route middleware, ownership check, or role changes. The suite (minus the two deleted gate-definition tests) must stay green — that is the safety net.
- **Keep `authorizeOwnership()`** in `CourseSessionController::markAttendance` — only the redundant `Gate::authorize('mark-attendance')` is removed.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes. Tests: full runs `--testsuite=Unit,Feature` only.
- Commit per task on branch `chore/retire-ability-gates`, message ending with:
  `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`

---

### Task 1: Remove the gate definitions, call sites, and gate tests

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Http/Controllers/Sao/CourseController.php`
- Modify: `app/Http/Controllers/Lecturer/CourseSessionController.php`
- Delete: `tests/Feature/Auth/AbilityGatesTest.php`
- Delete: `tests/Feature/Audit/ViewAuditLogGateTest.php`

**Interfaces:**
- Produces: no `Gate::define`/`Gate::authorize` anywhere in `app/`; authorization is route middleware + ownership only.

- [ ] **Step 1: Strip the gate wiring from `AppServiceProvider`**

`app/Providers/AppServiceProvider.php`:
- Delete the `ABILITIES` const **and its PHPDoc** (the `/** Map of ability name => roles… */` block + `protected const ABILITIES = [ … ];`).
- Delete the `configureGates()` method **and its PHPDoc** entirely.
- In `boot()`, remove the `$this->configureGates();` line so it reads:

```php
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthProviders();
        $this->configureAuditListeners();
    }
```

- Remove the now-unused imports `use App\Enums\RoleName;` and `use Illuminate\Support\Facades\Gate;`. **Keep `use App\Models\User;`** — `configureAuditListeners()` still uses it.

- [ ] **Step 2: Remove the `Gate::authorize` calls in `Sao\CourseController`**

`app/Http/Controllers/Sao/CourseController.php` — delete the `Gate::authorize(...)` line (and the blank line after it) from all three methods. Result:

```php
    public function approve(Request $request, Course $course): RedirectResponse
    {
        $this->reviewCoursePlan->approve($course, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Course plan approved.')]);

        return back();
    }

    public function reject(RejectCoursePlanRequest $request, Course $course): RedirectResponse
    {
        $this->reviewCoursePlan->reject($course, $request->user(), $request->string('notes')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Course plan rejected.')]);

        return back();
    }

    public function publishResults(Request $request, Course $course, PublishCourseResults $action): RedirectResponse
    {
        $count = $action->publish($course, $request->user());
```

Remove the now-unused import `use Illuminate\Support\Facades\Gate;` (line 19).

- [ ] **Step 3: Remove the `Gate::authorize` call in `Lecturer\CourseSessionController` (keep ownership)**

`app/Http/Controllers/Lecturer/CourseSessionController.php` — delete only the `Gate::authorize('mark-attendance');` line (and the blank line after it), keeping `authorizeOwnership()`. Result:

```php
    public function markAttendance(MarkAttendanceRequest $request, Course $course, CourseSession $session, MarkAttendance $action): RedirectResponse
    {
        $this->authorizeOwnership($request, $course);

        $action->mark($session, $request->statuses(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attendance saved.')]);

        return back();
    }
```

Remove the now-unused import `use Illuminate\Support\Facades\Gate;` (line 23).

- [ ] **Step 4: Delete the two gate-definition tests**

```bash
git rm tests/Feature/Auth/AbilityGatesTest.php tests/Feature/Audit/ViewAuditLogGateTest.php
```

Both assert only the gate definitions (`Gate::forUser()->allows()` / `->can('view-audit-log')`), which no longer exist. **No replacement is needed:** the audit-log endpoint's authorization is already covered by the real enforcement — `tests/Feature/Admin/AdminAuthorizationTest.php` asserts every non-admin role (and guests/roleless) gets `403`/redirect on `GET /admin/audit-logs`, and `tests/Feature/Admin/AuditLogIndexTest.php` covers the admin-`200` path. (This resolves the spec's §4.2 "repurpose vs delete" caveat in favor of delete, since repurposing would duplicate `AdminAuthorizationTest`.)

- [ ] **Step 5: Verify no gate reference survives**

```bash
grep -rn "Gate::\|configureGates\|ABILITIES\|AbilityGates\|ViewAuditLogGate\|->can('view-audit-log')\|allows('" app/ tests/
```

Expected: no matches (every prior `Gate::` was one of the removed call sites/definitions).

- [ ] **Step 6: Format and run the full suite**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact --testsuite=Unit,Feature
```

Expected: pint clean; suite green. Every authorization test that asserts a `403` on the formerly-gated controllers still gets that `403` from route middleware — so no outcome changes. If a test fails, do **not** re-add a gate; report BLOCKED if it reveals a real behavior change.

- [ ] **Step 7: Frontend gate (completeness — expect no diff)**

```bash
npm run build && npm run types:check && npm run lint:check
```

Expected: green, no chunk-size warning (no frontend change was made).

- [ ] **Step 8: Commit**

```bash
git add app/Providers/AppServiceProvider.php app/Http/Controllers/Sao/CourseController.php app/Http/Controllers/Lecturer/CourseSessionController.php
git rm tests/Feature/Auth/AbilityGatesTest.php tests/Feature/Audit/ViewAuditLogGateTest.php
git commit -m "refactor(auth): retire the redundant ability gates (#83)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 2: ADR-0025, docs sweep, PR

**Files:**
- Create: `docs/adr/0025-retire-ability-gates.md`
- Modify: `docs/adr/0022-authorization-enforcement-model.md` (Status → Superseded)
- Modify: `docs/adr/README.md` (0025 row + 0022 status)
- Modify: `docs/security.md` (§2.1 repurpose, §3.4 fix)
- Modify: `docs/architecture.md` (remove the gate table + pattern mention)
- Modify: `docs/index.md` (drop "ability gates" from descriptions; ADR count 24 → 25)
- Modify: `docs/modules/{course-management,admin-user-management,payments,admissions,exam-gating,notifications}.md`
- Modify (if it credits the gate): `docs/adr/0012-immutable-audit-log.md`
- Modify: `plan/context.md` (new § entry)

- [ ] **Step 1: Write ADR-0025** `docs/adr/0025-retire-ability-gates.md` (Status **Accepted**, **supersedes ADR-0022**):
  - **Context:** `AppServiceProvider::ABILITIES` declared 8 role-based gates; only 3 were invoked; ADR-0022 recorded that all merely restate their route-group middleware (every gate's role set equals the group's) and named the wire-or-retire choice as a backlog item (#83).
  - **Decision:** retire all 8 — remove the `ABILITIES` map, `configureGates()`, and the 3 `Gate::authorize` call sites. Authorization is `role:*` route middleware (coarse) + per-resource ownership checks (fine), the model ADR-0022 already found to be the real one.
  - **Consequences:** no behavior change (denied requests were denied by middleware already); the audit endpoint and every formerly-"gated" surface are enforced by their route group; `authorizeOwnership()` (the genuine per-resource guard) is untouched; the gate registry no longer misleads a reader into thinking a second layer exists. **Rejected alternative:** wire the 5 — redundant defence-in-depth that says nothing the middleware doesn't, and `manage-references` alone would mean ~16 calls across the reference controllers.
  - Then edit **ADR-0022** Status to `Superseded by ADR-0025`, and add the ADR-0025 row (+ flip 0022's status cell) in `docs/adr/README.md`.

- [ ] **Step 2: Run the docs-refresh skill** scoped to this change, verifying every claim against the shipped code. Correct each gate reference to name the real enforcer:
  - `docs/security.md` — repurpose §2.1 ("Ability gates") into an authorization-model note (role middleware + ownership; gates removed in ADR-0025), keeping the `### 2.1` heading/anchor; fix §3.4 (`view-audit-log` gate → `role:admin` group middleware).
  - `docs/architecture.md` — remove the 8-row gate table and the "ability gates" mention in the patterns prose.
  - `docs/index.md` — drop "ability gates" from the Architecture and Security row descriptions; ADR count 24 → 25.
  - `docs/modules/course-management.md` — the roles/abilities table (approve/reject/publish → `role:sao,admin`; mark-attendance → `role:lecturer` + ownership), the two mermaid sequence diagrams (drop `Gate::authorize('mark-attendance')`, the "Gate publish-results" note), the §5.4 "publish-results gate" prose, and the `MarkAttendanceTest`/`PublishCourseResultsTest` test-table cells that say "gate."
  - `docs/modules/admin-user-management.md` — every `view-audit-log` gate reference (roles table, §2 prose, the `admin/audit-logs` route row, audit-log read prose) → `role:admin` middleware.
  - `docs/modules/payments.md` — replace the "`validate-payment` gate defined but not invoked; treat as documented intent" note with "removed; the `role:accountant,admin` group enforces."
  - `docs/modules/admissions.md` — the §2 note that `process-admission`/`decide-application` are "defined but not invoked" → "removed; the `role:sao,admin` group enforces."
  - `docs/modules/exam-gating.md` / `docs/modules/notifications.md` — adjust the "no dedicated ability gate" mentions so they don't imply a gate layer still exists.
  - `docs/adr/0012-immutable-audit-log.md` — if it credits the `view-audit-log` gate for the audit endpoint, correct it to `role:admin` middleware.

- [ ] **Step 3: Append the `plan/context.md` § entry** (next number after §29): issue #83, branch, the retirement + ADR-0025-supersedes-0022, the "already-covered by AdminAuthorizationTest" note, gate result, docs touched, and the rejected wire alternative.

- [ ] **Step 4: Commit docs, push, open the PR**

```bash
git add -A
git commit -m "docs: ADR-0025 (retire gates, supersedes 0022) + security/architecture/module sync (#83)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
git push -u origin chore/retire-ability-gates
gh pr create --base main --title "refactor(auth): retire the redundant ability gates (#83)" --body "Closes #83. <summary + gate results>

🤖 Generated with [Claude Code](https://claude.com/claude-code)"
```

Expected: PR opens; wait for all 4 CI checks (ci 8.4 / ci 8.5 / quality / browser) before merging (squash + delete branch), then fast-forward local `main`.

---

## Self-review notes (already applied)

- **Spec coverage:** every §3 code removal → a Task 1 step; §4 tests → Task 1 Steps 4–5; §6 docs/ADR → Task 2. The behavior-preserving safety net is the full-suite run (Task 1 Step 6).
- **Deviation from spec §4.2, resolved:** the spec defaulted to *repurposing* `ViewAuditLogGateTest` into an endpoint test but flagged "delete if existing coverage exists." Investigation confirms `AdminAuthorizationTest` already asserts `403` for every non-admin on `GET /admin/audit-logs` and `AuditLogIndexTest` the admin `200` — so the plan **deletes** both gate tests and creates no new file, avoiding duplication.
- **No behavior change:** the removed gates' roles equalled their route middleware; the only per-resource distinction (`mark-attendance` ownership) is explicitly retained. Task 1 Step 6 proves the suite green with no re-added gate.
- **`use App\Models\User;` retained** in `AppServiceProvider` (still used by the audit-event listeners) while `RoleName`/`Gate` imports are dropped.
