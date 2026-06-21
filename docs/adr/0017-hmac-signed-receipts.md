# ADR-0017: HMAC-signed school receipts with public verification

- **Status:** Accepted
- **Date:** 2026-06-18
- **Deciders:** SchuLyf maintainers

## Context
The paper school receipt is the institution's single proof of payment, and the original manual process
let it be forged or reused — the core problem this system was built to solve. A digital receipt is only
trustworthy if anyone can verify it is genuine and unaltered, without logging in or trusting the bearer.

## Decision
Each `school_receipt` is **HMAC-signed**: `hash_hmac('sha256', payload, config('app.key'))`
(`app/Models/SchoolReceipt.php:62-69`) over the **canonical payload
`receipt_number|matricule|amount_xaf|academic_year`**. The model is **immutable** (blocks update/delete,
`:33-39`). A **public, unauthenticated** `VerifyReceiptController` (`receipts/verify/{number}`)
recomputes the signature and compares with **`hash_equals()`** (constant-time, `:90`); it reveals the
bound identity **only when the signature is valid**. An unknown receipt number and a bad signature both
read simply **"invalid"**.

## Consequences
- A receipt cannot be altered (any field change breaks the HMAC) or fabricated without `app.key`.
- Verification is public and self-contained — no login, no trust in the person presenting it.
- **`app.key` is now receipt-signing key material**: rotating it invalidates every existing receipt's
  signature. Treat key rotation as a receipt-reissue event.
- Constant-time comparison avoids signature-timing oracles; the invalid response is uniform, leaking
  nothing about why.

See [`../modules/payments.md`](../modules/payments.md) and [`../routes.md`](../routes.md).
