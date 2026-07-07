# ADR-0028: Transcript verification — snapshot-at-issue, not live re-derivation

- **Status:** Accepted
- **Date:** 2026-07-06
- **Deciders:** SchuLyf maintainers

## Context
School receipts ([ADR-0017](0017-hmac-signed-receipts.md)) are verified by re-deriving their HMAC
from *currently bound* identity — correct there, because a receipt's bound facts (matricule, amount,
year) never change after issuance. A transcript is different: it aggregates course results that **can
legitimately change after the document is printed** (a course published late, a dispute resolved, a
correction). Re-deriving a transcript's signature from live results would make a perfectly genuine
older transcript read as "invalid" the moment any later result changed. So the receipt pattern cannot
be applied verbatim.

## Decision
**Snapshot-at-issue.** Each generation writes an **immutable `transcripts` record** that stores the
exact rendered snapshot (JSON) plus an HMAC over `transcript_number | issued_at_iso | content_digest`,
keyed by `APP_KEY`. `content_digest` is a SHA-256 of the snapshot with its `meta` block removed. The
public, `throttle:lookups`-limited endpoint `GET transcripts/verify/{transcript_number}` looks the
record up and calls `Transcript::verifies()`, which recomputes the digest **from the stored snapshot**
(not live results) and `hash_equals()`-compares. A QR + printed URL on the PDF point at this endpoint.

- **No existence oracle.** The response payload is built only inside a `$valid ? [...] : null`
  branch, so an unknown number, a forged record, and a tampered snapshot all render `valid: false`
  with `transcript: null` — indistinguishable.
- **Content-digest dedupe.** Because `meta` (issue time + issuer role) is excluded from the digest,
  re-issuing the same academic content for the same student yields the same digest;
  `IssueTranscript` reuses the existing record (same number, no second audit) rather than minting a
  duplicate. Changed results produce a new digest and a new record.
- **Immutability.** The `Transcript` model throws on `updating`/`deleting` (like `SchoolReceipt` /
  `AuditLog`), so the signed snapshot cannot drift out from under a signature.

## Consequences
- A verified transcript reflects the results **as they were at issue**, which is the correct legal
  posture for a printed document — later corrections do not retroactively invalidate an old genuine
  transcript; the holder regenerates to get an up-to-date one (a new record).
- Storage grows by one row per distinct content generation per student (dedupe keeps re-downloads
  from multiplying rows).
- A benign race remains by design: two concurrent *first-time* generations of identical content can
  both mint, since the dedupe check runs outside the mint transaction. Accepted — re-downloads dedupe,
  so it is rare and harmless.
- Verification mirrors the receipt endpoint's UX and no-oracle guarantee, so the two public verify
  pages behave consistently.

## As-built vs. planned
Built as designed in [plan/transcripts/design.md](../../plan/transcripts/design.md) §2/§6. No drift.
Delivered on `feat/student-transcripts` (#71).
