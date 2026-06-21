# SchuLyf — Student Management System

SchuLyf digitises the manual student-administration processes of a Cameroonian university —
turning paper application folders, hand-carried bank receipts, and word-of-mouth class notices
into auditable online workflows.

It covers five problem areas drawn from real campus pain points:

- **Admissions** — applicants apply online and attach their credentials; Student Affairs
  Officers (SAO) triage and decide, and an admitted applicant becomes a student.
- **Payments & receipts** — a student uploads their bank-deposit slip, an accountant validates
  it, and the system issues a single **HMAC-signed school receipt** with a public verification
  endpoint — so a lost or forged paper receipt is no longer a source of conflict.
- **Exam gating & deferrals** — tuition is paid in dated installments; a student's
  **payment standing** is computed live and gates exam-hall access, with accountant-granted
  **deferrals** lifting the gate.
- **Course management** — course catalog & plans, session scheduling with lecturer-absence
  notifications, attendance, assignments, and CA/exam results with a student dispute flow.
- **Notifications** — transactional email and in-app notifications for admission decisions,
  payment outcomes, and course-session changes.

## Stack

Laravel 13 · PHP 8.4 · Inertia v3 · Vue 3 · TypeScript · Tailwind v4 · PrimeVue (Aura) ·
Laravel Fortify (session auth) · MySQL · Pest v4 · Laravel Pint. Money is stored as integer
XAF; authorization is role-based (Applicant · Student · SAO · Accountant · Lecturer · Admin)
with per-resource ownership checks and an immutable audit log.

## Quick start

Requires PHP 8.4, Composer 2, Node 20+, and MySQL 8 (Laragon bundles them all). Full detail —
including environment keys and the seeded login — is in **[docs/onboarding.md](docs/onboarding.md)**.

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed     # standard local reset — migrations are edited in place
composer run dev                     # PHP server + queue worker + Vite, together
```

The app runs at `http://127.0.0.1:8000`. Seeded admin (local/testing only):
`admin@example.com` / `password`.

## Quality gate

Every change must pass the gate before commit — see
[onboarding §5](docs/onboarding.md#5-the-quality-gate) for the details and caveats:

| Step | Command | When |
|---|---|---|
| PHP style | `vendor/bin/pint --dirty --format agent` | after any PHP change |
| Tests | `php artisan test --compact` | Pest (Unit · Feature · Browser) |
| Types | `npx vue-tsc --noEmit` | after a frontend change |
| Lint | `npx eslint .` | after a frontend change |
| Build | `npm run build` | must succeed with no chunk-size warning |

After a feature ships, run the **`docs-refresh`** skill to bring the affected `/docs` pages
back in sync — treat it as part of the gate.

## Documentation

Full developer and end-user documentation lives in **[`docs/`](docs/index.md)** (start at the
index), verified against the shipped code:

- **Developer** — [Architecture](docs/architecture.md) · [Onboarding](docs/onboarding.md) ·
  [Data model](docs/data-model.md) · [Routes](docs/routes.md) · [Security](docs/security.md) ·
  [Testing](docs/testing.md) · [Deployment & ops](docs/deployment.md)
- **Modules** — [Admissions](docs/modules/admissions.md) ·
  [Payments](docs/modules/payments.md) · [Exam gating](docs/modules/exam-gating.md) ·
  [Course management](docs/modules/course-management.md) ·
  [Notifications](docs/modules/notifications.md) ·
  [Admin user management](docs/modules/admin-user-management.md)
- **User guides** — [Applicant](docs/guides/applicant.md) · [Student](docs/guides/student.md) ·
  [SAO](docs/guides/sao.md) · [Accountant](docs/guides/accountant.md) ·
  [Lecturer](docs/guides/lecturer.md) · [Admin](docs/guides/admin.md)
- **Decisions** — [22 Architecture Decision Records](docs/adr/README.md)

## Project layout

```
app/            Models · Actions (state changes) · Services · Http · Enums · Mail/Events/Listeners
routes/         web.php · settings.php · admin.php · sao.php — session-auth, split by audience
resources/js/   Inertia pages · components · layouts (Vue 3 + PrimeVue/Aura)
database/       migrations (edited in place) · factories · seeders
docs/           the documentation tree linked above
plan/           design & planning logs (context.md, per-feature plans)
tests/          Pest — Unit · Feature · Browser
```

## Contributing

[`CLAUDE.md`](CLAUDE.md) is the authoritative convention guide for both humans and AI
assistants working in this repo. The design history and per-feature plans live under `plan/`.
