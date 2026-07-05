# ADR-0023: Structured per-document review makes DocumentsRequested a resolvable round-trip

- **Status:** Accepted
- **Date:** 2026-07-03
- **Deciders:** SchuLyf maintainers

## Context
Admissions already had a `DocumentsRequested` application status, but it was a **dead end** (#80): an
SAO could triage into it with a free-text note, yet the applicant had no way to act on it — the
application page was read-only and nothing moved the application forward. The office also had no
per-document verdict; "documents requested" was one note against the whole application, so the
applicant couldn't tell which of their uploads was the problem. We needed a way for the applicant to
respond that (a) named the exact documents at fault, (b) returned the application to the queue on its
own, and (c) reused the existing upload/audit/mail machinery rather than inventing a parallel one.

## Decision
**Give every `application_document` its own review state (`pending` / `accepted` / `rejected`) and
build the `DocumentsRequested` round-trip on top of it.** Concretely:

- An SAO accepts or rejects **each document individually** (`ReviewApplicationDocument`); a rejection
  stores the reason in `review_notes`, an acceptance clears it. Both endpoints re-fetch the
  application and document under `lockForUpdate()` and refuse a decided application (`AUD-001`).
- Triaging into `DocumentsRequested` is **guarded on ≥1 `rejected` document** — the request always
  names concrete documents to fix. `decision_notes` for that triage move is now **optional** (it used
  to be required).
- The applicant **replaces a rejected document in place** (`ReplaceRejectedDocument`). The row keeps
  its identity because `(application_id, document_type_id)` is unique *including trashed rows*, so
  delete-and-recreate is impossible; the file is swapped, the document reset to `pending`, and its
  review fields cleared (history stays in the audit log). File I/O happens **before** the transaction
  with rollback cleanup, matching the submit path (`AUD-009`).
- **Auto-resubmit** is a single shared concern, `ResolvesDocumentsRequested::flipToSubmittedWhenResolved()`,
  called by both the SAO-accept path and the applicant-replace path: while the application is
  `DocumentsRequested` and no `rejected` document remains, it flips back to `Submitted`. There is no
  manual SAO "re-open" step.
- Entering `DocumentsRequested` dispatches `ApplicationDocumentsRequested` (`afterCommit`), whose
  queued listener emails the applicant the list of rejected documents — mirroring the existing
  `ApplicationDecided` → `ApplicationDecisionMail` chain (the mailable is `Queueable`, queued at the
  listener).

**Explicitly out of scope:** requesting *additional* document types the applicant never uploaded. A
request can only reject documents that already exist on the application; asking for a brand-new
document kind is deferred.

## Consequences
- `application_documents` gains four columns (`status` default `pending` + indexed, `review_notes`,
  `reviewed_by`, `reviewed_at`) and a `reviewedBy` relation; `ApplicationDocumentStatus` follows the
  project's string-column + enum-cast rule ([ADR-0004](0004-string-columns-enum-casts.md)).
- Three new audit actions (`DocumentAccepted`, `DocumentRejected`, `DocumentResubmitted`) give a
  per-document forensic trail; the auto-flip writes its own `StatusChanged` row.
- The auto-flip trait is **the** place the "back to Submitted" rule lives — both call sites must go
  through it, and it must stay a no-op outside `DocumentsRequested` so a stray call can't resurrect a
  decided application.
- `DocumentsRequested` becomes a genuine interim state with a bounded lifecycle: one email per request
  round (the event fires on every *entry*), and the applicant clears it themselves. The SAO queue
  reflects resolved applications without manual bookkeeping.
- The transactional-mailable set grows from four to five, and the "which mailables are `ShouldQueue`"
  note in [notifications.md](../modules/notifications.md) §5.2 must stay honest (only the invite is;
  the rest queue at their listener).
- Cost: the SAO must reject at least one document before requesting — a deliberate friction so a
  request is never empty. A pure "please re-upload something new" ask isn't expressible until the
  out-of-scope item above is built.

## As-built vs. planned
Shipped as decided. Two refinements versus the original spec, both benign:

- The replace endpoint is `POST applications/{application}/documents/{document}` (plural prefix, no
  `/replace` suffix), matching the existing download/view document sibling routes; the route **name**
  (`application.documents.replace`) is unchanged.
- Unlike its GET download/view siblings, the replace `POST` route carries `scopeBindings` but **not**
  `throttle:lookups`. Low risk (owner-only, one row, multipart upload), noted here so a later
  hardening pass can add a write throttle deliberately rather than by surprise.

Delivered on `feat/documents-requested-response` (#80), backend commits `30045e4`→`c7dbc1d`, UI
`076951e`/`10351ba`. See [admissions.md](../modules/admissions.md) §5.6 for the full flow.
