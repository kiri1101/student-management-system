# ADR-0009: Single institution per install (no tenancy)

- **Status:** Accepted
- **Date:** 2026-06-18
- **Deciders:** SchuLyf maintainers

## Context
The system models one university's admissions, payments, and course management. Multi-tenancy (an
`institution_id` discriminator on every table, scoped queries, per-tenant config) is a large, pervasive
commitment. Nothing in the problem domain — a single Cameroonian institution's processes — requires it.

## Decision
**One institution per installation.** There is **no tenancy** — no `institution_id`/`tenant_id` column,
no tenant scoping, no tenancy package. Verified: zero matches for `institution` / `tenant` / `tenancy`
across `app/` and `database/`.

## Consequences
- Every table is simpler — no tenant key, no global scope, no per-tenant migration concerns.
- Serving a second institution means a separate deployment + database, not a schema change.
- If multi-tenancy is ever needed, it is a deliberate, large future ADR that supersedes this one — not
  an incremental tweak.

See [`../data-model.md`](../data-model.md).
