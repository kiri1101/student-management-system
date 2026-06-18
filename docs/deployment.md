# Deployment & ops

The production runbook for SchuLyf: the deploy target, the processes that must run, the mail
and cache stores, and the hardening checklist to work through before exposing the app.

> Source of truth: the production hardening baseline in `plan/context.md` §16 (AUD-034).
> Each item below names the audit finding it closes. See also
> [Onboarding](onboarding.md) for local setup and [Security](security.md) for the auth and
> audit posture.

## Target: Laravel Cloud

The app deploys on [Laravel Cloud](https://cloud.laravel.com/), the supported path for
shipping and scaling this Laravel application. A deploy needs, beyond the web instance:

| Component | Requirement |
|---|---|
| Web | PHP 8.4, the app instance serving Inertia pages over HTTPS. |
| Database | MySQL — single institution per install (no multi-tenancy). |
| Queue worker | **Required** — see below. |
| Scheduler | Cron entry for `schedule:run` — see below. |
| Cache | Redis (via `predis`) recommended in prod. |
| Mail | A real transactional SMTP mailer. |

## Processes that must run

### Queue worker (required, not optional)

```bash
php artisan queue:work   # under a supervisor / Laravel Cloud worker process
```

Mail and notifications are **queued** `ShouldQueue` listeners — there is no synchronous
send. Without a running worker, nobody is notified:

| Listener | Triggered by | Sends |
|---|---|---|
| `SendApplicationDecisionNotification` | application decided | decision mail (admit/reject/waitlist) — **AUD-002** |
| `SendPaymentReviewedNotification` | payment validated/rejected | student notification |
| `SendDeferralReviewedNotification` | deferral approved/rejected | student notification |
| `SendCourseSessionChangedNotification` | session cancelled/rescheduled | cohort email + in-app (database) notification |

The user-invitation mail (admin creates staff) is queued too. `QUEUE_CONNECTION=database`
is fine — just ensure the worker process exists.

### Scheduler

```cron
* * * * * php artisan schedule:run
```

`PruneAuditLogs` (`audit:prune`) runs **daily** to enforce the 2-year audit-log retention
horizon (**AUD-032**). It is a no-op without a scheduler, so the audit table grows unbounded
until the cron is wired.

## Cache: Redis via predis

| Environment | `CACHE_STORE` | `REDIS_CLIENT` |
|---|---|---|
| Local clone (default) | `file` | — (no Redis needed) |
| Local, exercising prod path | `redis` | `predis` (Laragon Redis on `:6379`) |
| Tests | `array` (pinned in `phpunit.xml`) | — |
| Production | `redis` | `predis` (pure-PHP — no PHP extension to install) |

The reference-data cache (`ReferenceDataCache`, #39) is store-agnostic and uses **no cache
tags**, so it behaves identically on `file`, `redis`, or `array`. `predis/predis` is a
composer dependency (chosen over phpredis to avoid the Windows DLL hassle). Behind a load
balancer, pointing `CACHE_STORE=redis` also makes the rate-limiter buckets global rather
than per-instance (**AUD-011**, **AUD-026**).

## Mail

- **Local dev** — `MAIL_MAILER=log` writes every message to `storage/logs/laravel.log`.
  Point at **Mailtrap** (set `MAIL_MAILER=smtp` + the Mailtrap host/port/credentials) to
  inspect rendered mail; the invite-flow QA (#37) was validated this way.
- **Production** — configure a real transactional mailer (`MAIL_MAILER` / `MAIL_HOST` /
  credentials). `log` silently swallows every outbound message (**AUD-034**).

## Production hardening checklist (§16 / AUD-034)

`.env.example` carries local-development defaults that are **not** safe in production. Work
through each item before exposing the app; each closes the named audit finding.

### Environment

- [ ] `APP_ENV=production`, `APP_DEBUG=false` — with debug on, an unhandled exception
  renders a stack trace that leaks schema, file paths, and env values (**AUD-003**).
- [ ] `APP_KEY` set to a real generated key (`php artisan key:generate`), never blank.
- [ ] `APP_URL` set to the canonical `https://` origin.

### Transport & session security

- [ ] Serve over HTTPS only; redirect plaintext to TLS at the edge.
- [ ] `SESSION_SECURE_COOKIE=true` — the session cookie is never sent over http.
- [ ] Consider `SESSION_ENCRYPT=true` and a locked-down `SESSION_DOMAIN`.

### Queue & scheduler

- [ ] Durable `queue:work` under a supervisor (mail/notifications never send otherwise —
  **AUD-002**).
- [ ] `schedule:run` wired in cron so `audit:prune` actually runs (**AUD-032**).

### Storage / filesystem

- [ ] Set `FILESYSTEM_DISK` deliberately. Uploads and downloads both resolve the **default**
  disk (**AUD-030**), so switching to `s3` is a single knob — but set it **before** any
  documents are stored, or historical downloads 404. For `s3`, set the `AWS_*` credentials
  and bucket.

### Database seeding

- [ ] Never seed the demo accounts in production. `DatabaseSeeder` and `LocalStaffSeeder`
  are gated to local/testing, so production `db:seed` creates zero users (**AUD-012**).
  Provision the first admin out-of-band.

### Mail & rate limiting

- [ ] Configure a real mailer (above) — `log` swallows everything (**AUD-034**).
- [ ] Rate limiting (auth flows, `api/v1`, downloads, audit-log modal) is already enforced
  in code (**AUD-011**, **AUD-026**). No env action needed, but behind a load balancer point
  the limiter at a shared store (`CACHE_STORE=redis`) so buckets are global.

**Acceptance:** every item names the finding it closes — AUD-003, AUD-002, AUD-032,
AUD-030, AUD-012, AUD-011, AUD-026.

## Health check

The app exposes a health endpoint at `/up` (configured in `bootstrap/app.php`) — point the
platform's health check at it.
