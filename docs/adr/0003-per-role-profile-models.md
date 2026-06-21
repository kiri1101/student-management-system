# ADR-0003: Separate per-role profile models (no applicant or admin profile)

- **Status:** Accepted
- **Date:** 2026-06-18
- **Deciders:** SchuLyf maintainers

## Context
Each staff/student role needs role-specific attributes (a student's matricule and cohort, a lecturer's
employee data, etc.). Stuffing every role's fields onto `users` would produce a wide, mostly-null table
where most columns are meaningless for any given user, and would couple unrelated role concerns.

## Decision
Role-specific data lives in **separate per-role profile models**, each a `HasOne` off `User`:
`studentProfile`, `lecturerProfile`, `accountantProfile`, `saoProfile`. Each profile table has a
**unique `user_id`**, **soft deletes**, and a **`restrictOnDelete`** FK to `users`. There is
**no `applicant_profile` table** and **no admin profile** — `WritesRoleProfile::writeProfile()` returns
`null` for the Applicant and Admin roles.

## Consequences
- `users` stays narrow; each role's fields are isolated and independently validated.
- A role swap soft-deletes the old profile and writes the new one (see
  [0002](0002-single-role-for-staff.md)), so profile history is preserved, not destroyed.
- Applicant data has no profile model — it is carried on the `applications` snapshot instead (see
  [0011](0011-application-data-snapshot.md)). Admin is intentionally profile-less.
- Reading a user's role data means touching the right profile relation; there is no single polymorphic
  profile table.

See [`../data-model.md`](../data-model.md) for the profile tables.
