# ADR-0008: Email verification required for all users

- **Status:** Accepted
- **Date:** 2026-06-18
- **Deciders:** SchuLyf maintainers

## Context
The system sends consequential transactional mail (admission decisions, school receipts, invitations)
and gates campus-facing actions on identity. An unverified or mistyped email address would silently
break those flows and undermine the audit trail.

## Decision
**Every user must verify their email.** `User implements MustVerifyEmail`,
`Features::emailVerification()` is enabled in `config/fortify.php`, and the `verified` middleware guards
the authenticated route groups. Self-registered applicants verify via the standard Fortify verification
email; **invited staff are pre-verified** — `CreateUserAction` sets `email_verified_at = now()` because
the invite link itself proves control of the mailbox (see [0015](0015-invite-link-credentials.md)).

## Consequences
- No authenticated user can reach a dashboard with an unverified address.
- Transactional mail can assume a deliverable, verified address.
- The invite flow folds verification into account setup, so staff never see a separate "verify your
  email" step.

See [`../security.md`](../security.md) for the auth middleware stack.
