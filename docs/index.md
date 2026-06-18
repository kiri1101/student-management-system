# SchuLyf — Documentation

SchuLyf is a Student Management System that digitises a Cameroonian university's manual
admission, tuition-payment, and course-management processes. This is the documentation home.

> **Status:** scaffolded by the documentation initiative ([#68](https://github.com/)). Pages
> marked 🚧 are planned and land phase by phase (see `plan/documentation/plan.md`). ✅ = published.

## For developers

| Doc | What it covers | Status |
|---|---|---|
| [Architecture](architecture.md) | Request lifecycle, the Laravel 13 + Inertia v3 + Vue 3 + PrimeVue/Aura + Fortify stack, core patterns (Actions, ability gates, audit log, enum-as-string) | 🚧 |
| [Onboarding](onboarding.md) | Local setup (Laragon, MySQL, Redis), seeded credentials, the quality gate (Pint / Pest / vue-tsc / ESLint / Vite) | 🚧 |
| [Data model](data-model.md) | 24-model schema reference + ER diagram | 🚧 |
| [Routes & endpoints](routes.md) | Reference across the 8 route files, the `api/v1` lookups, and the public receipt-verify endpoint | 🚧 |
| [Security](security.md) | Fortify auth, ability gates, immutable audit log, HMAC receipts + public verify, file-viewer hardening | 🚧 |
| [Testing](testing.md) | Pest feature + `tests/Browser` smoke conventions | 🚧 |
| [Deployment & ops](deployment.md) | Laravel Cloud, queue worker, mail, Redis/predis cache runbook | 🚧 |

## Domain modules

| Module | Issue | Status |
|---|---|---|
| [Admissions](modules/admissions.md) — applicant funnel + SAO decisions | — | 🚧 |
| [Payments & receipts](modules/payments.md) — slip upload, validation, HMAC school receipts | #6 | 🚧 |
| [Exam gating](modules/exam-gating.md) — payment standing + tuition deferrals | #8 | 🚧 |
| [Course management](modules/course-management.md) — catalog, attendance, assignments, results, disputes | #11 | 🚧 |
| [Notifications](modules/notifications.md) — channel strategy via Laravel Notifications | #12 / #18 | 🚧 |
| [Admin user management](modules/admin-user-management.md) — invite-link users, role change, CSV import, audit | #30 | 🚧 |

## User guides (by role)

| Guide | For | Status |
|---|---|---|
| [Applicant](guides/applicant.md) | Prospective students applying for admission | 🚧 |
| [Student](guides/student.md) | Admitted students — payments, attendance, results | 🚧 |
| [SAO](guides/sao.md) | Student Affairs Officers — admissions, course plans, publishing results | 🚧 |
| [Accountant](guides/accountant.md) | Validating payments, issuing receipts, granting deferrals | 🚧 |
| [Lecturer](guides/lecturer.md) | Courses, attendance, assignments, results, absence notices | 🚧 |
| [Admin](guides/admin.md) | System configuration & user management | 🚧 |

## Decisions

- [Architecture Decision Records](adr/README.md) — the numbered record of locked design decisions.

---

*Maintained per the documentation initiative. When a feature changes, update the affected page
(the `docs-refresh` skill assists). The full scope and build plan live in `plan/documentation/plan.md`.*
