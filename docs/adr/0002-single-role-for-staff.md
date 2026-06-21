# ADR-0002: Staff hold a single role (multi-role intent superseded in practice)

- **Status:** Accepted
- **Date:** 2026-06-18
- **Deciders:** SchuLyf maintainers

## Context
The planning log (`plan/context.md` §4.2/§4.3) intended users to carry **multiple roles** via the
`role_user` pivot — e.g. a lecturer who is also an SAO. In practice this created ambiguity in the
admin UI (which dashboard? which role does a change apply to?) and in the per-role profile model, and
no real staff workflow needed more than one role. GitHub #20 (multi-role admin management) was closed
not-planned.

## Decision
**Staff and admin users hold exactly one role.** `CreateUserAction` assigns a single role at creation;
`ChangeUserRoleAction` **detaches every prior role** before attaching the new one and soft-deletes the
now-mismatched per-role profile — a swap, not an accumulation. The `role_user` **pivot is retained**
(not collapsed to a `role_id` column) precisely to keep one deliberate exception alive.

## Consequences
- The admin role picker is single-select; a user's "role" is unambiguous everywhere downstream.
- A role change is a clean transition with one old profile soft-deleted and (where applicable) one new
  profile written.
- **Deliberate exception — the Applicant→Student union.** `DecideApplicationAction::promoteToStudent()`
  calls `assignRole(Student)` **without** detaching `Applicant`, so a promoted student legitimately holds
  both roles via the pivot. This lives outside the admin module and is unaffected by the single-role rule.

## As-built vs. planned
Planned: arbitrary multi-role via the pivot (`plan/context.md` §4.2/§4.3). Shipped: single-role for
staff/admin, enforced in `CreateUserAction` and `ChangeUserRoleAction`; #20 closed not-planned. The
pivot survives only to support the Applicant+Student union. See
[`../modules/admin-user-management.md`](../modules/admin-user-management.md) §9 and
[`../security.md`](../security.md) §2.3.
