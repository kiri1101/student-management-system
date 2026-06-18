# Architecture Decision Records

This folder records the locked design decisions for SchuLyf, one numbered file per decision.

Each ADR follows the template: **Context · Decision · Status · Consequences · As-built vs. planned.**
The "As-built" section matters here — several decisions taken during the planning session
(`plan/context.md` §4) were later superseded in code. ADRs describe **what the code does today**,
with the original intent noted for history.

**Statuses:** `Proposed` · `Accepted` · `Superseded by ADR-NNNN` · `Deprecated`.

## Index

> All entries are `Proposed` until written and verified against source in phase **D3**.
> Final numbering may shift as decisions merge or split.

| ADR | Decision | Status |
|---|---|---|
| 0001 | Custom `Role` model + `role_user` pivot + Gate/Policy (no Spatie) | Proposed |
| 0002 | Multi-role intent → single-role-for-staff as built | Proposed |
| 0003 | Separate per-role profile models (no applicant/admin profile) | Proposed |
| 0004 | String columns + PHP backed-enum casts (no native DB ENUM) | Proposed |
| 0005 | Soft deletes everywhere except `audit_logs` | Proposed |
| 0006 | `restrictOnDelete` on all FKs to real-data tables | Proposed |
| 0007 | Three-identifier login (email / matricule / employee_id) | Proposed |
| 0008 | Email verification required for all users | Proposed |
| 0009 | Single institution per install (no tenancy) | Proposed |
| 0010 | `DegreeProgram` enum + program-offerings + per-level credentials | Proposed |
| 0011 | Applications carry their own applicant-data snapshot | Proposed |
| 0012 | Immutable, significant-writes-only audit log | Proposed |
| 0013 | PrimeVue/Aura for new UI, per-page imports (no globals) | Proposed |
| 0014 | Session-auth web routes + `api/v1` same-origin lookups (no token API) | Proposed |
| 0015 | Invite-link credential flow for staff | Proposed |
| 0016 | Money stored as integer XAF | Proposed |
| 0017 | HMAC-signed school receipts + public verification | Proposed |
| 0018 | No enrollment table — implicit cohort membership | Proposed |
| 0019 | Notification channel strategy (email / in-app / SMS deferred) | Proposed |
| 0020 | Reference-data read-through cache (no tags) | Proposed |
| 0021 | Re-registration = verify-first via password reset | Proposed |
