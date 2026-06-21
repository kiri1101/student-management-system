# ADR-0015: Invite-link credential flow for staff (admin never sets passwords)

- **Status:** Accepted
- **Date:** 2026-06-18
- **Deciders:** SchuLyf maintainers

## Context
Admins create staff accounts (SAO, accountant, lecturer, other admins). If the admin chose the initial
password, that password would pass through the admin's hands, the audit log, and possibly a chat
message — a credential-handling and repudiation problem. Staff should own their own password from the
first login.

## Decision
**Admins never set or see a staff password.** `CreateUserAction` saves a **random 64-character
password** the admin never sees, `forceFill`s `email_verified_at = now()`, then queues
`UserInvitationMail` carrying a **Fortify `password.reset` token**. The invitee follows the signed link
and sets their own password. **Resend rotates the token** (one active token per user); the setup link
expires after `config('auth.passwords.users.expire')` (72h). The random plaintext is **never stored,
logged, or audited** (`auditRedact()`).

## Consequences
- No human-chosen initial password ever exists; the credential boundary is the invitee's mailbox.
- Account setup and email verification (see [0008](0008-email-verification-required.md)) collapse into
  one link.
- "Forgot password" and "accept invite" reuse the same Fortify reset machinery — fewer moving parts.
- A stale or leaked invite link self-expires and can be rotated by resending.

See [`../modules/admin-user-management.md`](../modules/admin-user-management.md) for the full flow.
