# ADR-0022: Authorization is enforced by role middleware + ownership; ability gates are largely uninvoked

- **Status:** Superseded by [ADR-0025](0025-retire-ability-gates.md) — the ability gates were retired; role middleware + ownership is now the single documented model
- **Date:** 2026-06-20
- **Deciders:** SchuLyf maintainers

## Context
The codebase defines named **ability gates** in `AppServiceProvider::ABILITIES`
(`process-admission`, `decide-application`, `validate-payment`, `manage-references`, `view-audit-log`,
`approve-course-plan`, `publish-results`, `mark-attendance`) and tests them
(`tests/Feature/Auth/AbilityGatesTest.php`, `tests/Feature/Audit/ViewAuditLogGateTest.php`). The intent
(`plan/context.md` §4) was for these gates to be the authorization layer. A code-verification pass for
ADR 0001/0012 found that most are never called at a request call site.

## Decision
Record the **as-built reality**: authorization is enforced primarily by the **`role:` route-group
middleware** plus **per-resource ownership checks** (e.g. `authorizeOwnership()` for a lecturer's own
course session). Only **3 of the 8** ability gates are actually wired into the request path. This is
**Accepted with a known gap**, and the gap is tracked as a backlog item: either invoke
`Gate::authorize(...)` at the controller/action layer for the unwired gates, **or** retire them so the
gate set matches what is enforced.

Verified per-gate evidence (grep of `app/` for `Gate::authorize`/`allows`/`->can(`/`@can`/`can:`):

| Gate | Wired? | Evidence |
|---|---|---|
| `approve-course-plan` | **Yes** | `Sao/CourseController.php:128` (approve), `:139` (reject) |
| `publish-results` | **Yes** | `Sao/CourseController.php:150` |
| `mark-attendance` | **Yes** | `Lecturer/CourseSessionController.php:198` (+ `authorizeOwnership()`) |
| `process-admission` | No | endpoint guarded by `role:sao,admin` (`routes/sao.php:16`) only |
| `decide-application` | No | endpoint guarded by `role:sao,admin` (`routes/sao.php:17`) only |
| `validate-payment` | No | endpoint guarded by `role:accountant,admin` (`routes/accountant.php:16`) only |
| `manage-references` | No | endpoints guarded by the `role:admin` group (`routes/admin.php:13`) only |
| `view-audit-log` | No | endpoint guarded by the `role:admin` group (`routes/admin.php:13,20`) only |

## Consequences
- **Every endpoint is still correctly authorized** — the `role:` middleware (and ownership checks)
  cover all of them. This is a defense-in-depth / maintainability gap, **not an open access hole**.
- The five unwired gates are effectively **dead code at the call-site layer**: they pass their unit
  tests but guard nothing in production, which is misleading to a future maintainer.
- Authorization rules are split across two places (route middleware + a gate registry that's mostly
  unused); the gate registry is **not** a reliable index of what is enforced.
- Backlog choice (one or the other): wire `Gate::authorize()` at each action/controller, or delete the
  unwired gate definitions and their tests.

## As-built vs. planned
Planned: ability gates as the authorization layer (`plan/context.md` §4). Shipped: `role:` middleware +
ownership as the real enforcement, with 5 of 8 gates defined-but-uninvoked. Note: `docs/security.md`
§2.1/§3.4 currently implies the `view-audit-log` gate guards the audit endpoint — strictly it is the
`role:admin` middleware; tighten that wording in a follow-up. See [`../security.md`](../security.md) §2.3.
