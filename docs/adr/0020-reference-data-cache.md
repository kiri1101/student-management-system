# ADR-0020: Reference-data read-through cache (no cache tags)

- **Status:** Accepted
- **Date:** 2026-06-18
- **Deciders:** SchuLyf maintainers

## Context
Reference data — departments, program offerings, document types, and similar lookups — is read on
nearly every applicant-facing page and the cascading dropdowns, but changes rarely (admin edits only).
Re-querying it per request is wasteful. Cache **tags** would make invalidation elegant, but they require
a tag-capable store (Redis/Memcached) and would break the `array` cache store used in tests (#39).

## Decision
A **`ReferenceDataCache` service** wraps the lookups in a **read-through cache** (`Cache::remember()`)
over **four fixed keys**, `TTL = 86400` (24h), with an explicit `ALL_KEYS` list and a **tag-free
`flush()`** that loops `Cache::forget()` over those keys. The four admin reference controllers inject
the service and **flush after every write**. Being tag-free makes it **store-agnostic** — it works on
the `array` store under test.

## Consequences
- Hot reference reads are served from cache; the DB is hit on miss or after an admin edit.
- Invalidation is explicit and total (forget the known keys) rather than tag-scoped — simple and
  portable, at the cost of not invalidating a single sub-key.
- Any new reference-data writer **must** call `flush()`, or stale lookups will persist up to the TTL.
- Coverage is proven by `ReferenceDataCacheTest` (read-through + flush + controller invalidation).

See [`../architecture.md`](../architecture.md).
