# ADR-0007: Multi-identifier login (email / employee_id / phone / matricule)

- **Status:** Accepted
- **Date:** 2026-06-18
- **Deciders:** SchuLyf maintainers

## Context
Different user populations know themselves by different identifiers: applicants and staff by email,
staff by an employee ID, students by their matricule, and (after B9) any user by phone number. Forcing
a single login field would make sign-in awkward for at least one population. A single typed credential
must resolve to one user without the UI asking "what kind of identifier is this?".

## Decision
Login accepts **four identifiers in one field**, resolved server-side by the Fortify
`authenticate-using` resolver in `FortifyServiceProvider`: **`email`**, **`users.employee_id`**,
**normalized `users.phone`**, and **`student_profiles.matricule`**. The resolver matches in a single
query; the phone clause is added **only when the input normalizes to a phone number**, so it never
degrades into a `phone IS NULL` match.

## Consequences
- One login form serves every role; users type whatever identifier they know.
- The resolver — not a fixed `email` column — is the source of truth for "who is this credential?", so
  identifier rules (uniqueness, normalization) must hold across `users` and `student_profiles`.
- Adding a fifth identifier is a resolver change, not a schema or UI change.

## As-built vs. planned
Planned **three** identifiers — email / matricule / employee_id (`plan/context.md` §4.6). **B9 added
phone**, making the shipped resolver **four**. `users.phone` is `nullable()->unique()`. See
[`../security.md`](../security.md) §1.1 (already documents the four-identifier resolver).
