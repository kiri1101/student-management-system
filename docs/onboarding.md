# Onboarding

Get SchuLyf running locally and learn the quality gate every change must pass before commit.
Targets a Windows + Laragon setup (the project's home), but the steps generalise.

> See also: [Architecture](architecture.md) for how the pieces fit · [Testing](testing.md)
> for test conventions · [Deployment & ops](deployment.md) for the production runbook.

## Prerequisites

| Tool | Version | Notes |
|---|---|---|
| PHP | 8.4 | Hard floor — composer requires `^8.4`. |
| Composer | 2.x | |
| Node.js | 20+ | npm ships with it. |
| MySQL | 8.x | Laragon bundles it; DB `student_management`, user `root`, **no password**. |
| Redis | optional | Only if you set `CACHE_STORE=redis`; `file` works with zero setup. |

## 1. Clone & install

```bash
git clone <repo-url> student-management-system
cd student-management-system

composer install
npm install
```

## 2. Environment

```bash
cp .env.example .env
php artisan key:generate
```

`.env.example` already carries the local defaults — no edits needed for a standard Laragon
setup. The keys that matter:

| Key | Default | Meaning |
|---|---|---|
| `APP_NAME` | `SchuLyf` | Drives the title and `VITE_APP_NAME`. |
| `DB_CONNECTION` / `DB_DATABASE` | `mysql` / `student_management` | Create this DB before migrating. |
| `DB_USERNAME` / `DB_PASSWORD` | `root` / *(blank)* | Laragon defaults. |
| `CACHE_STORE` | `file` | Reference-data cache (#39). Set `redis` + `REDIS_CLIENT=predis` to exercise the prod path locally. |
| `QUEUE_CONNECTION` | `database` | Queued mail/notifications need a worker (`composer run dev` runs one). |
| `MAIL_MAILER` | `log` | Outbound mail is written to `storage/logs/laravel.log`. Point at Mailtrap to actually see it (see [Deployment](deployment.md)). |
| `SESSION_SECURE_COOKIE` | `false` | Local dev is http. Set `true` in production. |

> The committed `.env.example` ships `CACHE_STORE=file` (per #39, so a fresh clone needs no
> Redis). Your local untracked `.env` may use `redis`/`predis` if you want to exercise that
> path — Laragon runs Redis on `:6379`.

## 3. Database

Create the database (Laragon → MySQL, or `mysql -uroot`), then build the schema and seed:

```bash
php artisan migrate:fresh --seed
```

This is the standard local reset for this project — migrations are edited in place rather
than via alter-migrations, so `migrate:fresh --seed` is the way to apply schema changes.

### Seeded login

`DatabaseSeeder` provisions a verified admin **only in local/testing** (production seeding
creates zero users — AUD-012):

| Field | Value |
|---|---|
| Email | `admin@example.com` |
| Password | `password` |
| Role | Admin |

Use it to browse admin-gated UI. `LocalStaffSeeder` adds further demo staff/students for the
other dashboards.

## 4. Run the dev environment

One command runs the PHP server, the queue worker, and Vite together:

```bash
composer run dev
```

This is `concurrently` over `php artisan serve`, `php artisan queue:listen --tries=1`, and
`npm run dev`. The queue worker matters — decision mail and notifications are queued, so
without a worker they never send. The app is at `http://127.0.0.1:8000` (or
`http://student-management-system.test` if served by Laragon/Herd).

Prefer separate terminals? Run them yourself:

```bash
php artisan serve
php artisan queue:listen --tries=1
npm run dev
```

## 5. The quality gate

Run **all five** before finalising any change — this mirrors `composer ci:check` plus the
frontend type/lint/build checks. PHP changes need Pint + Pest; frontend changes need vue-tsc
+ ESLint + a build.

| # | Command | Checks |
|---|---|---|
| 1 | `vendor/bin/pint --dirty --format agent` | PHP style on changed files (auto-fixes). |
| 2 | `php artisan test --compact` | Pest suite (filter with `--filter=Name`). |
| 3 | `npx vue-tsc --noEmit` | Vue/TS type-check (`npm run types:check`). |
| 4 | `npx eslint .` | JS/TS/Vue lint (`npm run lint:check`; `npm run lint` to auto-fix). |
| 5 | `npm run build` | Production Vite build — must succeed with **no** chunk-size warning. |

Notes:

- **Pint** — never run `--test`; just run it to auto-fix. Required after touching any PHP.
- **Vite chunk warning** — a `chunkSizeWarningLimit` warning means a bundle regression, not
  a limit to raise. The nav route-barrels and auth/settings layouts are deliberately
  code-split to keep the entry chunk under 500 kB; don't undo that.
- **PrimeVue imports** — import every PrimeVue component **per page**
  (`import Button from 'primevue/button'`); there are no global registrations beyond
  `ToastService` and the `tooltip` directive (AUD-020). A new global registration is a
  bundle regression.
- **Tests** run on in-memory SQLite with `CACHE_STORE=array` (pinned in `phpunit.xml`), so
  they need neither MySQL nor Redis. See [Testing](testing.md).

## Finding your way around

| You want to… | Look in |
|---|---|
| Understand a request's path | [Architecture](architecture.md) |
| Add a state change | `app/Actions/` — mirror `Accountant/ReviewPaymentAction.php` |
| Add/guard a route | `routes/*.php` (split by audience) — see [Routes](routes.md) |
| Add a gated ability | `AppServiceProvider::ABILITIES` — see [Security](security.md) |
| Add a status column | `app/Enums/` + `string` column (never native `ENUM`) |
| Add a page | `resources/js/pages/` + a Wayfinder route helper |
| Understand the schema | [Data model](data-model.md) |
