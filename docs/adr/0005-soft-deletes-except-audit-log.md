# ADR-0005: Soft deletes on domain tables; the audit log is append-only

- **Status:** Accepted
- **Date:** 2026-06-18
- **Deciders:** SchuLyf maintainers

## Context
Records in this system carry institutional weight — a user, a student profile, an application. A hard
delete loses history and can orphan references. At the same time, a few tables are immutable or purely
derived, where a `deleted_at` column would be meaningless or actively wrong (an audit log you can
soft-delete is not an audit log).

## Decision
**Domain tables use soft deletes** (22 tables carry `softDeletes()`), so a "delete" is recoverable and
account reactivation can restore a trashed row (see
[0021](0021-reactivation-via-password-reset.md)). The **`audit_logs` table is append-only**: no
`deleted_at`, `$timestamps = false`, and the `AuditLog` model **throws on `updating`/`deleting`** (see
[0012](0012-immutable-audit-log.md)).

## Consequences
- Most deletes are reversible; queries rely on Eloquent's soft-delete scope to hide trashed rows.
- The audit log can only grow (pruned by retention policy, never edited).
- **Honest scope note — `audit_logs` is the *headline* exception, not the only table without soft
  deletes.** Other tables omit it deliberately: `school_receipts` (immutable proof-of-payment),
  `fee_installments` (set-synced children, `cascadeOnDelete`), the counters `matricule_sequences` /
  `receipt_sequences`, `notifications`, and the `role_user` pivot. The rule is "soft-delete domain
  entities; immutable/derived/owned-set tables opt out for cause," not "everything except `audit_logs`."

See [`../data-model.md`](../data-model.md) and [`../security.md`](../security.md) §3.
