# ADR-0025: Retire the ability gates — authorization is role middleware + ownership

- **Status:** Accepted
- **Date:** 2026-07-05
- **Deciders:** SchuLyf maintainers

## Context
[ADR-0022](0022-authorization-enforcement-model.md) recorded that `AppServiceProvider::configureGates()`
declared 8 role-based ability gates (`process-admission`, `decide-application`, `validate-payment`,
`publish-results`, `approve-course-plan`, `mark-attendance`, `view-audit-log`, `manage-references`),
but only 3 were ever invoked and **every gate's role set was identical to the roles of the route
group its endpoints sit behind** — so a gate could never decide anything differently from the
`role:*` route middleware already in front of it. ADR-0022 accepted this "with a known gap" and named
the follow-up: wire the 5 uninvoked gates at their call sites, or retire them (GitHub #83).

## Decision
**Retire all 8 gates.** The `ABILITIES` map, `configureGates()`, and the 3 `Gate::authorize()` call
sites (`Sao\CourseController` approve/reject/publishResults; `Lecturer\CourseSessionController`
markAttendance) are removed. Authorization is now a single, honest model:

- **`role:*` route middleware** (`EnsureUserHasRole`) — the coarse role gate on every protected route
  group; `abort_unless($user->hasAnyRole($required), 403)` is semantically identical to the deleted
  `Gate::define` closures.
- **Per-resource ownership checks** in the controller/Action (e.g. `authorizeOwnership()` — a lecturer
  marks attendance only for their own course) — the genuine fine-grained layer, untouched.

The alternative — wiring the 5 uninvoked gates — was rejected: it adds redundant defence-in-depth that
restates the route middleware, and `manage-references` alone would mean ~16 `Gate::authorize` calls
across the reference controllers.

## Consequences
- **No behavior change.** Every request the gates would have denied was already denied by the route
  middleware (identical role sets); the `mark-attendance` gate's `[Lecturer, Admin]` set even had a
  dead `Admin` branch behind a `role:lecturer`-only route. Denial outcomes are unchanged and are
  covered by existing tests (`CoursePlanApprovalTest`, `PublishCourseResultsTest`, `MarkAttendanceTest`,
  `AdminAuthorizationTest`).
- The gate registry no longer misleads a reader into believing a second enforcement layer exists.
  `docs/security.md` and the module docs describe the real model (middleware + ownership).
- The `Gate` facade and `RoleName` import leave `AppServiceProvider`; the audit-event listeners are
  unaffected.
- If a genuine defence-in-depth need arises later (e.g. a controller not covered by a route group),
  add an explicit check there deliberately rather than reviving the blanket gate registry.

## As-built vs. planned
ADR-0022 documented the gates as a defined-but-largely-uninvoked layer and accepted the gap. This ADR
resolves that gap by removing the layer. **Supersedes ADR-0022.** Delivered on
`chore/retire-ability-gates` (#83).
