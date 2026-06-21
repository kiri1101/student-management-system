# ADR-0021: Re-registration is verify-first via the password-reset flow

- **Status:** Accepted
- **Date:** 2026-06-18
- **Deciders:** SchuLyf maintainers

## Context
Accounts are soft-deleted (see [0005](0005-soft-deletes-except-audit-log.md)), so an email may belong to
a trashed user. A returning applicant who re-registers must not be able to (a) discover that an account
exists for an email (enumeration — AUD-004/AUD-017), or (b) hijack a trashed account by simply
re-registering it. Reactivation must prove control of the mailbox first.

## Decision
**Re-registration / reactivation is verify-first via the password-reset flow.** Registration **422s on a
trashed *or* active email** through the unique rule — an indistinguishable response either way, so it
leaks nothing. To return, the user runs **password reset**: `ResetUserPassword::reset()` calls
`reactivate()`, which **restores the trashed row, detaches all prior roles, and audits** (`RoleRevoked`
per prior role + `Restored`) in a transaction. A dedicated `PasswordBrokerUserProvider`
(`eloquent-with-trashed`, wired for the password broker only) finds trashed users — but **returns `null`
for trashed staff** (`hasAnyRole(staff)`), so **staff/admin are excluded** and can only be brought back
by an admin restore.

## Consequences
- No registration response reveals whether an email exists; reactivation always requires mailbox proof.
- A reactivated applicant comes back as a clean, role-reset account with a full audit trail.
- Staff/admin cannot self-reactivate — a deliberate guardrail; their return is an admin action.
- The password broker uses a different user provider than auth, which is intentional — don't unify them.

See [`../security.md`](../security.md) and [`../modules/admin-user-management.md`](../modules/admin-user-management.md).
