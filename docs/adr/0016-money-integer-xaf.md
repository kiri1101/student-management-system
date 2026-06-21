# ADR-0016: Money stored as integer XAF

- **Status:** Accepted
- **Date:** 2026-06-18
- **Deciders:** SchuLyf maintainers

## Context
All amounts in the system are in Central African CFA francs (XAF), the institution's currency. XAF has
**no minor unit** — there are no centimes in circulation. Floating-point money invites rounding errors,
and a "store cents as integer" convention borrowed from other currencies would be wrong here (there are
no cents to store).

## Decision
**Money is stored as an `unsignedInteger` number of whole XAF**, cast to `integer` in the model. The
four money columns — `payment_submissions.amount_xaf`, `fee_installments.amount_xaf`,
`fee_schedules.total_xaf`, `school_receipts.amount_xaf` — are all integer; **no `decimal`/`float`** is
used for money anywhere. Validation is `integer|min:1`.

## Consequences
- Exact arithmetic; no floating-point drift in fee totals, standing calculations, or receipts.
- The `_xaf` suffix names the unit at every column, so there is no ambiguity about scale.
- The frontend formats with `Intl.NumberFormat(currency: 'XAF', maximumFractionDigits: 0)` passing the
  integer **directly** — **no `/100`**, because there is no minor unit. New money fields must keep this
  whole-unit convention.

See [`../modules/payments.md`](../modules/payments.md) and [`../modules/exam-gating.md`](../modules/exam-gating.md).
