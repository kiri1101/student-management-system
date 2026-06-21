# ADR-0010: Academic structure — DegreeProgram enum + program offerings + per-level credentials

- **Status:** Accepted
- **Date:** 2026-06-18
- **Deciders:** SchuLyf maintainers

## Context
The catalog must express "this department offers this degree program, across these levels, and
admission at each level requires these documents." Modeling degree programs as a free table would let
inconsistent values in; modeling credential requirements globally would ignore that requirements differ
by program and level.

## Decision
A fixed **`DegreeProgram` enum** (`Hnd`, `Bachelors`, `Masters`) — no table. Departments offer programs
through **`program_offerings`**: `department_id`, `degree_program` (string + enum cast),
`min_level`/`max_level`, with a **unique `(department_id, degree_program)`**. Per-level admission
requirements live in **`level_credential_requirements`**, keyed unique on
`(program_offering_id, level, document_type_id)`.

## Consequences
- The set of degree programs is closed and lives in code (consistent with [0004](0004-string-columns-enum-casts.md)).
- A department can't list the same program twice; levels are bounded per offering.
- Required documents are precise — they vary by offering **and** level — and drive the applicant
  document checklist.
- This offering + level tuple is also what student cohorts are built on (see
  [0018](0018-implicit-cohort-membership.md)).

As-built matches the planned design (`plan/context.md` §4) — no divergence. See
[`../modules/admissions.md`](../modules/admissions.md) and [`../data-model.md`](../data-model.md).
