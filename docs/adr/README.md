# Architecture Decision Records

This folder records the locked design decisions for SchuLyf, one numbered file per decision.

Each ADR follows the template: **Context · Decision · Status · Consequences · As-built vs. planned.**
The "As-built" section matters here — several decisions taken during the planning session
(`plan/context.md` §4) were later superseded in code. ADRs describe **what the code does today**,
with the original intent noted for history.

**Statuses:** `Proposed` · `Accepted` · `Superseded by ADR-NNNN` · `Deprecated`.

## Index

> All entries written and verified against source in phase **D3** (2026-06-20).

| ADR | Decision | Status |
|---|---|---|
| [0001](0001-role-model-and-pivot.md) | Custom `Role` model + `role_user` pivot + native Gates (no Spatie) | Accepted |
| [0002](0002-single-role-for-staff.md) | Staff single-role (multi-role intent superseded in practice) | Accepted |
| [0003](0003-per-role-profile-models.md) | Separate per-role profile models (no applicant/admin profile) | Accepted |
| [0004](0004-string-columns-enum-casts.md) | String columns + PHP backed-enum casts (no native DB ENUM) | Accepted |
| [0005](0005-soft-deletes-except-audit-log.md) | Soft deletes on domain tables; audit log append-only | Accepted |
| [0006](0006-restrict-on-delete-fks.md) | `restrictOnDelete` on FKs to real-data tables | Accepted |
| [0007](0007-multi-identifier-login.md) | Multi-identifier login (email / employee_id / phone / matricule) | Accepted |
| [0008](0008-email-verification-required.md) | Email verification required for all users | Accepted |
| [0009](0009-single-institution-per-install.md) | Single institution per install (no tenancy) | Accepted |
| [0010](0010-degree-program-offerings.md) | `DegreeProgram` enum + program-offerings + per-level credentials | Accepted |
| [0011](0011-application-data-snapshot.md) | Applications carry their own applicant-data snapshot | Accepted |
| [0012](0012-immutable-audit-log.md) | Immutable, significant-writes-only audit log | Accepted |
| [0013](0013-primevue-aura-no-globals.md) | PrimeVue/Aura for new UI, per-page imports (no globals) | Accepted |
| [0014](0014-session-auth-api-v1-lookups.md) | Session-auth web routes + `api/v1` same-origin lookups (no token API) | Accepted |
| [0015](0015-invite-link-credentials.md) | Invite-link credential flow for staff | Accepted |
| [0016](0016-money-integer-xaf.md) | Money stored as integer XAF | Accepted |
| [0017](0017-hmac-signed-receipts.md) | HMAC-signed school receipts + public verification | Accepted |
| [0018](0018-implicit-cohort-membership.md) | No enrollment table — implicit cohort membership | Accepted |
| [0019](0019-notification-channel-strategy.md) | Notification channel strategy (email / in-app / SMS deferred) | Accepted |
| [0020](0020-reference-data-cache.md) | Reference-data read-through cache (no tags) | Accepted |
| [0021](0021-reactivation-via-password-reset.md) | Re-registration = verify-first via password reset | Accepted |
| [0022](0022-authorization-enforcement-model.md) | AuthZ via role middleware + ownership; ability gates largely uninvoked | Accepted (known gap) |
