# ADR-0004: Fixed-value columns are string + PHP backed-enum casts (no native DB ENUM)

- **Status:** Accepted
- **Date:** 2026-06-18
- **Deciders:** SchuLyf maintainers

## Context
Many columns hold a closed set of values — application status, payment status, role name, degree
program, document type, course/result status. The native MySQL `ENUM` type encodes that set in the
schema, but changing it requires an `ALTER TABLE`, the allowed values live in the database rather than
the codebase, and the values are awkward to share with the Vue/Inertia frontend and tests.

## Decision
Every fixed-value column is a **`string` column cast to a PHP backed enum** in the model's `$casts`.
**No migration uses `$table->enum(...)`.** Defaults are taken from the enum case
(e.g. `->default(ApplicationStatus::Draft->value)`). There are **16 backed enums** under `app/Enums/`.

## Consequences
- The allowed set lives in PHP (one enum per concept), is the single source of truth, and is shared by
  models, validation, and Wayfinder-typed frontend code.
- Adding or renaming a value is a code change, not a schema migration.
- The database does not enforce the set — the enum cast and form-request validation do. A raw SQL write
  could store an out-of-set string, so writes must go through the models.
- Enum keys use TitleCase; the stored `->value` strings are the wire/database format (typically
  lowercase) — see the status maps in the module docs.

See [`../data-model.md`](../data-model.md) for the per-table enum columns.
