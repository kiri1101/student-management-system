# ADR-0024: Applications are born Submitted — no Draft state

- **Status:** Accepted
- **Date:** 2026-07-05
- **Deciders:** SchuLyf maintainers

## Context
The application schema and state machine were designed with a `Draft` status as the first
`ApplicationStatus` (§6.4 / the Phase 7 status design), and AUD-010 even added a dedicated
`Draft → Submitted` transition. But the shipped `ApplicationController::store()` creates every
application directly as `Submitted` with `submitted_at = now()`, uploading all required documents in
the same request. **Nothing in the application ever persisted a `Draft` row.** `Draft` survived only
as dead surface: the enum case, the migration column default, a member of `OPEN_STATUSES`, the
unreachable `Draft → Submitted` branch in `canTransitionTo()`, an SAO prior-applications exclusion
filter, the factory default, and a frontend status-map entry. A half-removed state — a dead enum case
and an unreachable transition — is misleading to future maintainers (#82).

## Decision
**Remove the `Draft` state; applications are born `Submitted`.** The alternative — building
save-as-draft so the state becomes reachable — was rejected. The admission application is a one-time,
submit-once artifact, not a document users iteratively edit across sessions, so cross-session resume
is marginal value. Supporting genuine drafts would have required relaxing the `applications` table's
`NOT NULL` columns to nullable everywhere (taxing every consumer), a storage/expiry lifecycle for
documents attached to unsubmitted drafts, and "block vs replace" logic for the one-open-application
guard (which counts `OPEN_STATUSES`). That is disproportionate cost for the value; it can be built
deliberately later if the product ever wants it.

Concretely: the `Draft` enum case, its `OPEN_STATUSES` membership, and the `Draft → Submitted`
transition branch are deleted; the `applications.status` column default becomes `Submitted`; the SAO
prior-applications query drops its `Draft` exclusion; the factory is born `Submitted`.

## Consequences
- The state machine tells the truth: applications start `Submitted` and move through the interim trio
  to a terminal decision. `OPEN_STATUSES` now equals `INTERIM_STATUSES` (kept as a distinct semantic
  alias — "counts as an open application" is a separate concept from "interim triage state").
- `canTransitionTo()` simplifies to "an interim source may move to any status; a terminal source may
  not." Its `ApplicationStatus $next` parameter is retained for call-site stability but is no longer
  consulted (the SAO Form Requests constrain valid targets); a future cleanup may drop it.
- The AUD-010 `Draft → Submitted` transition no longer exists. No data migration is needed — there
  were never any `Draft` rows to migrate.
- This decision does **not** touch the unrelated `Draft` values of `CoursePlanStatus` /
  `ResultStatus`, which are live.

## As-built vs. planned
Planned (§6.4 / Phase 7): `Application::status` defaults to `Draft`; submit flips it to `Submitted`;
AUD-010 added a `Draft → Submitted` transition. Shipped: submit created `Submitted` directly and no
draft was ever persisted, leaving `Draft` as dead code — now removed. Delivered on
`chore/remove-draft-state` (#82).
