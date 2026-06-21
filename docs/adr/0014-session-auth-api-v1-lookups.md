# ADR-0014: Session-authenticated web routes + same-origin api/v1 JSON lookups (no token API)

- **Status:** Accepted
- **Date:** 2026-06-18
- **Deciders:** SchuLyf maintainers

## Context
This is an Inertia (Vue) app served from the Laravel backend — not a separate SPA talking to a public
API. A few client interactions still need JSON (cascading dropdowns, an audit-log modal), and there is
one genuinely public read (receipt verification). The app does **not** need a token-issuing API or a
second auth system; that would be surface area and complexity for no consumer.

## Decision
**All application routes are session-authenticated web routes** (the `web` guard). JSON lookups are
**same-origin `fetch()` targets, not a token API**:
- Cascading-dropdown lookups live under a versioned **`api/v1`** group inside `routes/web.php`
  (`routes/web.php:86`, `throttle:lookups`) — still session-authenticated.
- The audit-log modal endpoint is `admin/audit-logs` inside the `routes/admin.php` admin group.
- The **only** unauthenticated endpoint is the public **`receipts/verify/{number}`** verification
  (`routes/web.php:24`) — see [0017](0017-hmac-signed-receipts.md).

There is **no Sanctum/Passport token API**. New applicant-facing JSON endpoints follow the `api/v1`
convention.

## Consequences
- One auth model (session) across the whole app; no token issuance, rotation, or scopes to manage.
- JSON lookups inherit session auth + CSRF for free; they are not callable cross-origin.
- A future external/mobile client would need a deliberate token-API ADR — it is not a small addition.

See [`../routes.md`](../routes.md) and [`../security.md`](../security.md).
