# ADR-0011: Applications carry their own snapshot of applicant data

- **Status:** Accepted
- **Date:** 2026-06-18
- **Deciders:** SchuLyf maintainers

## Context
An application is a point-in-time submission that the SAO reviews and decides on. If it always read the
applicant's *current* user record, later edits to that record would silently rewrite what was actually
submitted and decided — a problem for fairness and for the audit trail. Applicants also have no profile
model (see [0003](0003-per-role-profile-models.md)), so their personal data needs a home.

## Decision
The **`applications` row stores its own snapshot** of the applicant's submitted data — `first_name`,
`last_name`, `contact_email`, `phone`, `date_of_birth`, `previous_institute` — **distinct from**
`users.name` / `users.email`. The application is decided against this snapshot, not against the live
user record.

## Consequences
- What was submitted and reviewed is preserved verbatim, regardless of later profile edits.
- Applicant personal data lives on the application (there is no `applicant_profile`).
- Some duplication between snapshot and user fields is intentional, not a normalization defect.
- On admission, `DecideApplicationAction::promoteToStudent()` reads the snapshot's
  `program_offering_id` + `level` to build the `StudentProfile` — the bridge from snapshot to cohort
  (see [0018](0018-implicit-cohort-membership.md)).

As-built matches the planned design — no divergence. See
[`../modules/admissions.md`](../modules/admissions.md).
