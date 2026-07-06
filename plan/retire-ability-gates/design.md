# Design — Retire the ability gates; role middleware + ownership is the model (#83)

- **Issue:** #83 — Wire the 5 declared-but-never-invoked ability gates (or retire them) + fix security.md wording (ADR-0022 follow-up)
- **Date:** 2026-07-05
- **Size:** S (code) + M (docs — the gates are documented as an enforcement layer in several places)
- **Status:** Approved (owner chose *retire all 8*), pending implementation plan

## 1. Problem

`AppServiceProvider::configureGates()` defines 8 role-based ability gates from an `ABILITIES` map.
Only 3 are ever invoked (`approve-course-plan`, `publish-results` in `Sao\CourseController`;
`mark-attendance` in `Lecturer\CourseSessionController`). The other 5 (`process-admission`,
`decide-application`, `validate-payment`, `manage-references`, `view-audit-log`) are declared and
exhaustively tested but **never called** — the ADR-0022 finding whose promised backlog follow-up was
never filed. Crucially, **every gate's role set is identical to the role set of the route group it
would guard** (e.g. `process-admission` = [SAO, Admin] behind a `role:sao,admin` route), so a gate can
never decide anything differently from the route middleware already in front of it. The gates are a
redundant restatement, not an independent layer — even the wired `mark-attendance` gate is half-baked
(its ability allows [Lecturer, **Admin**] but the route is `role:lecturer`-only, so the Admin
allowance is dead). There is **no live exposure**; the real authorization is `role:*` route middleware
+ per-resource ownership checks. `docs/security.md` §2.1/§3.4 additionally overstate the gates as
enforcing.

The owner chose **retire all 8** (over wiring them or the issue's retire-5-keep-3): the gates only
duplicate route middleware, so the honest move is to remove the whole abstraction rather than add
redundant checks or leave an arbitrary 3-kept remnant.

## 2. Goals / non-goals

**Goals**
- Remove the ability-gate abstraction entirely; document `role:*` middleware + per-resource ownership
  as the single authorization model.
- Preserve all behavior (no request that was denied becomes allowed; ownership checks untouched) and
  keep the suite green.
- Resolve ADR-0022 via a superseding ADR-0025 and correct the drifted docs.

**Non-goals (out of scope)**
- No change to route middleware, ownership checks, or anyone's roles.
- Not adding any new authorization; not wiring gates (the rejected alternative).
- No change to `EnsureUserHasRole`, `hasAnyRole`, or the `RoleName` enum.

## 3. Code removal

### 3.1 `app/Providers/AppServiceProvider.php`
- Delete the `ABILITIES` const (and its PHPDoc).
- Delete the `configureGates()` method and its call in `boot()` (`$this->configureGates();`).
- Drop the now-unused imports `use Illuminate\Support\Facades\Gate;` and `use App\Enums\RoleName;`.
  **Keep `use App\Models\User;`** — the `configureAuditListeners()` login/logout/failed handlers still
  reference `User`.

### 3.2 `app/Http/Controllers/Sao/CourseController.php`
- Remove the three calls: `Gate::authorize('approve-course-plan')` in `approve()` and `reject()`, and
  `Gate::authorize('publish-results')` in `publishResults()`. Drop the unused `use ...Gate;` import.
- Authorization for these actions is now solely the `role:sao,admin` route group (unchanged effect —
  the gate's roles equalled the group's).

### 3.3 `app/Http/Controllers/Lecturer/CourseSessionController.php`
- Remove `Gate::authorize('mark-attendance')` in `markAttendance()`. **Keep the `authorizeOwnership()`
  call** — that is the real per-resource guard (a lecturer marks only their own course's attendance).
  Drop the unused `use ...Gate;` import.
- Authorization is now `role:lecturer` route middleware + the ownership check (unchanged effect).

## 4. Tests

### 4.1 Delete `tests/Feature/Auth/AbilityGatesTest.php`
It asserts only the gate definitions (the role × ability matrix), which no longer exist.

### 4.2 Repurpose `tests/Feature/Audit/ViewAuditLogGateTest.php` → an endpoint authorization test
Its `->can('view-audit-log')` assertions would break (an undefined ability denies everyone). Replace
them with an **endpoint** test on the real enforcement — `GET admin.audit-logs.index`: an **Admin**
gets `200`, a **non-admin** role (e.g. SAO) gets `403` (from the `role:admin` group middleware).
Rename the file to reflect its new purpose (e.g. `tests/Feature/Audit/AuditLogAccessTest.php`).
*If the implementer finds an existing test already asserting that endpoint's role gating, delete
`ViewAuditLogGateTest` instead of duplicating.*

### 4.3 Full suite stays green
Every authorization test asserting a `403` on the (formerly) gated controllers already gets that `403`
from route middleware, not the gate — so removing the gates changes no test outcome. Run
`php artisan test --compact --testsuite=Unit,Feature` and fix any incidental breakage without
re-introducing a gate.

## 5. Behavior

Fully behavior-preserving. Each gate's role set equals its route group's, so no denied request becomes
allowed; the `mark-attendance` gate's dead `Admin` allowance simply disappears (the route already
blocked non-lecturers). No data or config migration.

## 6. Docs + ADR

The gates are documented as an enforcement layer in several places; each reference must be corrected to
name the real enforcer (`role:*` middleware, and where relevant the kept ownership check).

- **`docs/adr/0025-retire-ability-gates.md`** (new, Accepted) — context: 8 gates declared, 5 never
  invoked, all redundant with route middleware (per ADR-0022); decision: remove the ability-gate
  abstraction; authorization is `role:*` middleware + per-resource ownership; consequences: the
  `AppServiceProvider::ABILITIES` registry and the 3 `Gate::authorize` call sites are gone, the audit
  endpoint (and every other formerly-"gated" surface) is enforced by its route group, ownership checks
  unchanged; rejected alternative: wire the 5 (redundant defence-in-depth, esp. `manage-references`
  across the reference controllers). **Supersedes ADR-0022.**
- **`docs/adr/0022-authorization-enforcement-model.md`** — set Status to **Superseded by ADR-0025**
  (status-line edit only; do not rewrite the decision).
- **`docs/adr/README.md`** — add the 0025 row; flip 0022's status cell.
- **`docs/security.md`** — repurpose §2.1 ("Ability gates") into the authorization-model summary
  (role middleware + ownership; note the gates were removed in ADR-0025), keeping the `### 2.1`
  heading/anchor to avoid cross-reference churn; fix §3.4 (`view-audit-log` gate →
  `role:admin` group middleware).
- **`docs/architecture.md`** — remove the ability-gate table (lines listing the 8 gates) and the
  "ability gates" mention in the core-patterns prose.
- **`docs/index.md`** — drop "ability gates" from the Architecture and Security row descriptions;
  ADR count 24 → 25.
- **Module docs** — correct each gate reference:
  - `docs/modules/course-management.md` — the roles/abilities table (the 3 wired gates →
    `role:sao,admin` / `role:lecturer` + ownership), the two mermaid sequence diagrams
    (`authorizeOwnership + Gate::authorize('mark-attendance')` → `authorizeOwnership` only; the
    publish-results "Gate" note), the §5.4 "publish-results gate" prose, and the `MarkAttendanceTest` /
    `PublishCourseResultsTest` test-table cells that say "gate."
  - `docs/modules/admin-user-management.md` — every `view-audit-log` gate reference (the roles table,
    the §2 prose, the `admin/audit-logs` route row, and the audit-log read prose) → `role:admin`
    middleware.
  - `docs/modules/payments.md` — replace the "`validate-payment` gate defined but not invoked; treat
    as documented intent" note with "removed; the `role:accountant,admin` group enforces."
  - `docs/modules/admissions.md` — the §2 note about `process-admission`/`decide-application` being
    "defined but not invoked" → "removed; the `role:sao,admin` group enforces."
  - `docs/modules/exam-gating.md` / `docs/modules/notifications.md` — adjust the passing "no dedicated
    ability gate" mentions so they don't imply a gate layer still exists.
  - `docs/adr/0012-immutable-audit-log.md` — if it credits the `view-audit-log` gate for the audit
    endpoint, correct it to the `role:admin` middleware.

Run the docs-refresh skill to drive this sweep and verify every claim against the shipped code.

## 7. Testing / gate

- `php artisan test --compact --testsuite=Unit,Feature` — green (minus `AbilityGatesTest`; plus the
  repurposed audit-endpoint test).
- `vendor/bin/pint --format agent`; `npm run build && npm run types:check && npm run lint:check`
  (no frontend change, but run for completeness — expect no diff).

## 8. Risk

Low. The only executable change is deleting redundant `Gate::authorize` calls whose roles equalled
their route middleware, so enforcement is unchanged; the ownership check that actually distinguishes
`mark-attendance` is retained. The bulk of the work is documentation.

## 9. File map

**Modify:** `app/Providers/AppServiceProvider.php`, `app/Http/Controllers/Sao/CourseController.php`,
`app/Http/Controllers/Lecturer/CourseSessionController.php`, `docs/adr/0022-authorization-enforcement-model.md`,
`docs/adr/README.md`, `docs/security.md`, `docs/architecture.md`, `docs/index.md`,
`docs/modules/course-management.md`, `docs/modules/admin-user-management.md`, `docs/modules/payments.md`,
`docs/modules/admissions.md`, `docs/modules/exam-gating.md`, `docs/modules/notifications.md`,
`docs/adr/0012-immutable-audit-log.md` (if it credits the gate).
**Create:** `docs/adr/0025-retire-ability-gates.md`, `tests/Feature/Audit/AuditLogAccessTest.php`.
**Delete:** `tests/Feature/Auth/AbilityGatesTest.php`, `tests/Feature/Audit/ViewAuditLogGateTest.php`.
