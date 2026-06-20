# ADR-0006: restrictOnDelete on FKs to real-data tables (no silent cascade)

- **Status:** Accepted
- **Date:** 2026-06-18
- **Deciders:** SchuLyf maintainers

## Context
With soft deletes on domain tables (see [0005](0005-soft-deletes-except-audit-log.md)), a hard delete
should be rare and never silently destroy related institutional data. Default cascade behavior risks a
single delete fanning out across applications, payments, and results.

## Decision
FK on-delete behavior is chosen per relationship (35 on-delete clauses across 22 migrations):
- **`restrictOnDelete()`** — FKs pointing to tables that hold real institutional data (the default;
  deleting a referenced parent is blocked).
- **`nullOnDelete()`** — nullable *actor* columns where losing the actor shouldn't block the delete
  (`reviewed_by`, `decided_by_user_id`, `audit_logs.user_id`).
- **`cascadeOnDelete()`** — only for **owned children synced as a set** (`fee_installments`,
  `course_sessions`, `attendance_records`, `assignments`, `assignment_submissions`, `course_results`,
  `result_disputes`).

## Consequences
- You cannot accidentally erase a chain of records by deleting one parent; restricts surface as errors.
- Actor references degrade gracefully to null instead of blocking or cascading.
- Set-owned children are correctly torn down with their parent, matching how they are created.
- Removing a referenced entity is a deliberate operation (detach/soft-delete first), not a cascade.

See [`../data-model.md`](../data-model.md) for the full FK map.
