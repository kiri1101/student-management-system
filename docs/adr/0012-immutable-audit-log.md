# ADR-0012: Immutable, significant-writes-only audit log

- **Status:** Accepted
- **Date:** 2026-06-18
- **Deciders:** SchuLyf maintainers

## Context
Admissions, payment validation, role changes, and reactivations are decisions that can be disputed.
The system needs a trustworthy record of *who did what, when* — one that cannot be quietly edited or
deleted after the fact, and that isn't drowned in noise from trivial writes.

## Decision
An **append-only `audit_logs` table** records only **significant writes**. The `AuditLog` model
**throws on `updating` and `deleting`** (`app/Models/AuditLog.php:41-47`); the table has **no
`deleted_at`** and `$timestamps = false` (it carries its own timestamp). Models opt in via the
`RecordsAudit` concern, which excludes/redacts sensitive fields (e.g. password plaintext — see
[0015](0015-invite-link-credentials.md)). Retention is **`RETENTION_DAYS = 730`**, enforced by an
`audit:prune` command that deletes aged rows **via the query builder** (bypassing the model guard).

## Consequences
- The log can only be appended to or pruned by policy — never edited, never soft-deleted.
- Pruning is the **one** sanctioned bulk delete, and it deliberately sidesteps the `deleting` guard via
  the query builder; application code cannot delete a single row.
- Audited models must keep the `RecordsAudit` redaction list correct — anything not excluded is stored.
- Read access is **enforced by the `role:admin` route-group middleware** on the audit-log endpoint. Note
  the `view-audit-log` **ability gate is defined but not invoked** at that call site — see
  [0022](0022-authorization-enforcement-model.md).

See [`../security.md`](../security.md) §3.
