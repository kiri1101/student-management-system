# ADR-0001: Custom Role model with a role_user pivot and native Gates

- **Status:** Accepted
- **Date:** 2026-06-18
- **Deciders:** SchuLyf maintainers

## Context
The system has six roles (applicant, student, SAO, accountant, lecturer, admin) with
distinct dashboards and abilities. The obvious off-the-shelf option is `spatie/laravel-permission`.
We wanted full control over the role/profile lifecycle (per-role profile models, soft-deleting a
profile on a role swap, an immutable audit trail of role changes) without inheriting a package's
schema, caching, and permission-table semantics — and without adding a dependency for something the
framework already supports.

## Decision
Authorization is built on a **hand-rolled `Role` model + a `role_user` pivot**, with access enforced
through **Laravel's native Gate/Policy layer** and a `role:` route middleware. No Spatie (or other RBAC)
package is installed. Role identity is a `RoleName` backed enum; `User` exposes `hasRole()` /
`hasAnyRole()` / `assignRole()` helpers over the pivot.

## Consequences
- Full control of the schema and the role-change side effects (profile create/soft-delete, audit log).
- We own and must maintain the role-check helpers and the `role:` middleware instead of a maintained package.
- Permissions are coarse — role-level, not a granular permission table. Finer rules live in ability
  gates and per-resource ownership checks (see [0022](0022-authorization-enforcement-model.md)).
- The pivot (rather than a single `role_id` column) is what makes the Applicant+Student union in
  [0002](0002-single-role-for-staff.md) possible.

See [`../security.md`](../security.md) §2 for the full role/gate map.
