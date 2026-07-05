# Applicant response flow for `DocumentsRequested` (#80) — Design

**Status:** Approved by owner 2026-07-03 (brainstorming session; decisions locked below).
**Branch:** `feat/documents-requested-response` (off `main` @ `ef51f16`). One PR closing #80.
**Origin:** plan-vs-shipped omissions audit finding 1 (`plan/context.md` §24) — §4.5 promised
applicants could "upload missing documents" post-submission; nothing shipped. `DocumentsRequested`
is a dead end on both sides: the applicant is never notified and has no in-app action, and the
AUD-005 one-open-application guard blocks re-applying while stuck there.

## Locked decisions

1. **Documents only.** The applicant can replace rejected documents; demographic fields
   (name, DOB, phone, previous institute) stay immutable post-submission. A demographics
   correction flow, if ever needed, is a separate backlog item.
2. **Structured per-document review** (not free-text). Each `ApplicationDocument` carries its own
   review status; the SAO rejects specific documents with a required reason. `decision_notes`
   stops being load-bearing for document requests.
3. **Three-state document status:** `pending` (on upload *and* on resubmit) → `accepted` /
   `rejected` (SAO). Only `rejected` documents are applicant-editable. Two states would be
   ambiguous: a resubmitted file must not display as already-checked.
4. **Guard:** triage **to** `DocumentsRequested` requires ≥ 1 currently-rejected document —
   entering the status now *requires* creating the applicant's to-do list.
5. **Auto-flip:** when the last rejected document is resolved, the application flips
   `DocumentsRequested → Submitted` automatically (interim→interim, already legal in
   `Application::canTransitionTo()`). Trigger points: applicant replaces the last rejected
   document, **or** SAO accepts the last rejected document while the application sits in
   `DocumentsRequested` (prevents an SAO-side dead end).
6. **Approach 1 — per-document replace endpoint.** Each rejected document is replaced
   individually and immediately; partial progress is durable. No bulk "send response" form.
7. **In-place row update, not soft-delete + recreate.** The `(application_id, document_type_id)`
   unique index deliberately includes trashed rows (Phase 4 decision) — recreate would collide.
   File history is preserved via the audit log, not via rows.
8. **Email only.** One queued mail to the application's `contact_email` whenever the application
   *enters* `DocumentsRequested` (first entry or re-entry), listing rejected documents + reasons.
   No in-app notification (applicants have no feed surface), no SAO-side notification (queue
   reappearance is the SAO's signal), no mail on acceptance.

## 1. Data model

Edit the original `application_documents` migration in place (+ `migrate:fresh --seed`, local
workflow). New columns:

| Column | Type | Notes |
|---|---|---|
| `status` | `string`, default `'pending'`, indexed | cast to `ApplicationDocumentStatus` |
| `review_notes` | nullable `text` | SAO's rejection reason |
| `reviewed_by` | nullable FK → `users`, `nullOnDelete` | last reviewer |
| `reviewed_at` | nullable `datetime` | |

New enum `App\Enums\ApplicationDocumentStatus` (string-backed, TitleCase keys, lowercase values):
`Pending = 'pending'`, `Accepted = 'accepted'`, `Rejected = 'rejected'`; `label()`.

**Resubmit semantics:** row updated in place — new `file_path`, `original_filename`, `mime_type`,
`size_bytes`, `uploaded_at = now()`, `status = pending`, and `review_notes` / `reviewed_by` /
`reviewed_at` **nulled**. Rejection history lives in the audit log (`DocumentRejected` rows carry
the reason), not on the row.

`ApplicationDocument` model: add `status` cast + `reviewedBy()` relation + factory states
`accepted()` / `rejected(?string $notes)`.

## 2. SAO side — per-document review

**Routes** (`routes/sao.php`, existing `role:sao,admin` group, `scopeBindings`):

| Method · URI | Name |
|---|---|
| POST `sao/applications/{application}/documents/{document}/accept` | `sao.applications.documents.accept` |
| POST `sao/applications/{application}/documents/{document}/reject` | `sao.applications.documents.reject` |

Reject uses a Form Request (`App\Http\Requests\Sao\RejectApplicationDocumentRequest`) requiring
`notes` (string, `max:1000`). Accept takes no body. Split routes mirror course-plan
approve/reject.

**Action `App\Actions\Sao\ReviewApplicationDocument`** — single action for both verbs:
`execute(ApplicationDocument $document, ApplicationDocumentStatus $decision, ?string $notes, User $reviewer)`.
Inside `DB::transaction`:
- re-fetch document + application under `lockForUpdate` (AUD-001 pattern);
- refuse when the application is terminal (`ValidationException`);
- accept only `Accepted`/`Rejected` as `$decision`;
- write `status`, `review_notes` (null on accept), `reviewed_by`, `reviewed_at`;
- audit `DocumentAccepted` / `DocumentRejected` (subject = document, notes in changes);
- **auto-flip check** (shared trait, see below): if `$decision === Accepted`, the application is
  in `DocumentsRequested`, and no `rejected` documents remain → flip application to `Submitted`
  (`saveQuietly` + `StatusChanged` audit, userId = reviewer).

The auto-flip lives in a shared concern **`App\Actions\Concerns\ResolvesDocumentsRequested`**
(`flipToSubmittedWhenResolved(Application $application, User $actor): void`) used by both this
action and `ReplaceRejectedDocument` — two classes, one rule, called inside each action's
transaction while the application row is still locked.

**Triage guard** (`TriageApplicationAction`): when `$next === DocumentsRequested`, require
`$application->documents()->where('status', Rejected)->exists()`, else `ValidationException`
("Reject at least one document before requesting documents."). Consequence: the triage `notes`
requirement for `DocumentsRequested` in `TriageApplicationRequest` is **relaxed to optional**
(per-document reasons are the payload now); existing triage tests updated.

## 3. Applicant side — per-document replace

**Route** (`routes/web.php`, existing `auth` + `verified` applicant area, `scopeBindings`):
POST `application/{application}/documents/{document}` → `application.documents.replace`.

**Form Request `App\Http\Requests\Applications\ReplaceDocumentRequest`:** `document` file,
required, `mimes:pdf,jpg,jpeg,png`, `max:8192` — constants extracted/shared from
`StoreApplicationRequest` (make `ALLOWED_MIMES` / `MAX_FILE_KB` public there; no duplication).

**Action `App\Actions\Applicant\ReplaceRejectedDocument`:**
- controller pre-check: `abort_if($application->user_id !== $request->user()->id, 403)` (matches
  `show()`);
- store the new file **before** the transaction (`applications` dir on the default private disk);
- inside `DB::transaction` (application re-fetched under `lockForUpdate`): refuse unless
  application status is `DocumentsRequested` **and** the document's status is `rejected`
  (`ValidationException`); capture old `file_path`; update row per resubmit semantics (§1);
  audit `DocumentResubmitted` (changes: old/new original_filename); run
  `flipToSubmittedWhenResolved` (actor = applicant) — `submitted_at` is **not** modified;
- after commit: delete the old file; on any failure: delete the newly-stored file and rethrow
  (AUD-009 pattern, mirrors `ApplicationController::store()`).

## 4. Notification

`App\Mail\ApplicationDocumentsRequestedMail` — queued markdown mailable to
`$application->contact_email`: greeting, list of rejected documents (type name + reason), CTA
link to `application.show`, "sign in to replace them" copy.

Wiring mirrors the decision mail: event `App\Events\ApplicationDocumentsRequested` dispatched via
`DB::afterCommit` from `TriageApplicationAction` when the transition lands on
`DocumentsRequested`; queued listener `SendDocumentsRequestedNotification` builds + queues the
mail. Fires on **every** entry into the status (first time and re-entries after a failed
resubmission cycle). No other notifications.

## 5. Frontend

- **`sao/applications/Review.vue`:** documents table gains a status `Tag` column (statusDisplay
  map) and per-row Accept / Reject buttons; Reject opens a small `Dialog` with a `Textarea` for
  the reason. Controls hidden when the application is terminal. (PrimeVue docs-first rule applies
  at implementation.)
- **`applicant/applications/Show.vue`:** every document row shows a status `Tag`
  (*Awaiting review* / *Accepted* / *Rejected*). When the application is `documents_requested`,
  each **rejected** row additionally shows the SAO's reason and an upload control posting to
  `application.documents.replace` (`useForm`, `forceFormData`); non-rejected rows render name +
  tag only. A `Message` banner explains the situation and remaining count ("1 of 3 documents
  still needs your attention"). The final successful upload flips status server-side; the
  refreshed page shows *Submitted* + server flash toast.
- **`resources/js/lib/statusDisplay.ts`:** add `applicationDocumentStatusLabel/Severity`
  (pending = `warn` "Awaiting review", accepted = `success`, rejected = `danger`).

## 6. Audit

`App\Enums\AuditAction` += `DocumentAccepted`, `DocumentRejected`, `DocumentResubmitted`.
`StatusChanged` reused for the guarded triage entry and both auto-flip triggers.

## 7. Tests (~20 new Pest cases, `tests/Feature/`)

- **SAO review** (`Sao/ReviewApplicationDocumentTest`): accept + reject happy paths incl. audit
  rows and review metadata; reject without notes → 422; non-SAO roles → 403; terminal
  application → 422; accepting the last rejected doc in `DocumentsRequested` auto-flips to
  `Submitted` + `StatusChanged` audit.
- **Triage guard** (extend `TriageApplicationTest`): to `DocumentsRequested` with zero rejected
  docs → 422; with ≥1 rejected → OK, notes now optional, `ApplicationDocumentsRequestedMail`
  queued to `contact_email` (`Mail::fake`), fires again on re-entry.
- **Applicant replace** (`Applications/ReplaceRejectedDocumentTest`): non-owner → 403; wrong
  application status → 422; non-rejected document → 422; mime/size validation → 422; happy path
  replaces in place (row fields, `pending`, review metadata nulled, old file deleted, new file
  stored — `Storage::fake`); `DocumentResubmitted` audit; partial replace keeps
  `DocumentsRequested`; final replace flips to `Submitted` (+audit, `submitted_at` unchanged);
  full re-rejection cycle works end-to-end.
- `ApplicationDocumentFactory` states used throughout; existing triage/decide tests updated where
  they enter `DocumentsRequested`.

## 8. Delivery

Two phase-commits on one branch/PR closing #80, full quality gate each
(`pint` → `test --testsuite=Unit,Feature` → `npm run build` → `types:check` → `lint:check` →
`migrate:fresh --seed`):

1. **Backend:** migration + enum + model/factory + actions + form requests + routes + mail/event/
   listener + triage guard/notes relaxation + all tests.
2. **Frontend + docs:** both Vue pages + statusDisplay + docs-refresh (admissions module doc,
   routes.md 122 → 125, applicant guide, **new ADR** recording structured per-document review) +
   context.md § entry.

## Out of scope (explicit)

- Requesting a document **type** the applicant never submitted (extra credentials beyond the
  required set) — future backlog item if ever needed.
- Demographic field corrections post-submission.
- In-app notification feed for applicants; SAO-side notifications.
- Any change to the one-open-application rule (`OPEN_STATUSES`) — the response flow resolves the
  blockage from inside the open application.
