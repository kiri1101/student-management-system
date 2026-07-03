# Planning Session Context — Student Management System

**Session dates:** 2026-04-30 → 2026-05-01
**Working mode:** Discussion only (no implementation). Implementation begins in a follow-up session, starting at Phase 1.
**Project:** `D:\laragon\www\student-management-system`
**Stack at planning time:** Laravel 13 + PHP 8.4 + Inertia v3 + Vue 3 + Fortify v1 + Wayfinder v0 + Pest v4 + Tailwind v4 + MySQL (`student_management`).

---

## 1. Why this document exists

This file captures the full context of the planning session that produced the implementation plan: the problem, the decisions taken, the conventions established, the schema designed, and the 10-phase implementation roadmap. It is the project-side companion to the personal plan file at `C:\Users\jtuku\.claude\plans\hi-claude-lets-discuss-compressed-bubble.md`. Keep them in sync if you edit either.

The system being built is described in the project root `CLAUDE.md`: a Student Management System addressing real pain points the user observed in a Cameroonian university — manual admissions, fragile payment validation, tuition deferrals, course management, lecturer absence rumors, results disputes.

---

## 2. State of the codebase at planning time (verified 2026-04-30)

A fresh Laravel starter kit with auth scaffolding only. **No domain code exists yet.**

- **Models**: only `App\Models\User`.
- **Migrations**: `users`, `cache`, `jobs`, plus the Fortify two-factor columns. No domain tables.
- **Routes** (`routes/web.php`): `/` → Welcome, `/dashboard` → placeholder (auth + verified middleware). Settings routes via `routes/settings.php`. No `routes/api.php` yet.
- **Pages** (`resources/js/pages`): Welcome, Dashboard, auth flows (Login, Register, ForgotPassword, ResetPassword, ConfirmPassword, TwoFactorChallenge, VerifyEmail), profile/security settings.
- **Frontend tooling**: Tailwind v4 via `@tailwindcss/vite`; shadcn-vue primitives already installed (`reka-ui`, `class-variance-authority`, `clsx`, `tailwind-merge`, `tw-animate-css`, `lucide-vue-next`); Inertia v3 with `@inertiajs/vite`; Wayfinder; ESLint/Prettier configured.

---

## 3. Problem domains identified

From the project `CLAUDE.md`, the system needs to address (in rough natural-dependency order):

1. **Admissions** — online application, document uploads, decision workflow, automated notifications. Replaces a paper-folder process.
2. **Payment validation** — link bank receipts to tamper-proof school receipts, verifiable lookup.
3. **Tuition deferrals** — installment thresholds, request/approval flow, gating access to facilities/exams.
4. **Course management** — planning, attendance, assignments, CA + exam results, disputes.
5. **Lecturer absence notifications** — push to enrolled students directly, bypass the rumor chain.
6. **Receipt verification** — staff/public scan or lookup to validate receipts.

The session focused exclusively on **Foundations** (users + roles) and **Admissions** (the application form + decision flow), as the user requested.

---

## 4. Decisions taken in the session

### 4.1 Scope decisions

- **First focus area**: Foundations (users & roles). Everything else depends on this.
- **Working mode**: discussion only this session; capture decisions in this file; implementation in a later session.

### 4.2 Role list (FINAL — 6 roles)

| Role | Scope |
|---|---|
| **Applicant** | Pre-admission; submits application; tracks status |
| **Student** | Admitted; enrolled; pays tuition; sits exams |
| **Lecturer** | Teaches courses; marks attendance/results; notifies absences |
| **Accountant** | Validates payments; issues school receipts; grants deferrals |
| **Student Affairs Officer (SAO)** | Processes admissions; oversees lecturers; approves course plans; publishes CA & exam results |
| **System Admin** | System-level configuration & user management |

> **Note on SAO scope (project-specific):** The user defined SAO with **academic oversight authority** — not just student services. SAO handles admissions decisions, oversees lecturers, approves course plans, and publishes results. This subsumes what would otherwise be a Department Head or Examination Officer role. Revisit only if departmental autonomy becomes a concrete requirement later.

### 4.3 Authorization model

- **Multi-role**: a single user can hold multiple roles simultaneously via a `role_user` pivot. Authorization should never assume "the role" — always "any of the user's roles".
- **Mechanism**: custom `Role` model + `role_user` pivot, enforced through Laravel's built-in `Gate` facade and `Policy` classes. **No third-party permission package.** `User` gets `hasRole(RoleName)`, `hasAnyRole(array)`, `assignRole(RoleName)`, `removeRole(RoleName)` helpers. Gates defined in `AuthServiceProvider` map ability names (e.g., `publish-results`, `validate-payment`, `approve-course-plan`) onto role checks. Policies for per-resource rules (e.g., a Lecturer can only mark attendance for their own courses). Revisit Spatie only if/when permissions fragment beyond what the 6 roles can express.

### 4.4 Data shape

- **Profile structure**: separate profile models per role — `User hasOne StudentProfile`, `hasOne LecturerProfile`, `hasOne AccountantProfile`, `hasOne SaoProfile`. Each profile is its own narrow table keyed by `user_id`. A user with multiple roles has multiple profile rows. Adding a role-specific field is a migration on one narrow table.
- **No `applicant_profile`**: the `applications` table itself carries application data (first/last name, contact details, program choice, documents).
- **Admin role does not need a profile table** — admin accounts carry no role-specific attributes.

### 4.5 Applicant lifecycle

- Applying **creates a User account** with the Applicant role. Applicants can log in to track status, upload missing documents, and receive in-app notifications.
- On admission, the Student role is added (Applicant role may be retained for audit) and a `StudentProfile` row is created in the same transaction.
- An Applicant can have many Applications (different cycles, different program choices).

### 4.6 Login identifier strategy

- Login is accepted via **email OR matricule (Students) OR employee_id (staff)**.
- Applicants register with email + password only — no matricule yet (matricules are issued on admission).
- Lecturer / Accountant / SAO / Admin accounts are created by an admin and an `employee_id` is assigned at creation; both email and employee_id work as logins from day one.
- Implementation note: requires customizing Fortify's username resolver (`Fortify::authenticateUsing()`) to accept any of the three identifiers. Stored matricules and employee IDs must be globally unique across users.

### 4.7 Email verification

- **Required for all users at registration.** Standard Fortify `MustVerifyEmail` behavior on `User`. An Applicant cannot submit/review an application until their email is verified — this filters typos and gives a reliable notification channel from day one.

### 4.8 Tenancy

- **Single institution per install.** No `institution_id` on domain tables. Second-university scenarios are served by a fresh deployment, not by sharing the database. If multi-tenant ever becomes a real requirement, it's a redesign, not a config flip — that trade-off is accepted.

### 4.9 Application domain

- **`DegreeProgram` is a fixed PHP enum** (`App\Enums\DegreeProgram`: `Hnd`, `Bachelors`, `Masters`). No `degree_programs` table. Adding a 4th program is a code change.
- **Departments are independent rows.** Many-to-many with degree programs via a `program_offerings` pivot that carries the level range for each `(department, program)` pair.
- **Levels are configured per `program_offering`** as `min_level` / `max_level`. Admin-editable. Default seeded ranges: HND `1–2`, Bachelors `1–4`, Masters `1–2`, but each offering can override.
- **Per-level required credentials** live in their own table — for each `(program_offering, level)` we list which `document_types` must be uploaded. This drives "level 3 Bachelor requires HND diploma; level 1 Bachelor requires GCE A-Level / Baccalauréat".
- **`document_types` is admin-editable** so new credential types can be added at runtime.
- **Application stores its own snapshot** of `first_name`, `last_name`, `contact_email`, `phone`, `date_of_birth`, `previous_institute` — distinct from the User's login `email` and display `name`.

### 4.10 Audit log scope

- **Significant writes only**: Eloquent `created` / `updated` / `deleted` / `restored` events on domain models, plus explicit calls for non-Eloquent events (login success/fail, role assigned/revoked, application decision, payment validated). No read logging.
- **Audit log is immutable**: never updated, never deleted (not even softly).
- **Display**: paginated, filterable in a modal on the Admin dashboard.

### 4.11 UI library

- **PrimeVue + Aura preset** for all new UI components (inputs, modals, buttons, dropdowns, tables, file uploads).
- The Laravel starter installed shadcn-vue primitives (reka-ui). Existing auth + settings pages keep using shadcn-vue; PrimeVue is the choice for **new** work. No wholesale migration.
- Reference docs: https://primevue.org/llms/llms.txt (index), https://primevue.org/llms/llms-full.txt (full docs).

---

## 5. Project conventions established (apply to every migration / model / page)

These are the ground rules every implementation phase must follow.

1. **No native ENUM columns.** Any column with a fixed set of allowed values is declared as `string` in the migration and cast to a backed PHP Enum class on the model (`app/Enums/...`). The Enum is the source of truth; validation uses `Rule::enum(...)`. *Why:* DB-level ENUMs are painful to evolve and duplicate the value list between schema and code.

   ```php
   // Migration
   $table->string('status')->index();

   // app/Enums/RoleName.php
   enum RoleName: string {
       case Applicant = 'applicant';
       case Student   = 'student';
       // ...
   }

   // Model
   protected function casts(): array {
       return ['name' => RoleName::class];
   }
   ```

2. **Soft deletes everywhere except audit logs.** Every domain table declares `$table->softDeletes()` and the model uses the `SoftDeletes` trait. The lone exception is `audit_logs` — audit records are immutable and never deleted (not even softly).

3. **`restrictOnDelete` for all foreign keys to tables with real data.** No cascade deletes on domain tables. If a parent has children, deletion is blocked at the DB level. Combined with soft deletes, "delete" in the app is always a soft-delete; `restrictOnDelete` only fires on a hard delete (a DBA action).

4. **PrimeVue + Aura for all new UI components.** New inputs, buttons, modals, dropdowns, tables, file uploads, etc. use PrimeVue components with the Aura theme preset. Existing shadcn-vue pages stay untouched unless substantially modified.

5. **Phased implementation with per-phase audit + commit.** Multi-step work is split into discrete phases. Each phase ends with an audit pass (cleanup, scope review, optimize, pint, tests, route audit) and a focused git commit before the next phase begins.

---

## 6. Schema implied by these decisions

All tables below have `softDeletes()` and `timestamps()` unless explicitly noted. All foreign keys use `restrictOnDelete()` unless explicitly noted.

### 6.1 Identity & roles

- **`users`** — id, name, email (unique), password, email_verified_at, employee_id (nullable, unique — for staff), two_factor_*, timestamps, soft deletes. No role column.
- **`roles`** — id, `name` (string, unique, indexed) cast to `App\Enums\RoleName`. Cases: `Applicant`, `Student`, `Lecturer`, `Accountant`, `Sao`, `Admin`. Seeded once.
- **`role_user`** — user_id, role_id, timestamps. **No soft deletes** (pivot).

### 6.2 Per-role profiles

- **`student_profiles`** — user_id (unique FK), matricule (unique), program_offering_id (FK), level, academic_year, enrolled_at, `status` (string + `StudentStatus` enum: `Active`, `Suspended`, `Graduated`, `Withdrawn`).
- **`lecturer_profiles`** — user_id (unique FK), department_id (FK), specialization, hired_at.
- **`accountant_profiles`** — user_id (unique FK), bank_desk / cashier_window.
- **`sao_profiles`** — user_id (unique FK), scope/department (if needed later).

### 6.3 Reference / lookup tables (admin-editable)

- **`departments`** — id, name (unique), code (unique), description (nullable).
- **`program_offerings`** — id, department_id (FK), `degree_program` (string + `DegreeProgram` enum cast — no FK since `DegreeProgram` is enum-only), min_level (uint), max_level (uint). Unique `(department_id, degree_program)`.
- **`document_types`** — id, name (unique), code (unique), description (nullable). Seeded with: National Identity, Birth Certificate, GCE A-Level / Baccalauréat, HND Diploma, Bachelors Degree, Masters Degree (admin can add more).
- **`level_credential_requirements`** — id, program_offering_id (FK), level (uint), document_type_id (FK), required (bool, default true), notes (nullable). Unique `(program_offering_id, level, document_type_id)`. **The rules engine** the application form uses to decide which "latest degree" upload slot to render.

### 6.4 Applications

- **`applications`** — id, user_id (FK, the Applicant), program_offering_id (FK), level (uint), first_name, last_name, contact_email, phone, date_of_birth, previous_institute, `status` (string + `ApplicationStatus` enum: `Draft`, `Submitted`, `UnderReview`, `DocumentsRequested`, `Admitted`, `Rejected`, `Waitlisted`, `Withdrawn`), submitted_at (nullable), decided_at (nullable), decided_by_user_id (FK, nullable — an SAO), decision_notes (nullable). `level` validated against the offering's `min_level..max_level` at write time.
- **`application_documents`** — id, application_id (FK), document_type_id (FK), file_path, original_filename, mime_type, size_bytes, uploaded_at. National Identity and Birth Certificate are always required; the "latest degree" slot is derived from `level_credential_requirements` for the chosen `(offering, level)`.

### 6.5 Audit log

- **`audit_logs`** — id, user_id (FK, nullable for system actions), `action` (string + `AuditAction` enum cast: `Created`, `Updated`, `Deleted`, `Restored`, `StatusChanged`, `RoleAssigned`, `RoleRevoked`, `LoggedIn`, `LoginFailed`, `LoggedOut`, `ApplicationDecided`, `PaymentValidated`, ...), subject_type + subject_id (polymorphic morphs, nullable for non-Eloquent events), `changes` (JSON: before/after diff for `Updated`; created/deleted snapshot otherwise), `context` (JSON: ip, user_agent, route name), occurred_at (indexed). **No `softDeletes()`** — audit records are immutable. Indexes on `user_id`, `(subject_type, subject_id)`, `action`, `occurred_at`.

---

## 7. Routes & pages (Inertia + Vue)

Login redirect by role priority: `Admin > Sao > Accountant > Lecturer > Student > Applicant`. Customise Fortify's `LoginResponse` to inspect the user's roles and route to the corresponding dashboard. (For multi-role users, a role switcher in the header can be added later.)

- **Applicant**
  - `GET /applicant/dashboard` — list of the Applicant's applications with status badges + a CTA "New Application" button.
  - `GET /application/new` — application form. Cascading dropdowns:
    1. Degree Program (enum cases) → enables Department dropdown.
    2. Department (filtered by `program_offerings` rows for that program) → enables Level dropdown.
    3. Level (constrained to that `program_offering`'s `min_level..max_level`) → triggers the "latest degree" required-document slot to render based on `level_credential_requirements`.
  - Always-required uploads: National Identity, Birth Certificate.
  - `POST /application` — submit (validation: level within offering range; required document types present; mime/size limits; email verified).
  - `GET /application/{application}` — view submitted application + status timeline.
- **Admin**
  - `GET /admin/dashboard` — overview with a button/link that opens an **Audit Log modal** (paginated, filterable by actor, action, subject type, date range). Plus CRUD pages for `departments`, `program_offerings`, `document_types`, `level_credential_requirements`.
- **Other role dashboards** are stubs initially — built out in later iterations.

---

## 8. Implementation Plan — 10 Phases

Each phase ends with an audit pass and a git commit before the next phase starts.

### Per-phase audit checklist (run at the end of every phase)

1. Remove unused variables, functions, routes, imports, and dead code introduced in this phase.
2. Re-review scope — did anything beyond the phase requirements creep in? Remove it.
3. Optimize — N+1 queries, missing indexes, missing eager loads, redundant DB calls.
4. Run `vendor/bin/pint --dirty --format agent` until clean.
5. Run `php artisan test --compact` — all green, including the new tests for this phase.
6. Run `php artisan route:list --except-vendor` — no orphan routes.
7. `git commit` with a message describing the phase's deliverable. Do not start the next phase until this commit exists.

---

### Phase 1 — Roles & Authorization Foundation
**Goal:** users can carry multiple roles; gates resolve correctly.

**Deliverables**
- `App\Enums\RoleName` (Applicant, Student, Lecturer, Accountant, Sao, Admin).
- Migration: `users` adds `employee_id` (nullable, unique) + `softDeletes()`; `roles` (string `name` cast to `RoleName`); `role_user` pivot.
- `Role` model; `User` gets `hasRole(RoleName)`, `hasAnyRole(array)`, `assignRole`, `removeRole`.
- `RolesSeeder` iterating `RoleName::cases()`.
- Gates in `AuthServiceProvider` for top-level abilities: `process-admission`, `decide-application`, `validate-payment`, `publish-results`, `approve-course-plan`, `mark-attendance`, `view-audit-log`. Initially mapped to role checks.

**Tests (Pest, feature)**
- `assignRole` / `removeRole` round-trip.
- `hasRole` / `hasAnyRole` resolve correctly for single- and multi-role users.
- Each gate returns true only for the intended role(s).

**Commit:** `feat(auth): introduce role model with multi-role support and top-level gates`

---

### Phase 2 — UI Toolkit: PrimeVue + Aura
**Goal:** PrimeVue is wired into the Inertia + Vue 3 + Tailwind v4 stack with the Aura theme; a smoke component renders correctly in light + dark mode; all subsequent UI phases consume PrimeVue without further setup.

**Deliverables**
- `npm install primevue @primeuix/themes` and `npm install -D tailwindcss-primeui`.
- `resources/css/app.css` — add `@import 'tailwindcss-primeui';` directly after `@import 'tailwindcss';`. Confirm CSS layer ordering remains correct (`theme, base, primevue, utilities`).
- `resources/js/app.ts` — restructure `createInertiaApp` to include a `setup({ el, App, props, plugin })` callback that creates the Vue app instance, calls `.use(plugin)` for Inertia, then `.use(PrimeVue, { theme: { preset: Aura, options: { darkModeSelector: '.dark', cssLayer: { name: 'primevue', order: 'theme, base, primevue, utilities' } } } })`, then `.mount(el)`. (The current app.ts has no `setup` — it is added in this phase.)
- Register a small set of components globally for ergonomics: `Button`, `InputText`, `Select`, `FileUpload`, `Dialog`, `DataTable`, `Column`, `Toast`. Other components are imported per-page.
- Add a `<Toast />` placement in `AppLayout.vue` so any page can use the `useToast()` composable.
- Smoke test: convert a single existing element on `Dashboard.vue` to a PrimeVue `<Button>` to confirm theming works in both light and dark mode.
- Document the integration in the project `CLAUDE.md` under a "UI components" heading.

**Tests**
- Pest browser (Pest 4) smoke test: visit `/`, assert page renders without console errors; visit `/dashboard` (auth-required), assert the PrimeVue Button is present and clickable.
- Manual: toggle dark mode, confirm PrimeVue surfaces respond.

**Audit additions**
- `npm run build` succeeds; `npm run dev` succeeds with no Vite errors.
- No CSS specificity wars: Tailwind utilities still win over PrimeVue defaults where expected.
- `npm run lint:check` and `npm run types:check` clean.

**Commit:** `feat(ui): integrate primevue with aura theme and tailwindcss-primeui`

---

### Phase 3 — Identifier-Flexible Login + Role-Based Redirect
**Goal:** users log in with email, matricule, or employee_id; landing page is their role's dashboard.

**Deliverables**
- Customise Fortify's `Fortify::authenticateUsing()` (or username resolver) so any of `email | users.employee_id | student_profiles.matricule` resolves to a user.
  *(Note: `student_profiles.matricule` doesn't exist yet — for this phase, only email + employee_id are wired; matricule is added in Phase 6 when StudentProfile lands. The resolver is structured so adding matricule later is one line.)*
- Custom `LoginResponse` redirects by priority: `Admin > Sao > Accountant > Lecturer > Student > Applicant`.
- Stub dashboard routes/pages for each role (`/admin/dashboard`, `/sao/dashboard`, etc.) — Inertia pages with placeholder content.

**Tests**
- Login by email succeeds.
- Login by employee_id (for staff) succeeds.
- Login by an unknown identifier returns 422 with the standard Fortify error.
- After login, each role lands on its own dashboard route.
- Multi-role user lands on highest-priority dashboard.

**Commit:** `feat(auth): support email/employee_id login and role-priority redirect`

---

### Phase 4 — Reference Tables + Admin CRUD
**Goal:** admin can manage the lookup data the application form depends on.

**Deliverables**
- `App\Enums\DegreeProgram` (Hnd, Bachelors, Masters).
- Migrations: `departments`, `program_offerings` (with `degree_program` string + enum cast, unique `(department_id, degree_program)`), `document_types`, `level_credential_requirements` (unique `(program_offering_id, level, document_type_id)`). All with `softDeletes()` + `restrictOnDelete()` FKs.
- Models with relationships and casts.
- Seeders: default `document_types`; one demo department + offerings for dev.
- Inertia admin pages + controllers + Form Requests for CRUD on each table. Wayfinder routes. **UI: PrimeVue `DataTable` with row actions, `Dialog` for edit/create, `InputText` / `Select` / `InputNumber` for fields.**
- Gate `manage-references` (admin-only).

**Tests**
- CRUD endpoints reject non-admin.
- `program_offerings` enforces unique `(department, degree_program)`.
- `level_credential_requirements` enforces level within offering's `min_level..max_level`.
- `restrictOnDelete` blocks hard-delete when children exist.

**Commit:** `feat(admin): add reference tables and admin CRUD for departments, offerings, document types, level requirements`

---

### Phase 5 — Audit Log Infrastructure
**Goal:** every significant write is recorded immutably; auth events are recorded; nothing can be edited or deleted.

**Deliverables**
- `App\Enums\AuditAction`.
- Migration: `audit_logs` (no `softDeletes()`). Indexed.
- `AuditLog` model with no `update()` / no soft deletes; throws on attempt to mutate.
- `RecordsAudit` Eloquent trait (used by domain models) — hooks into `created`, `updated`, `deleted`, `restored`. Captures diff via `getOriginal()` vs `getDirty()`.
- `AuditLog::record(action, subject, changes, context)` helper for non-Eloquent events.
- Auth event listeners: `Login`, `Failed`, `Logout`. Records ip + user agent from current request.
- Gate `view-audit-log` (admin only).

**Tests**
- Creating/updating/deleting/restoring a model with `RecordsAudit` writes the right `audit_log` row with correct diff.
- Auth events write logs.
- Attempting to update or delete an `AuditLog` throws.
- Non-admin cannot read `audit_logs`.

**Commit:** `feat(audit): immutable audit log with eloquent trait and auth event listeners`

---

### Phase 6 — Per-Role Profile Models
**Goal:** role-specific data lives in dedicated tables; matricule becomes a valid login identifier.

**Deliverables**
- `App\Enums\StudentStatus`.
- Migrations: `student_profiles` (matricule unique), `lecturer_profiles`, `accountant_profiles`, `sao_profiles`. All with `softDeletes()` + `restrictOnDelete()` FKs to `users`.
- Models with `belongsTo User`, casts, and `RecordsAudit` trait.
- `User` gets `studentProfile`, `lecturerProfile`, `accountantProfile`, `saoProfile` `hasOne` relationships.
- Extend Phase 3's username resolver to also accept `student_profiles.matricule`.

**Tests**
- Creating each profile auto-writes an audit log entry.
- `User->studentProfile` returns the profile when present, null otherwise.
- Login by matricule resolves to the right user.
- Hard-deleting a user with profiles is blocked.

**Commit:** `feat(profiles): per-role profile models with matricule login support`

---

### Phase 7 — Application Domain Models
**Goal:** the data layer for applications is in place, with validation against the reference tables and audit recording.

**Deliverables**
- `App\Enums\ApplicationStatus` (Draft, Submitted, UnderReview, DocumentsRequested, Admitted, Rejected, Waitlisted, Withdrawn).
- Migrations: `applications`, `application_documents`. `softDeletes()` + `restrictOnDelete()`.
- Models with `RecordsAudit` trait (from Phase 5) and relationships.
- `StoreApplicationRequest` Form Request: validates `level` within the chosen offering's `min_level..max_level`; validates that all `required` `document_types` from `level_credential_requirements` are present in the upload set; mime/size limits per file.
- Storage strategy: Laravel default disk for now (revisit at deployment).

**Tests**
- Submitting a valid application persists rows and writes audit logs.
- Level outside offering range → 422.
- Missing a required document → 422 (validation message names which document_type is missing).
- Soft-deleting an application keeps audit history queryable.

**Commit:** `feat(applications): application domain models with credential-aware validation`

---

### Phase 8 — Applicant Dashboard + Application Form (UI) — ✅ shipped (`8c55067`)
**Goal:** Applicants can complete and submit applications end-to-end through the UI.

**Deliverables**
- Inertia pages: `Applicant/Dashboard.vue`, `Application/New.vue`, `Application/Show.vue`. **All built with PrimeVue components.**
- Applicant dashboard: `DataTable` of the user's applications (status badge using `Tag`), CTA `Button` linking to `/application/new`.
- Application form on `/application/new`: PrimeVue `Select` for Degree Program / Department / Level, `InputText` / `InputMask` / `DatePicker` for personal fields, `FileUpload` for each required document slot, `Button` to submit. `useForm` from `@inertiajs/vue3` for state.
- Cascading dropdown endpoints: `GET /api/v1/program-offerings?degree_program=...` returns the filtered list; `GET /api/v1/level-requirements?offering=...&level=...` returns the required document types. The form re-fetches when its parent select changes.
- Form behaviours:
  - Degree Program select → enables Department.
  - Department select → enables Level (constrained to offering range).
  - Level select → "latest degree" upload slot renders the correct required document type.
  - National Identity + Birth Certificate slots are always shown.
- Wayfinder-generated route helpers.
- Toast notifications on submit success/failure via the `<Toast />` placed in `AppLayout` (from Phase 2).

**Tests**
- Pest feature tests: form submission round-trip; dashboard lists the user's applications with statuses.
- Pest browser test: complete the form happy-path, observe correct cascade behaviour, submit, see success state.

**Commit:** `feat(applicant): dashboard and cascading application form`

---

### Phase 9 — SAO Decision Flow + Admit-to-Student Promotion
**Goal:** SAO can review applications and decide; an admit decision promotes the User and creates a StudentProfile.

**Deliverables**
- SAO dashboard: paginated list of `Submitted` / `UnderReview` applications using PrimeVue `DataTable` with built-in filters/sort.
- Application review page: applicant info, downloadable documents (`Button` with download icon), decision form (PrimeVue `Select` for status, `Textarea` for notes, `Button` to submit).
- `DecideApplicationAction` (or service) — wraps in a transaction:
  - On `Admitted`: assigns Student role, creates `StudentProfile` with a generated matricule, records audit `ApplicationDecided` + `RoleAssigned`.
  - On other decisions: status update + audit only.
- Email/in-app notification stubs (channel selection deferred).

**Tests**
- Only SAO can decide.
- Admit promotes user and creates profile in one transaction.
- Rejecting writes the audit entry without creating a profile.
- Decisions are idempotent / cannot be re-decided once final (state-machine guard).

**Commit:** `feat(sao): application decision flow with admit-to-student promotion`

---

### Phase 10 — Admin Dashboard + Audit Log Modal
**Goal:** Admin gets a single dashboard with the audit log modal and reference-table shortcuts.

**Deliverables**
- `Admin/Dashboard.vue` with summary tiles (counts: users by role, applications by status, recent admissions) — built with PrimeVue `Card` components.
- Audit Log modal: PrimeVue `Dialog` containing a `DataTable` with lazy/server-side pagination, column filters (actor, action, subject type, date range via `DatePicker`). Triggered from a dashboard `Button`.
- Links to Phase 4's reference-table CRUD pages.
- Final cleanup pass: remove placeholder dashboards' stub content for any role that now has a real implementation; consolidate duplicated UI helpers.

**Tests**
- Modal endpoint returns paginated rows with filters applied.
- Non-admin gets 403 when hitting the audit log endpoint or modal route.
- Pest browser test: open dashboard, open modal, filter by action, see expected entries.

**Commit:** `feat(admin): dashboard with audit log modal viewer`

---

## 9. Cross-phase verification (after Phase 10)

End-to-end smoke (manual + automated):

- `php artisan migrate:fresh --seed` builds the whole schema cleanly.
- Full suite: `php artisan test --compact` green.
- Manual happy path:
  1. Register an Applicant → verify email → log in → land on `/applicant/dashboard`.
  2. Complete `/application/new` with cascading dropdowns; submit; see status `Submitted` on dashboard.
  3. Log in as SAO → review → admit. Applicant gains Student role + matricule.
  4. Applicant logs back in (via matricule this time) → role-priority redirect now sends to `/student/dashboard`.
  5. Log in as Admin → open Audit Log modal → see every event from steps 1–4 recorded.
- Negative checks: tampering level outside offering range → 422; deleting a department with applications → blocked; soft-deleted application still appears in audit log.
- `vendor/bin/pint --dirty --format agent` clean across the whole repo.

---

## 10. Out-of-scope (intentionally deferred)

These were mentioned in the original `CLAUDE.md` but were not designed in this session. They are downstream of the Foundations + Admissions work and will be addressed in later planning sessions:

- ~~Payment validation + tamper-proof school receipts (signed QR, verification endpoint).~~ ✅ SHIPPED — #6/B1, PR #56 (issue #6 closed 2026-06-15; see §17).
- ~~Tuition deferral request/approval flow + facility/exam access gating.~~ ✅ SHIPPED — #8/B2, PR #57 (see §17).
- ~~Course management (planning, attendance, assignments, results, disputes).~~ ✅ SHIPPED — #11/B3, PR #58 (see §17).
- ~~Lecturer absence notifications (push channel selection).~~ ✅ SHIPPED — #12/B4, PR #59 (closed #12; see §17).
- Receipt verification (public/staff lookup).
- ~~Notification channels (email vs in-app vs SMS — only stubs in Phase 9).~~ ✅ DECIDED — #18/B6 closed 2026-06-15 (email = transactional, in-app = broadcasts via Laravel Notifications, SMS deferred; see §17).
- Multi-role user role-switcher UI (default redirect by priority is enough for now).
- Migration of existing shadcn-vue auth/settings pages to PrimeVue (only when those pages are otherwise being modified).

---

## 11. Reference links

- PrimeVue navigation index: https://primevue.org/llms/llms.txt
- PrimeVue full docs: https://primevue.org/llms/llms-full.txt
- PrimeVue Vite setup: https://primevue.org/vite
- PrimeVue Tailwind integration: https://primevue.org/tailwind
- PrimeVue Laravel integration: https://primevue.org/laravel

---

## 12. Companion artifacts

- Personal plan file: `C:\Users\jtuku\.claude\plans\hi-claude-lets-discuss-compressed-bubble.md`
- Memory entries:
  - `project_role_student_affairs_officer.md` — SAO scope (academic oversight)
  - `project_ui_library_primevue.md` — PrimeVue + Aura choice
  - `feedback_enum_columns.md` — string columns + PHP enum casts convention
  - `feedback_phased_implementation.md` — phased implementation with audit + commit
  - `feedback_local_migration_workflow.md` — edit migrations in place; auto-run `migrate:fresh` in local
  - `project_reactivation_flow.md` — re-registration & account reactivation policy (§13 below)
  - `project_implementation_progress.md` — phases shipped vs pending; phase → commit map (§14 below)
  - `reference_laragon_ssl_gotchas.md` — Laragon local SSL failure modes (Apache `mod_ssl` not loaded, openssl.exe entry-point crash, cert-bootstrap trap) and their fixes — captured during the Phase 2 follow-ups session

---

## 13. Re-registration & Account Reactivation Flow (decided 2026-05-01, revised 2026-06-11)

### Why this section exists

Phase 1 (commit `e61d59a`) added `softDeletes()` to `users`. `users.email` is `unique`, so a soft-deleted row blocks re-registration with the same email. This section locks the policy. Implementation is split between **the auth layer** (registration + password reset) and **Phase 9** (SAO review).

> **Revision (2026-06-11, AUDIT.md AUD-004, commit `512a97c`):** the original policy reactivated the row inline at `/register`, before any proof of mailbox ownership — meaning any anonymous party who knew the email could reverse an admin's deactivation, claim the row's identity/audit history out from under the legitimate returning student, and read account state from the response shape. Reactivation is now **verify-first** through the password-reset flow. The original register-time policy below is superseded where it conflicts.

### Policy

1. **Self-registration never touches existing rows.** An email matching an active *or* soft-deleted user fails the unique rule with the identical 422 ("email already taken") — the two cases are indistinguishable to the requester, and a soft-deleted row is never restored, overwritten, or role-stripped by an anonymous `POST /register`.

2. **Reactivation happens through the password-reset flow.** The "email already taken" message naturally routes the returning user to *Forgot password*. The password broker uses a dedicated provider (`App\Services\PasswordBrokerUserProvider`, `users-with-trashed` in `config/auth.php`) that includes trashed users, so the reset link reaches the mailbox; redeeming the token is the proof of mailbox ownership that register-time reactivation lacked. `ResetUserPassword::reactivate()` then, in one transaction: restores the row, sets the new password, clears `remember_token`, and detaches all roles. `name` and `email_verified_at` keep their historical values — same mailbox, same identity.

3. **Reactivation does NOT auto-restore role assignments.** All `role_user` rows are detached; each detachment writes a `RoleRevoked` audit row and the restore writes a `Restored` row, all with `reactivated: true` context (AUD-028). The user re-enters roleless and becomes `Applicant` via the normal apply flow. Mailbox proof says "I control this inbox today," not "I am the same human as before" — email recycling is a real risk in the .edu and Cameroonian contexts.

4. **Trashed staff/admin accounts are excluded from self-service reactivation.** `PasswordBrokerUserProvider` filters trashed users holding any `RoleName::staff()` role (Lecturer, Accountant, SAO, Admin): no reset link is sent and a forged token resolves to no user. Their only path back is the admin user-management restore (`admin.users.restore`), which preserves their roles deliberately.

5. **Prior `StudentProfile` / `LecturerProfile` / `applications` / `audit_logs` stay intact** (still soft-deleted where applicable). Data is preserved; trust is not.

6. **SAO performs identity verification + one-click re-attachment** during application review (Phase 9). The review screen detects soft-deleted profile or prior decided application rows for that `user_id` and renders a banner with `[Restore prior enrollment]` and `[Admit as new student]` actions. `[Restore prior enrollment]` runs a single transaction: `StudentProfile->restore()`, re-attach `Student` role, mark current application as `Withdrawn` with merge note, three audit entries (`Restored`, `RoleAssigned`, `StatusChanged`). `[Admit as new student]` runs the normal admit flow and audits `acknowledged_prior_history: true`.

7. **Phone-number matching is deferred.** Reactivation matches on email only. Phone-as-secondary-identifier is its own future design.

### Implementation contract

**Auth layer** (revised in `512a97c`):
- `CreateNewUser`: plain validate-then-create; the unique rule counts trashed rows, so active and trashed conflicts 422 identically. A concurrent-insert unique violation re-throws as the same 422 (AUD-017).
- `PasswordBrokerUserProvider` (broker-only; the session guard keeps the default provider, so trashed users still can't log in): `withTrashed()` lookups, trashed staff/admin filtered to null.
- `ResetUserPassword`: trashed branch runs the reactivation transaction described in Policy 2–3.
- Tests: `tests/Feature/Auth/RegistrationTest.php` (row untouched, identical 422s, race → 422) and `tests/Feature/Auth/PasswordResetTest.php` (reactivation restores row + detaches roles + audits; staff excluded at send and redeem; reactivated account can log in).

**Phase 9** — `app/Actions/Sao/RestorePriorEnrollment.php` + SAO review controller + PrimeVue banner on the Inertia review page:
- Review controller loads `priorHistory = ['profiles' => StudentProfile::withTrashed()->where('user_id', ...)->get(), 'applications' => Application::withTrashed()->where('user_id', ...)->whereNotIn('status', ['Draft'])->get()]`.
- `RestorePriorEnrollment` takes `(User, StudentProfile $prior, Application $current)`, transactional.
- Tests: transactional rollback on partial failure; role re-attached exactly once; three audit entries written; banner renders only when prior history exists; non-SAO returns 403.

### Open question deferred to Phase 7/9

Whether the dropped current-application status should be `Withdrawn` or a new dedicated `MergedIntoPriorEnrollment` value when the `ApplicationStatus` enum is finalized in Phase 7. `Withdrawn` is fine if the audit-log note carries the merge context; a dedicated status is cleaner for reporting.

---

## 14. Implementation Progress

**Last updated:** 2026-06-12 (AUD-033 doc refresh; route count and post-Phase-10 work recorded). Refresh this section at every phase-boundary commit. The authoritative roadmap stays §8; the authoritative diff for each phase is its commit. This section is the **bridge** between them — phase number → commit SHA → at-a-glance summary.

> **Supersession note:** the per-phase records below are accurate as history of what each commit shipped, but audit Fix Phases 1–5 (§15) later changed several of those decisions in place — notably: PrimeVue global registration + `chunkSizeWarningLimit: 1000` (Phase 2 → reversed by AUD-020), inline reactivation in `CreateNewUser` (Phase 3 → rewritten by AUD-004, see §13), the count-based matricule generator (Phase 9 → replaced by AUD-006), and the duplicated status/label maps (Phases 8–10 → consolidated by AUD-027). When a §14 detail conflicts with §15, §15 wins.

### Status table

| Phase | Title | Status | Commit |
|---|---|---|---|
| 1 | Roles & Authorization Foundation | ✅ Done | `e61d59a` |
| 2 | UI Toolkit: PrimeVue + Aura | ✅ Done | `fe01bc5` (+ follow-ups `6a383ec`) |
| 3 | Identifier-Flexible Login + Role-Based Redirect | ✅ Done | `13ae327` |
| 4 | Reference Tables + Admin CRUD | ✅ Done | `9554088` |
| 5 | Audit Log Infrastructure | ✅ Done | `7ebc8aa` |
| 6 | Per-Role Profile Models | ✅ Done | `240f015` |
| 7 | Application Domain Models | ✅ Done | `28e655f` |
| 8 | Applicant Dashboard + Application Form | ✅ Done | `8c55067` |
| 9 | SAO Decision Flow + Admit-to-Student Promotion | ✅ Done | `c4d9d38` |
| 10 | Admin Dashboard + Audit Log Modal | ✅ Done | `359ed1f` (API) + `9a664da` (UI) + `1f8ae87` (seeder) |
| UM-A | Admin User Management — backend + invite-link flow | ✅ Done | `ac997ac` |
| UM-B | Admin User Management — UI with role-aware forms | ✅ Done | `e99fc2e` |
| UM-C | Admin User Management — invitation polish + role transitions | ✅ Done | `f46c02c` (+ fix `fa93a77`) |

Initial commit `96022ae feat: initiate first commit` is the starter-kit baseline before Phase 1. Other interim commits: `fc2324b` (split-screen login layout + tooltip-driven username field), `d2a5738`/`a8d69d4`/`e044f81` (small seeder/UI chores). Everything after `e044f81` is audit remediation — see §15.

### Phase 1 — Roles & Authorization Foundation (`e61d59a`)

**Shipped:**
- `App\Enums\RoleName` (Applicant, Student, Lecturer, Accountant, Sao, Admin).
- `roles` + `role_user` tables; `Role` model with `SoftDeletes`; `HasRoles` trait on `User` with idempotent `assignRole` / `removeRole` / `hasRole` / `hasAnyRole`.
- `RolesSeeder` (idempotent, wired into `DatabaseSeeder`).
- 7 ability gates in `AppServiceProvider::configureGates()`: `process-admission`, `decide-application`, `validate-payment`, `publish-results`, `approve-course-plan`, `mark-attendance`, `view-audit-log`.
- `users` table now has `employee_id` (nullable, unique) and `softDeletes()` — edited in place per the local-migration workflow.
- 35 new Pest cases (`RoleAssignmentTest` + dataset-driven `AbilityGatesTest`); 90/90 total tests passing.

**Side fixes folded in (called out for traceability):**
- Enabled global `RefreshDatabase` in `tests/Pest.php` — starter kit shipped it commented out, leaving every auth/settings test broken.
- Updated `ProfileUpdateTest::user_can_delete_their_account` for soft-delete semantics on `User`.

**Open follow-up resolved by §13:** the `users.email` unique-constraint vs soft-delete tension is fully addressed by the §13 reactivation design — implementation lands in Phase 3 + Phase 9.

### Phase 2 — UI Toolkit: PrimeVue + Aura (`fe01bc5`)

**Shipped:**
- npm: `primevue`, `@primeuix/themes`, `tailwindcss-primeui` (dev).
- `resources/css/app.css` imports `tailwindcss-primeui` directly after `tailwindcss`.
- `resources/js/app.ts` owns its own `setup({ el, App, props, plugin })` and registers PrimeVue with the Aura preset (`darkModeSelector: '.dark'`, `cssLayer.order: 'theme, base, primevue, utilities'`), `ToastService`, plus globally registered `Button`, `Column`, `DataTable`, `Dialog`, `FileUpload`, `InputText`, `Select`, `Toast`.
- `<Toast />` placed in `resources/js/layouts/app/AppSidebarLayout.vue` next to the existing vue-sonner `<Toaster />` (server flash toasts continue through `initializeFlashToast` → vue-sonner; PrimeVue toasts available via `useToast()`).
- Smoke `<Button label="PrimeVue ready" />` on `Dashboard.vue`.
- Project `CLAUDE.md` gained a "UI Components" section documenting the policy.

**Audit:** `npm run build` ✓, `types:check` ✓, `lint:check` ✓, `format:check` ✓, all 90 Pest tests still green, Pint clean, no new routes.

**Phase 2 follow-ups (closed in `6a383ec`):**
- **Icon strategy locked in.** `lucide-vue-next` is the single icon library across the app — used inside PrimeVue components via the `#icon` slot (e.g. `<template #icon><Check class="size-4" /></template>`). PrimeIcons is intentionally NOT installed. The smoke `<Button>` on `Dashboard.vue` was migrated from `icon="pi pi-check"` to the slot pattern. Convention documented in the project `CLAUDE.md` "UI Components" section.
- **Vite chunk-size warning silenced.** `vite.config.ts` now sets `build.chunkSizeWarningLimit: 1000`. Bundle is still ~913 KB (expected with 8 globally registered components); revisit only if real-world perf becomes a concern.
- **Dark-mode visual check passed.** PrimeVue Aura tokens flip cleanly together with the surrounding shadcn-vue layout. Appearance tabs at `/settings/appearance` (Sun/Monitor/Moon) persist via `useAppearance.ts` (localStorage + cookie).

**Local environment fixes folded in (outside the repo, captured in `reference_laragon_ssl_gotchas.md`):** the dark-mode check session uncovered two distinct SSL problems with the local Laragon stack — Apache `mod_ssl` not loaded after pointing Laragon at a fresh Apache zip (`AH00526 Invalid command 'SSLEngine'`), and an `openssl.exe` packaging mismatch in the Apache Lounge VS18 distribution (3.6.1 EXE shipped next to 3.0.19 sibling DLLs → `STATUS_ENTRY_POINT_NOT_FOUND` crash → broken cert regen). Both fixes are described in the reference memory; Laragon's auto-cert flow is now durably restored for all 44 local sites.

### Phase 3 — Identifier-Flexible Login + Role-Based Redirect (`13ae327`)

**Shipped:**
- `Fortify::authenticateUsing()` resolves the login identifier as `email` then `users.employee_id`. Matricule lookup is stubbed in a comment for Phase 6 (a one-line append once `student_profiles` exists).
- `App\Services\RoleDashboardResolver` owns the role-priority table (`Admin > Sao > Accountant > Lecturer > Student > Applicant`) and falls through to `/applicant/dashboard` for roleless users — the natural landing for fresh registrations and reactivated accounts.
- `App\Http\Responses\LoginResponse` (bound in `FortifyServiceProvider::register()`) replaces Fortify's default; `App\Http\Controllers\DashboardController` keeps the legacy `/dashboard` URL working as a smart redirect (still linked from `Welcome.vue`, `AppSidebar.vue`, `AppHeader.vue`).
- Six per-role dashboard pages under `resources/js/pages/dashboards/` (PrimeVue `<Card>` placeholders with lucide icons + Wayfinder route imports). The applicant route is intentionally unguarded — it is the roleless fallback.
- `App\Http\Middleware\EnsureUserHasRole` registered as `role` alias in `bootstrap/app.php`; usage `role:admin` / `role:sao,admin`. 401 for guests, 403 for wrong role.
- `App\Actions\Fortify\CreateNewUser` ships the §13 reactivation transaction: trashed-aware lookup, restore + name/password overwrite + clear `email_verified_at` + detach all `role_user` rows. Phase 5 audit-log line is left as a comment.
- `User` now `implements MustVerifyEmail` per §4.7.
- `UserFactory::staff(?string $employeeId)` state for tests; `employee_id` stays out of `$fillable` (deliberate — Phase 10 will plumb it via Form Requests).
- Login UI label updated to "Email or employee ID".

**Tests added (14 new):**
- `RegistrationTest`: four reactivation scenarios — same `users.id`, name/password/`email_verified_at` overwritten, roles detached, non-trashed conflict still 422s.
- `AuthenticationTest`: post-login redirect retargeted to `/applicant/dashboard`, plus staff `employee_id` login + unknown-identifier negative case.
- `Feature/Dashboards/RoleRedirectTest`: roleless → applicant; data-driven each-role-to-its-dashboard over `RoleDashboardResolver::PRIORITY`; multi-role priority; `role:` middleware blocks unauthorized 403.
- `DashboardTest` slimmed to the guest-redirect case only (the authenticated case moved to `RoleRedirectTest`).
- `Pest.php`: global `userWithRole(RoleName)` helper + `RolesSeeder` `beforeEach` on `Feature/Auth` and `Feature/Dashboards`.

**Audit:** Pint clean, 104/104 Pest green, `route:list --name=dashboard` shows 7 routes with correct middleware, `npm run build` ✓ (regenerated stale Wayfinder bundles to include `formVariants` `.form()` helpers, restoring `types:check` cleanliness), `lint:check` ✓, `format:check` ✓ after a one-file Prettier pass on `Login.vue`.

**Cross-phase contract honored:** the §13 reactivation flow is half-done — Phase 9 still owes the SAO `[Restore prior enrollment]` banner + `RestorePriorEnrollment` action. Until both ship, a returning student stays roleless after re-registration.

### Phase 4 — Reference Tables + Admin CRUD (`9554088`)

**Schema + models (4.1):**
- `App\Enums\DegreeProgram` (Hnd, Bachelors, Masters).
- 4 migrations (`2026_05_02_120000`–`120003`): `departments`, `program_offerings`, `document_types`, `level_credential_requirements`. All have `softDeletes()`, `timestamps()`, `restrictOnDelete()` FKs, and the §6.3 uniqueness constraints (`program_offerings.unique(department_id, degree_program)`, `level_credential_requirements.unique(program_offering_id, level, document_type_id)` named `lcr_offering_level_doctype_unique`). `level_credential_requirements.required` defaults to `true`.
- 4 models with `SoftDeletes`, `#[Fillable(...)]` PHP attribute, `casts()` (`degree_program` → `DegreeProgram`, `level`/`min_level`/`max_level` integer, `required` boolean), and the relationship graph: `Department hasMany ProgramOffering`; `ProgramOffering belongsTo Department`, `hasMany LevelCredentialRequirement`; `DocumentType hasMany LevelCredentialRequirement`; `LevelCredentialRequirement belongsTo ProgramOffering` and `DocumentType`.

**Seeders (4.2):**
- `DocumentTypesSeeder` (idempotent on `code`) — 6 default types per §4.9: `NID`, `BIRTH`, `GCE_AL`, `HND`, `BACH`, `MAST`.
- `DemoReferencesSeeder` (idempotent) — Computer Science department + 3 offerings (HND 1–2, Bachelors 1–4, Masters 1–2) + 4 entry-level credential rules: HND L1 → GCE A-Level, Bachelors L1 → GCE A-Level, Bachelors L3 → HND Diploma (lateral entry), Masters L1 → Bachelors Degree. National Identity + Birth Certificate are intentionally NOT seeded into `level_credential_requirements` because they are always-required at the form level (§4.9), not driven by the per-level rules engine.
- `DatabaseSeeder` now calls `RolesSeeder`, `DocumentTypesSeeder`, `DemoReferencesSeeder` via `$this->call([...])`.

**Gate + Form Requests + Controllers + routes (4.3):**
- `AppServiceProvider::ABILITIES` gained `'manage-references' => [RoleName::Admin]`. The existing `configureGates()` loop registers it automatically. Gate is intentionally NOT wired into route middleware — the spec mandates `role:admin`. The gate exists for `@can`/template checks and future Inertia ability sharing.
- **8 Form Requests** under `app/Http/Requests/Admin/References/` — Store + Update for each of the 4 resources. None override `authorize()` (auth is at middleware level, matching the `Settings/*` convention). No shared trait under `app/Concerns/` (rule overlap is too thin to justify; only Department/DocumentType share the name+code pattern).
- `ProgramOffering` requests enforce composite unique `(department_id, degree_program)` via `Rule::unique(...)->where(closure)->ignore($id)`, and `max_level >= min_level` via `gte:min_level`. The Update request resolves `departmentId` with a fallback to the existing offering's value, so the unique scope is correct even if the user only edits the level range.
- `LevelCredentialRequirement` requests use a **closure rule on `level`** (extracted to a protected `levelWithinOfferingRange()` method) that loads the offering by `program_offering_id` and fails if `value < min_level || value > max_level`. **Bails silently** if `program_offering_id` is missing/non-numeric or the offering doesn't exist — so the upstream `exists` rule's error fires first instead of a confusing "between X and Y" message on bad input. Composite unique `(program_offering_id, level, document_type_id)` attached to `document_type_id`.
- `ProgramOfferingUpdateRequest` carries a `// TODO (Phase 7)` comment noting that narrowing `min_level/max_level` can orphan existing child requirements; deferred because Phase 7's `ApplicationStoreRequest` will re-check the range from the offering anyway.
- **4 Controllers** under `app/Http/Controllers/Admin/References/`. Pattern matches `Settings/ProfileController`: `Inertia::render` for index, `Inertia::flash('toast', [...])` + `back()` for mutations. Route-model binding throughout.
- **`destroy()` refuses with an error toast when child rows exist** (decision recorded — not in the original §8 spec): Department → `programOfferings()->exists()`, ProgramOffering → `levelCredentialRequirements()->exists()`, DocumentType → `levelCredentialRequirements()->exists()`. `LevelCredentialRequirement` has no children → straight soft-delete. This mirrors the `restrictOnDelete` semantics in the UX (the FK constraint only blocks `forceDelete`, not soft-delete).
- `index()` ships eager-loaded relations (`with('department:id,name,code')`, etc., plus `withCount(...)` for parent rows) and aux dropdown data (departments list, document types list, `DegreeProgram` enum cases as `[{value, label}]`, offerings with `min_level`/`max_level` for client-side level clamping in the 4.4 Dialog).
- `routes/admin.php` (NEW) defines **17 named routes** under one `Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin/references')->name('admin.references.')->group(...)`. URL slugs are kebab-case (`program-offerings`, `document-types`, `level-requirements`). Verb on update: `PATCH` (matches `routes/settings.php`). Hub route `admin.references.index` defined here, Vue file landed in 4.4. `routes/web.php` got `require __DIR__.'/admin.php';` appended after the existing settings include.

**Inertia pages (4.4):**
- 5 pages under `resources/js/pages/admin/references/`: `Index.vue` (4-card hub linking to each resource), `Departments.vue`, `DocumentTypes.vue`, `ProgramOfferings.vue`, `LevelRequirements.vue`.
- All built with PrimeVue `DataTable` + `Dialog`. Per-page imports for `Card`, `InputNumber`, `Tag`, `Textarea`, `ToggleSwitch` (the rest are globally registered in `app.ts`).
- Forms use `useForm` from `@inertiajs/vue3` and submit via `Controller.store/update/destroy(...).url` Wayfinder helpers; every mutation passes `preserveScroll: true`. Server-side `Inertia::flash('toast', …)` surfaces through the existing `initializeFlashToast()` → vue-sonner pipeline (no new toast wiring).
- The level `InputNumber` is client-side clamped via `:min`/`:max` bound to the selected offering's range (the server-side closure rule remains the source of truth — the clamp is UX polish only).
- `dashboards/Admin.vue` got a "Reference data" link card so the hub is reachable; sidebar navigation will be revisited in Phase 10.
- Delete confirmation uses native `window.confirm()` for now; PrimeVue `ConfirmDialog` is intentionally deferred (would need `ConfirmationService` registration and a separate UX pass).

**Tests (4.5) — 103 new Pest cases, 207/207 total:**
- `tests/Feature/Admin/References/AuthorizationTest.php` — guests redirect to login, every non-admin role + roleless verified users get 403. Split into `unbound_endpoints` (index/store) and `boundReferenceEndpoints()` (PATCH/DELETE) because `SubstituteBindings` runs before `role:admin` middleware (it's part of the `web` group), so PATCH/DELETE need real seeded ids to reach the 403 path.
- `DepartmentsCrudTest.php`, `DocumentTypesCrudTest.php` — CRUD happy paths, dup-name/code 422, delete-with-children refused (controller guard, row stays).
- `ProgramOfferingsCrudTest.php` — composite-unique 422, same-program-different-department succeeds, `gte:min_level` 422, delete-with-children refused.
- `LevelRequirementsCrudTest.php` — level-out-of-range 422 (above + below), composite-unique 422, same-doctype-different-level allowed.
- `RestrictOnDeleteTest.php` — `forceDelete()` on each parent with a child throws `QueryException` (verifies the migration FK constraints).
- `tests/Pest.php` — added `'Feature/Admin'` to the `RolesSeeder` `beforeEach` group.

**Design decision — soft-delete + recreation (4.5):** the §8 spec called for "re-creating a `(department, degree_program)` pair after soft-deleting an offering with the same key should succeed". The 4.3 Form Requests had `Rule::unique(...)->whereNull('deleted_at')` on every unique check, which made validation strictly more lenient than the DB-level `->unique()` index (which includes trashed rows). In practice, validation said "fine" and the DB then rejected with `SQLSTATE 23000` → 500. **Resolution: dropped `->whereNull('deleted_at')` from every `Rule::unique(...)` call** (validation now matches the DB). Recreating after soft-delete is now blocked at validation with a clean 422; if an admin really needs the slot back they `restore()` the trashed row (Phase 10 will surface trashed rows in the admin UI). `Rule::exists(...)` still keeps `->whereNull('deleted_at')` — referencing a trashed parent should remain blocked. The two recreation tests in `DepartmentsCrudTest` / `ProgramOfferingsCrudTest` were flipped to assert 422.

**Audit:** `vendor/bin/pint --dirty --test --format agent` ✓, 207/207 Pest, `route:list --name=admin.references` shows 17 routes (`web,auth,verified,role:admin`), `npm run build` ✓, `types:check` ✓, `lint:check` ✓, `format:check` ✓.

### Phase 5 — Audit Log Infrastructure (`7ebc8aa`)

**Schema + enum + model (5.1):**
- `App\Enums\AuditAction` with 12 cases per §6.5: `Created`, `Updated`, `Deleted`, `Restored`, `StatusChanged`, `RoleAssigned`, `RoleRevoked`, `LoggedIn`, `LoginFailed`, `LoggedOut`, `ApplicationDecided`, `PaymentValidated`.
- Migration `2026_05_04_120000_create_audit_logs_table.php`: `id`, `user_id` (nullable, `nullOnDelete()` so deleting a user doesn't cascade-destroy their audit trail), `action` (string), `nullableMorphs('subject')`, `changes` JSON nullable, `context` JSON nullable, `occurred_at` timestamp. **No `softDeletes()`, no `timestamps()`** — audit rows are immutable. Indexes on `user_id`, `action`, `occurred_at`, plus the morph index from `nullableMorphs()`.
- `App\Models\AuditLog`: `$timestamps = false`, casts `action → AuditAction`, `changes` and `context` to `array`, `occurred_at → datetime`. **`booted()` registers `updating` and `deleting` listeners that throw `RuntimeException`** — this catches `update()`, `save()` on a dirty existing row, `delete()`, and `forceDelete()`. `subject()` morphTo + `user()` belongsTo. Static `record(action, ?subject, ?changes, context, ?userId)` helper for non-Eloquent events; static `buildContext(extra)` merges `ip` / `user_agent` / `route` from the current request, with caller-supplied keys winning on conflict.

**RecordsAudit trait (5.2):**
- `app/Models/Concerns/RecordsAudit.php` — `bootRecordsAudit()` hooks into `created`, `updated`, `deleted`, `restored` and routes each to `AuditLog::record(...)`. Created/Deleted/Restored carry `['attributes' => $model->auditAttributes()]`; Updated carries `['before' => ..., 'after' => ...]` from `auditDiff()`.
- `auditDiff()` walks `getChanges()` and pairs each non-excluded key with its `getOriginal($key)`. Returns `null` when only excluded fields changed (e.g. a `touch()` that only bumps `updated_at`) — the listener short-circuits and writes nothing, avoiding noise.
- Default `auditExclude()` strips `password`, `remember_token`, `two_factor_secret`, `two_factor_recovery_codes`, and the three timestamp columns. Models can override per-class.
- The `restored` listener registers unconditionally — for non-`SoftDeletes` models it's just inert.

**Auth event listeners (5.3):**
- `AppServiceProvider::configureAuditListeners()` (called from `boot()`) wires `Login`, `Failed`, `Logout` events to `AuditLog::record(LoggedIn|LoginFailed|LoggedOut, ...)`. Each listener passes `$event->user->id` explicitly because `Auth::id()` is not yet/no longer set during the event window — `SessionGuard` fires `Login` before `setUser()`, and `Logout` after the guard has been cleared. `Failed` listener passes the matched user when the event provides one (wrong-password case) and `null` for unknown-identifier attempts.

**Retrofits (5.4):**
- `App\Models\Role` now `use RecordsAudit, SoftDeletes` — Phase 1 retrofit per the cross-phase contract. The Phase 4 reference models (`Department`, `ProgramOffering`, `DocumentType`, `LevelCredentialRequirement`) are intentionally NOT retrofitted in this phase; they can opt in later when Phase 10's audit modal needs them, since the trait is a one-line addition per model.
- `App\Actions\Fortify\CreateNewUser` reactivation transaction now writes an explicit `AuditLog::record(AuditAction::Restored, $user, context: ['reactivated' => true], userId: $user->id)` — the placeholder comment from Phase 3 (`// Phase 5 will record an audit row here…`) is replaced. `userId` is passed explicitly because no one is authenticated during registration. When Phase 6 retrofits `User` with `RecordsAudit`, the trait's automatic Restored entry will coexist with this one — they serve different purposes (the trait captures the snapshot; this one carries the `reactivated: true` semantic flag).

**Tests (5.5) — 26 new Pest cases, 233/233 total:**
- `tests/Feature/Audit/AuditLogTest.php` (9 cases) — `record()` field shape; auth fallback for `userId`; null-subject system events; `buildContext()` ip capture; caller-keys-win override; `update()` / mutated-`save()` / `delete()` all throw `RuntimeException`; `subject()` morphTo round-trips.
- `tests/Feature/Audit/RecordsAuditTest.php` (7 cases) — uses `Role` (the retrofitted model). Created snapshot, Updated before/after diff, no-op on `touch()`, Deleted snapshot on soft-delete, Restored snapshot on `restore()`, attributes the Created log to the authenticated actor, sensitive/timestamp keys excluded.
- `tests/Feature/Audit/AuthEventsTest.php` (6 cases) — direct `Event::dispatch()` for Login/Failed/Logout, plus end-to-end HTTP login / failed-login / logout via the routes. Each asserts the audit row exists with correct `subject_id` / `user_id` / `context['ip']`.
- `tests/Feature/Audit/ViewAuditLogGateTest.php` (3 cases) — admin allowed; the other 5 roles denied (data-driven over `RoleName::cases()` minus admin); roleless user denied.
- `tests/Feature/Auth/RegistrationTest.php` — added a 6th reactivation case asserting the `AuditAction::Restored` row carries `context.reactivated === true` and the correct `subject_id` / `user_id`.
- `Feature/Audit` is intentionally NOT added to `tests/Pest.php`'s `RolesSeeder` `beforeEach` group — most audit tests don't need pre-seeded roles, and `ViewAuditLogGateTest` seeds inline. Keeping the seeder out keeps audit-row counts predictable in the trait tests.

**Audit:** `vendor/bin/pint --dirty --format agent` ✓ (one cosmetic fixer on `RecordsAuditTest`), 233/233 Pest green, `php artisan route:list --except-vendor` shows 23 routes (Phase 5 adds none — the audit modal is Phase 10), `npm run build` not re-run (no frontend changes in this phase).

**Cross-phase contracts honored / deferred:**
- ✅ `Role` retrofit: shipped.
- ✅ `CreateNewUser` reactivation audit: shipped with `reactivated: true` context.
- ⏳ Per-role profile models (`StudentProfile`, `LecturerProfile`, …): Phase 6 will create them with `RecordsAudit` from the start.
- ⏳ Application/payment-decision audit context (`StatusChanged`, `ApplicationDecided`, `PaymentValidated` enum cases): Phase 7/9 will emit these via `AuditLog::record(...)` from the relevant actions.
- ⏳ Admin audit-log viewer + filtering modal: Phase 10.
- ⏳ Phase 4 reference-table audit retrofit (one-line `use RecordsAudit;` per model): deferred until needed.

### Phase 6 — Per-Role Profile Models (`240f015`)

**Enum + migrations (6.1–6.2):**
- `App\Enums\StudentStatus` (Active, Suspended, Graduated, Withdrawn).
- 4 migrations (`2026_05_05_120000`–`120003`) — `student_profiles`, `lecturer_profiles`, `accountant_profiles`, `sao_profiles`. Each has `foreignId('user_id')->unique()->constrained()->restrictOnDelete()` + `softDeletes()` + `timestamps()`. Student adds `matricule` (unique) + `program_offering_id` FK + `level` + `academic_year` + `enrolled_at` + `status` (default `Active`, indexed). Lecturer adds `department_id` FK + nullable `specialization` + nullable `hired_at`. Accountant adds nullable `bank_desk` + `cashier_window`. Sao adds nullable `scope` placeholder.

**Models (6.3):**
- `StudentProfile`, `LecturerProfile`, `AccountantProfile`, `SaoProfile` — each `use HasFactory, RecordsAudit, SoftDeletes` (per the Phase 5 cross-phase contract). `#[Fillable]` attribute, `casts()` (StudentStatus enum on student, dates on student/lecturer), `belongsTo(User)` on all, `belongsTo(ProgramOffering)` on Student, `belongsTo(Department)` on Lecturer.
- `StudentProfile` defines an `Attribute::set` mutator that lowercases `matricule` on assignment. **Why:** Fortify's `CanonicalizeUsername` action lowercases the login identifier before passing it to the resolver, and the test suite uses SQLite (case-sensitive `=`). Storing canonicalized matricules keeps the resolver portable across DBs without `whereRaw('LOWER(...)')` gymnastics.

**User relations (6.4):**
- `User` gained four `hasOne` relations: `studentProfile`, `lecturerProfile`, `accountantProfile`, `saoProfile`. Returns `null` when absent.

**Login resolver (6.5):**
- The Phase 3 placeholder comment in `FortifyServiceProvider::configureAuthentication()` is replaced with the real third lookup: `User::query()->whereHas('studentProfile', fn ($q) => $q->where('matricule', $identifier))->first()`. Resolver still falls through to email → employee_id → matricule in that order.
- `resources/js/pages/auth/Login.vue` label updated to "Email, employee ID, or matricule" with placeholder `you@example.com, emp-1234, or stm-2026-001`.

**Factories (6.6):**
- 4 factories under `database/factories/`. `StudentProfileFactory` lazily resolves a default `Department::firstOrCreate(code=CS)` + `ProgramOffering::firstOrCreate(department, Bachelors)` so it works without a separately seeded reference graph; matricule is `stm-` + 6-digit unique numerify; status defaults to `Active`. `LecturerProfileFactory` does the same `Department::firstOrCreate` for its FK.

**Tests (6.7) — 13 new Pest cases, 246/246 total green:**
- `tests/Feature/Profiles/ProfilesTest.php` (12 cases) — `User` hasOne returns null then resolves the created profile (one per role); StudentStatus enum cast; Created audit row written for all 4 profile types; `forceDelete()` on a User with each profile type throws `QueryException` (FK `restrictOnDelete()` enforced); unique-matricule constraint; unique-user_id constraint per profile table.
- `tests/Feature/Auth/AuthenticationTest.php` — added "students can authenticate using their matricule" case asserting `assertAuthenticatedAs($user)` + redirect to `/student/dashboard` after the Student role is assigned. Test posts uppercase `STM-LOGIN-1` to verify Fortify's lowercasing pipeline matches the canonicalized stored value.
- `Feature/Profiles` is intentionally NOT added to the `tests/Pest.php` `RolesSeeder` `beforeEach` group — those tests don't need pre-seeded roles, and skipping the seeder keeps audit-row counts predictable.

**Audit:** `php artisan migrate:fresh --seed` ✓, `vendor/bin/pint --dirty --format agent` clean (one cosmetic fixer on the student-profile migration moved the `StudentStatus` import to the top), 246/246 Pest green, `php artisan route:list --except-vendor` shows 23 routes (Phase 6 adds none — UI lands in Phase 8), `npm run types:check` ✓, `npm run lint:check` ✓.

**Cross-phase contracts honored / deferred:**
- ✅ Profile models opted into `RecordsAudit` from the start.
- ✅ Matricule login resolver active.
- ⏳ Application/payment audits (`StatusChanged`, `ApplicationDecided`, `PaymentValidated`): Phase 7/9.
- ⏳ Phase 9 prior-history banner reads `StudentProfile::withTrashed()` for the same `user_id` — the unique FK + soft-delete combination shipped here is what makes that lookup safe.
- ⏳ Whether to retrofit `User` itself with `RecordsAudit` (auth events already cover login lifecycle; profile-settings changes would be the candidate) deferred to Phase 10 admin user-management work.

### Phase 7 — Application Domain Models (`28e655f`)

**Enum + migrations (7.1–7.2):**
- `App\Enums\ApplicationStatus` (8 cases): `Draft`, `Submitted`, `UnderReview`, `DocumentsRequested`, `Admitted`, `Rejected`, `Waitlisted`, `Withdrawn`. `UnderReview` and `DocumentsRequested` use snake_case values to keep enum->string parsing stable.
- 2 migrations (`2026_05_06_120000`–`120001`) — `applications` and `application_documents`. Both have `softDeletes()` + `timestamps()`. Applications: `user_id` + `program_offering_id` (both `restrictOnDelete`), `level`, demographic fields, indexed `status` defaulting to Draft, nullable `submitted_at`/`decided_at`/`decision_notes`, `decided_by_user_id` FK to `users` with `nullOnDelete()` (an SAO leaving shouldn't cascade-destroy the decision history). Application_documents: `application_id` + `document_type_id` (both `restrictOnDelete` per the §6 default), file metadata columns, **unique `(application_id, document_type_id)`** so a slot can't be uploaded twice.

**Models (7.3):**
- `Application` and `ApplicationDocument` — both `use HasFactory, RecordsAudit, SoftDeletes`. `#[Fillable]`, `casts()` (status enum, `level`/`size_bytes` int, dates).
- Application relations: `applicant()` (`belongsTo User, 'user_id'`), `programOffering()`, `decidedBy()` (`belongsTo User, 'decided_by_user_id'`), `documents()` (`hasMany ApplicationDocument`).
- ApplicationDocument relations: `application()`, `documentType()`.

**Factories (7.4):**
- `ApplicationFactory` with a `submitted()` state. Lazy-resolves the default Department + Bachelors offering via `firstOrCreate` (same pattern as `StudentProfileFactory` from Phase 6) so it works without seeded reference data.
- `ApplicationDocumentFactory` defaults to a `DocumentType::firstOrCreate(code=NID)` and a fake PDF path/metadata.

**StoreApplicationRequest (7.5):**
- `App\Http\Requests\Applications\StoreApplicationRequest`. Validates `program_offering_id` exists (excluding trashed); `level` is between 1..10 with a `levelWithinOfferingRange()` closure that loads the offering and `fail()`s if `level` is outside `[min_level..max_level]`; demographics fields with appropriate types; `documents` array required with one nested rule per required code.
- **Two-source required-codes computation** in `requiredDocumentCodes()`: const `ALWAYS_REQUIRED_CODES = ['NID', 'BIRTH']` (per §6.4) merged with codes from `level_credential_requirements` rows where `(program_offering_id, level, required=true)`. Missing-document validation messages use the document_type code so the UI can pinpoint which slot to highlight.
- Per-file rules: `mimes:pdf,jpg,jpeg,png` + `max:8192` (8 MB).
- Public helper `documentTypeIdMap()` returns `[code => id]` so the controller doesn't re-query.

**Controller + route (7.6):**
- `App\Http\Controllers\Applications\ApplicationController` with a single `store()` action. Wraps a `DB::transaction` that creates the Application (`status = Submitted`, `submitted_at = now()`) and one `ApplicationDocument` per uploaded file (stored via `Storage::default()->store('applications')`), then redirects to `/applicant/dashboard` with a flash toast. Phase 8 will layer the Inertia pages (`/application/new`, `/application/{id}`) and any role auto-attachment on top.
- Route added to `routes/web.php` inside the existing `auth, verified` group: `POST /application` named `application.store`. Total routes: 24.

**Tests (7.7) — 9 new Pest cases, 255/255 green:**
- `tests/Feature/Applications/SubmitApplicationTest.php`. Each test seeds `DocumentTypesSeeder` inline and resolves a Bachelors offering + a `level=1, GCE_AL` requirement in `beforeEach`. Helper `applicationPayload()` returns a default valid payload that tests then mutate.
- Cases: happy path persists Application + 3 documents + Created audit rows for all 4 subjects (Application + 3 docs) attributed to the actor; level=5 outside offering range (max 4) → 422 on `level`; missing `documents.NID` → 422; missing `documents.GCE_AL` (the level-credential code) → 422; oversized file → 422 on `documents.NID`; unsupported mime → 422 on `documents.NID`; guest → redirect to login; unverified user → redirect to `verification.notice`; soft-deleted application keeps Created + Deleted audit rows queryable and `Application::withTrashed()->find($id)` still resolves.
- Audit-log enum-cast assertion uses `AuditAction::Created`/`Deleted` instances (the `action` column is cast to enum), not their `->value` strings.
- `Feature/Applications` is intentionally NOT added to the `tests/Pest.php` `RolesSeeder` `beforeEach` group — these tests don't need pre-seeded roles, and they seed `DocumentTypesSeeder` inline in `beforeEach`.

**Audit:** `php artisan migrate:fresh --seed` ✓, `vendor/bin/pint --dirty --format agent` clean (one cosmetic fixer on the test file moved the seeder import to the top), 255/255 Pest green, `php artisan route:list --except-vendor` shows 24 routes (Phase 7 adds `application.store` only — Inertia pages land in Phase 8).

**Cross-phase contracts honored / deferred:**
- ✅ Application + ApplicationDocument opted into `RecordsAudit` from the start.
- ✅ `Application::status` defaults to Draft; `submit` flips to Submitted with `submitted_at`. Phase 9's `DecideApplicationAction` will move it to `Admitted`/`Rejected`/etc. with `decided_at` + `decided_by_user_id` + a `StatusChanged`/`ApplicationDecided` audit row.
- ✅ Always-required NID + BIRTH plus credential-aware required documents per §4.9.
- ⏳ Inertia pages (`/applicant/dashboard` table, `/application/new`, `/application/{id}`) + cascading-dropdown endpoints: Phase 8.
- ⏳ Role auto-attachment (roleless → Applicant on first submit) deferred to Phase 8 alongside the UI flow.
- ⏳ `ApplicationStatus::Withdrawn` vs a new `MergedIntoPriorEnrollment` for the §13 reactivation flow's "drop current application" branch — open question to settle in Phase 9.

### Phase 8 — Applicant Dashboard + Application Form (`8c55067`)

**Controller actions (8.1):**
- `App\Http\Controllers\Applications\ApplicationController` gains `dashboard()`, `create()`, and `show()` Inertia actions plus `offerings()` and `levelRequirements()` JSON endpoints. The Phase 7 `store()` action picks up role auto-attachment.
- `dashboard()` returns the authenticated user's applications (eager-loaded `programOffering.department`) shaped into a flat array — id, status enum value, level, submitted_at, created_at, and a nested `program_offering.department` block.
- `create()` ships `degreePrograms` (enum cases as `[{value, label}]`), `departments` (full list for client-side filtering), and `alwaysRequiredDocumentTypes` (NID + BIRTH from `DocumentType`). The dynamic level-credential lookup is fetched on demand from the cascading endpoint.
- `show()` does `abort_if($application->user_id !== $request->user()->id, 403)` and returns a fully shaped application payload including its documents with their `document_type` references.
- `offerings()` validates `degree_program?` + `department_id?` and returns the matching `ProgramOffering` rows with their department references. `levelRequirements()` validates `offering` (required) + `level` (required, 1..10) and returns the rows from `level_credential_requirements` flagged `required=true` for that pair, each carrying its `document_type` ref. Both endpoints `with()`-eager-load the relation they expose so the JSON payload is one query.

**Role auto-attachment (8.2):**
- `store()` checks `$user->roles()->doesntExist()` after persisting the application + documents. If true, it calls `assignRole(RoleName::Applicant)` and writes `AuditLog::record(AuditAction::RoleAssigned, $user, changes: ['role' => 'applicant'], userId: $user->id)`. The check is inside the same `DB::transaction` block, so a failure rolls back both the role attach and the application.
- This closes the §7 deferred contract: a fresh registration (roleless) becomes an Applicant on first submit; subsequent submits are no-ops because the user already has the role.

**Routes (8.3):**
- `routes/web.php` (under the existing `auth, verified` group):
  - `applicant/dashboard` — converted from `Route::inertia` to `[ApplicationController::class, 'dashboard']`. Route name unchanged (`applicant.dashboard`).
  - `GET application/new` → `application.create`.
  - `GET application/{application}` → `application.show`.
  - `POST application` → `application.store` (existing).
  - Sub-group `Route::prefix('api/v1')->name('api.v1.')` carries `program-offerings.index` and `level-requirements.index`. They share the `auth, verified` middleware so the form (which already requires verification) can call them with the same session cookie. Total routes: 29 (24 → 29: `applicant.dashboard` re-registered + 4 new endpoints).

**Inertia pages (8.4):**
- `dashboards/Applicant.vue` — replaces the Phase 3 placeholder. PrimeVue `DataTable` of the user's applications with PrimeVue `Tag` for status (severity map: `submitted=info`, `under_review/documents_requested=warn`, `admitted=success`, `rejected=danger`, the rest `secondary`). Empty state renders a centered "Start a new application" CTA.
- `applicant/applications/Create.vue` — three-stage cascading form. `Select` for Degree Program → on change, fetches `/api/v1/program-offerings?degree_program=...` and derives available departments from the response. `Select` for Department → resolves the unique offering (offering = single row matching `(degree_program, department_id)` per the §6.3 unique constraint). `InputNumber` for Level clamped to the selected offering's `min_level..max_level`. On level change, fetches `/api/v1/level-requirements?offering=...&level=...` and merges the returned codes with NID + BIRTH into the dynamic `FileUpload` slots.
- Personal fields use PrimeVue `InputText`, `InputMask` for the phone (mask `999999999` for 9-digit Cameroonian numbers), `DatePicker` (date format `yy-mm-dd`, `:max-date="new Date()"`), and a free-text `previous_institute`. Submit calls `useForm.transform(...)` to ISO-format the date and drop UI-only fields (`degree_program`, `department_id`) before posting with `forceFormData: true, preserveScroll: true`.
- `FileUpload` uses `mode="basic"` + `:auto="false"` per slot. Custom icon goes in the `#chooseicon` slot (NOT `#icon` — that's a typed slot only on `Button`). `accept` is `application/pdf,image/jpeg,image/png` and `max-file-size` is 8 MiB to mirror the server's `mimes:pdf,jpg,jpeg,png` + `max:8192`. Selected files are tracked in a plain `Record<string, File>` on the form so Inertia bracket-flattens them as `documents[NID]` / `documents[BIRTH]` / etc.
- `applicant/applications/Show.vue` — read-only Card with the application demographics + status `Tag`, plus a second Card with a `DataTable` of submitted documents (filename, size formatted, uploaded_at). No download button — Phase 9 ships that.

**HTTP client choice:** plain `fetch()` with `credentials: 'same-origin'` and `Accept: application/json`. Axios was removed in Inertia v3 (Boost rule), and `useHttp` would be overkill for two GET calls. The endpoints sit on the same origin, so the session cookie rides along automatically.

**Tests (8.5) — 17 new Pest cases, 272/272 green:**
- `tests/Feature/Applications/ApplicantDashboardTest.php` (3) — renders the user's applications with the right Inertia component name and shape; isolates per-user (Alice's applications don't leak into Bob's response); guest → redirect to login.
- `tests/Feature/Applications/ShowApplicationTest.php` (3) — owner can view (asserts the documents subarray is shaped); non-owner gets 403 (`abort_if`); guest → redirect to login.
- `tests/Feature/Applications/CreateApplicationFormTest.php` (3) — form props (`degreePrograms` 3 cases, `departments` non-empty, `alwaysRequiredDocumentTypes` exactly NID + BIRTH ordered by code); guest → login; unverified → `verification.notice`. Seeds `DocumentTypesSeeder` + `DemoReferencesSeeder` inline.
- `tests/Feature/Applications/CascadingLookupsTest.php` (6) — offerings filtered by `degree_program` returns 1 (CS Bachelors only); no filter returns 3 (Hnd, Bachelors, Masters from the demo seeder); level-requirements at `(Bachelors, level=1)` returns one rule with `document_type.code = GCE_AL`; level=2 returns `[]` (no rule seeded for that level); missing `offering` → 422; guests get 401 on both endpoints (JSON request → unauthenticated 401 instead of redirect).
- `SubmitApplicationTest.php` — added two cases: roleless user gains Applicant + a `RoleAssigned` audit row with `changes = {role: 'applicant'}`; a user who already has the Student role keeps it and gets no `RoleAssigned` audit row (idempotent guard).
- `tests/Pest.php` — `Feature/Applications` joins the `RolesSeeder` `beforeEach` group. The auto-attach call needs the Applicant role row to exist; the existing Phase 7 cases are unaffected because they create their own users without explicit roles.

**Audit:** `php artisan migrate:fresh --seed` ✓, `vendor/bin/pint --dirty --format agent` clean, 272/272 Pest green, `php artisan route:list --except-vendor` shows 29 routes, `npm run build` ✓, `npm run types:check` ✓ (had to switch the `FileUpload` icon slot from `#icon` to `#chooseicon` to satisfy the typed slot map), `npm run lint:check` ✓, `npm run format:check` ✓ (Prettier autofix on the three new Vue files only).

**Cross-phase contracts honored / deferred:**
- ✅ Applicant role auto-attachment closes the §7 deferred contract.
- ✅ Cascading dropdown endpoints land at the URLs the original §8 spec called for (`/api/v1/program-offerings`, `/api/v1/level-requirements`).
- ⏳ Document download endpoint (`Storage::download` for an `ApplicationDocument`) deferred to Phase 9 alongside the SAO review screen — both consumers will share the same auth-aware download route.
- ⏳ The `MergedIntoPriorEnrollment` vs `Withdrawn` decision for the §13 reactivation merge case is still open; settle it when wiring the §13.4 review banner in Phase 9.
- ⏳ Sidebar / navigation polish (a per-role menu so applicants don't have to discover `/application/new` via the dashboard CTA) deferred to Phase 10's cleanup pass.

### Phase 9 — SAO Decision Flow + Admit-to-Student Promotion

**State machine on `Application` (9.1):**
- `Application::TERMINAL_STATUSES` = Admitted, Rejected, Waitlisted, Withdrawn. `INTERIM_STATUSES` = Submitted, UnderReview, DocumentsRequested.
- `isTerminal()` and `canTransitionTo(ApplicationStatus $next)` guard transitions: terminal → nothing; interim → any other interim or terminal. Draft is not part of the SAO flow (it's the applicant-side stub before submit; everything entering SAO is at minimum Submitted).

**Matricule generator on `StudentProfile` (9.2):**
- Static `nextMatriculeForYear(int $year): string` returns `stm-{year}-{0001-padded}` based on `withTrashed()->count()` of matching matricules. Caller is responsible for the surrounding `DB::transaction` + `lockForUpdate()` (the `DecideApplicationAction` issues the lock on the year's existing rows before computing the next number, so concurrent admits within the same request lifecycle don't collide).

**Three actions under `App\Actions\Sao\` (9.3):**
- `TriageApplicationAction` — moves between INTERIM_STATUSES; throws `ValidationException` if `canTransitionTo` is false; writes `StatusChanged` audit row with `before`/`after` diff.
- `DecideApplicationAction::ALLOWED_DECISIONS` = Admitted, Rejected, Waitlisted (Withdrawn is reserved for the merge flow). Inside `DB::transaction`: fills decision fields (`decided_at`, `decided_by_user_id`, `decision_notes`) via `saveQuietly()` (so `RecordsAudit::updated` doesn't double-fire alongside the manual `ApplicationDecided` row); on Admit calls `promoteToStudent()` which `lockForUpdate()`s the year's matching matricules, computes the next one, creates `StudentProfile`, assigns Student role, writes `RoleAssigned` audit. Always writes `ApplicationDecided` audit. `DB::afterCommit` dispatches `ApplicationDecided` event.
- `RestorePriorEnrollment` (§13.4): preconditions check trashed-ness, ownership match (both prior profile and current application belong to the same applicant), and that current isn't terminal. Inside `DB::transaction`: restores the prior profile (the trait emits a `Restored` audit automatically), assigns Student role with manual `RoleAssigned` audit, fills the current Application as Withdrawn with merge-prefixed notes via `saveQuietly()`, writes `StatusChanged` and `ApplicationDecided` audits (the latter carries `context: ['merged_into_prior' => true, 'prior_profile_id' => …]`). `DB::afterCommit` fires `ApplicationDecided` event. Closes the §13.4 contract that Phase 3 left half-done.

**Form Requests under `App\Http\Requests\Sao\` (9.4):**
- `TriageApplicationRequest` — status whitelisted to interim trio; `notes` `required` when status is `documents_requested`.
- `DecideApplicationRequest` — status whitelisted to admitted/rejected/waitlisted (Withdrawn explicitly NOT selectable); `notes` `required` for rejected/waitlisted; `acknowledged_prior_history` is `sometimes|boolean` and gets folded into the audit `context` when present.
- `RestorePriorEnrollmentRequest` — `prior_profile_id` `required|integer|exists:student_profiles,id` (no `whereNull('deleted_at')` because the action specifically wants trashed rows; the action's preconditions enforce the trashed-ness instead).
- `statusEnum()` accessor on the two status-bearing requests; controllers pass `$request->statusEnum()` directly to the actions.

**Controller + routes (9.5):**
- `ApplicationReviewController` ships `dashboard` (status counts), `index` (paginated, filterable by status, sortable on a whitelist of `submitted_at`/`created_at`/`level`), `show` (full applicant + programme + documents + prior-history sidecar via `StudentProfile::withTrashed()->where('user_id', …)` and `Application::withTrashed()->where('user_id', …)->where('id', '!=', $application->id)->whereNotIn('status', [Draft])`), `triage`, `decide`, `restorePrior`. The `index` `DEFAULT_STATUS_FILTER` is `[submitted, under_review, documents_requested]`; SAOs can browse decided rows by overriding the filter from the query string. Sort whitelist guards against arbitrary column sorts.
- `routes/sao.php` (NEW) groups everything under `web,auth,verified,role:sao,admin` with prefix `sao` and name `sao.`. Required from `routes/web.php` after the existing settings/admin includes. Decision routes redirect to `sao.applications.index`; triage redirects `back()`. Total routes: 29 → 36.
- The Phase 8-era `Route::inertia('sao/dashboard', …)` placeholder in `routes/web.php` is gone (replaced by the new controller route in `routes/sao.php`).

**Document download endpoint (9.6):** `App\Http\Controllers\Applications\DocumentDownloadController` (single-action `__invoke`) backs the new `application.documents.download` route at `/applications/{application}/documents/{document}/download` with `scopeBindings()`. Authorization: applicant owner OR SAO/Admin. Mismatched application/document pair returns 404 (not 403, since neither party owns it). Streams via `Storage::disk('local')->download(...)`. Both the applicant `Show.vue` (Phase 8 deferred this) and the SAO `Review.vue` link to the same route.

**Inertia pages (9.7):**
- `dashboards/Sao.vue` — replaces the Phase 3 placeholder. Two `Card`s: pending review (counts of Submitted/UnderReview/DocumentsRequested), decided (counts of Admitted/Rejected/Waitlisted/Withdrawn). CTA links to `sao.applications.index`.
- `sao/applications/Index.vue` — PrimeVue `DataTable` with `lazy` + `paginator`, server-side sort/page via `router.get(...)` reload + `preserveState: true, preserveScroll: true, replace: true`. PrimeVue `MultiSelect` for status filter; default selected matches the controller default. `onSort` handles PrimeVue's `string | function | undefined` `sortField` shape by short-circuiting non-string values (only `field`-bound columns are sortable, but the type signature allows function fields). Status `Tag` severity map identical to `Applicant.vue`. Wayfinder `ApplicationReviewController.show(id).url` for "Review" links.
- `sao/applications/Review.vue` — top: back-to-queue link + status `Tag`. Sections: applicant card (name/email/contact/phone/dob/previous institute), prior-history `Message` (warn severity, only if prior profiles or applications exist; ships a Select of trashed profiles + "Restore prior enrollment" + "Admit as new student" CTAs that scroll the Decide form into view and pre-tick the `acknowledged_prior_history` checkbox), programme card, documents `DataTable` with per-row Download button using `application_routes.documents.download({application, document}).url`. Decision area: if `is_terminal` show a read-only summary card; otherwise a 2-col grid with a Triage form (interim status `Select` + notes textarea) and a Decide form (admit/reject/waitlist `Select` + notes textarea + acknowledged-prior-history checkbox when prior history exists). Notes-required hints render in red beside the label. All forms use `useForm` + `preserveScroll: true`; submit URLs come from Wayfinder `ApplicationReviewController.{triage,decide,restorePrior}(id).url`.
- HTTP-client choice unchanged — Wayfinder action URLs + `useForm` for posts, `<Link>` for GETs.

**Login UI cosmetic (9.8):** `resources/js/pages/auth/Login.vue` placeholder updated from `stm-2026-001` to `stm-2026-0001` to match the matricule format the generator now produces (`%04d` zero-pad).

**Tests (9.9) — 55 new Pest cases, 327/327 green:**
- `tests/Feature/Sao/AuthorizationTest.php` (19) — guests redirect, non-SAO/admin roles 403 (data-driven over `RoleName::cases()` minus SAO/Admin), roleless 403, SAO and Admin both reach the dashboard. Split unbound vs bound endpoints because `SubstituteBindings` runs before `role:` middleware in the `web` group.
- `tests/Feature/Sao/SaoDashboardTest.php` (2) — status counts, every enum case has a key.
- `tests/Feature/Sao/ApplicationQueueTest.php` (6) — default filter shows actionable trio, query-string status filter, unknown status 422, non-whitelisted sort field 422, custom rows-per-page paginates, `statusOptions` carries every enum case.
- `tests/Feature/Sao/ReviewApplicationTest.php` (3) — show payload shape, prior-history surfacing for trashed profile + decided prior application, `is_terminal` flag.
- `tests/Feature/Sao/TriageApplicationTest.php` (5) — Submitted→UnderReview + StatusChanged audit, notes-required for DocumentsRequested, notes persisted, terminal application refused, terminal target refused.
- `tests/Feature/Sao/DecideApplicationTest.php` (8) — admit creates StudentProfile (`stm-2026-0001`) + Student role + ApplicationDecided audit + RoleAssigned audit + dispatches ApplicationDecided event; sequential matricules within a year (`0001` then `0002`); reject doesn't create profile; notes-required for reject/waitlist; Withdrawn refused at validation; terminal application refused; `acknowledged_prior_history` flows into audit context. Carbon test-now pins the year to 2026 so matricule assertions are deterministic.
- `tests/Feature/Sao/RestorePriorEnrollmentTest.php` (5) — happy path (trashed→active, role assigned, current Withdrawn with merge note, three audit rows, event dispatched), refuses non-trashed prior, refuses cross-applicant prior, refuses against terminal current, missing prior_profile_id 422.
- `tests/Feature/Applications/DocumentDownloadTest.php` (7) — owner downloads, SAO downloads, admin downloads, other applicant 403, non-SAO/admin staff 403, mismatched application/document 404, guest redirected.
- `tests/Pest.php` — `Feature/Sao` joins the `RolesSeeder` `beforeEach` group.

**Audit:** `vendor/bin/pint --dirty --format agent` ✓, 327/327 Pest green, `php artisan migrate:fresh --seed` ✓, `php artisan route:list --except-vendor` shows 36 routes (29 → 36: 6 SAO routes + document download), `npm run build` ✓, `npm run types:check` ✓ (added explicit `FormDataConvertible` + `DataTablePageEvent`/`DataTableSortEvent` typings on Index.vue and a non-string `sortField` short-circuit), `npm run lint:check` ✓ (one auto-fix pass for import-order + curly braces), `npm run format:check` ✓.

**Cross-phase contracts honored / deferred:**
- ✅ Reactivation slice complete: Phase 3's `CreateNewUser` reactivation + Phase 9's `RestorePriorEnrollment` form a coherent flow. A returning student re-registering with the same email gets restored to roleless (Phase 3), and the SAO can either re-attach the prior profile (RestorePriorEnrollment, current app → Withdrawn) or admit them as fresh (DecideApplicationAction issues a new matricule). The `MergedIntoPriorEnrollment` vs `Withdrawn` open question from Phase 8 was settled in favor of `Withdrawn` + a `merged_into_prior` audit-context flag.
- ✅ Document download route shared by applicant + SAO/admin per the Phase 8 deferred contract.
- ✅ Reserved enum cases used per spec: `StatusChanged` (triage), `ApplicationDecided` (decide + merge), `RoleAssigned` (admit + merge), `Restored` (auto via trait on prior profile).
- ⏳ Email/in-app notification sends remain stubs — the `ApplicationDecided` event is dispatched but unhandled (no listeners). Phase 10 or a follow-up can wire mail/in-app channels onto this single hook.
- ⏳ **Phase 10** reuses the Phase 9 restore mechanism for staff accounts via the admin user-management page, and surfaces the audit log via a paginated/filterable modal off the Admin dashboard. Also: surface trashed reference rows so admins can `restore()` them (Phase 4 deferred); decide whether `User` itself should opt into `RecordsAudit` for profile-settings changes; consider retrofitting the four Phase 4 reference models with `RecordsAudit` (one line each) since the audit modal will want to display those events too.

### Phase 10.1 — Admin Dashboard Backend + Audit Log API (`359ed1f`)

**Reference-model audit retrofit (10.1.1):** `Department`, `ProgramOffering`, `DocumentType`, `LevelCredentialRequirement` now `use RecordsAudit, SoftDeletes` so their CRUD events flow into `audit_logs` (Phase 5 contract). One-line trait additions per model — closes the Phase 4 deferred retrofit.

**Admin dashboard controller (10.1.2):** `App\Http\Controllers\Admin\DashboardController` (single-action `__invoke`) replaces the Phase 1 `Route::inertia('admin/dashboard', …)` placeholder. Ships `totals.{users, applications, student_profiles}`, `usersByRole` (ordered by `RoleName::cases()`), `applicationsByStatus` (ordered by `ApplicationStatus::cases()`), and `recentAdmissions` (top 5 `StudentProfile` with eager-loaded user + offering.department). The placeholder `dashboards/Admin.vue` page wasn't touched here — Phase 10.2 rebuilds it to consume these props.

**Audit log JSON endpoint (10.1.3):** `App\Http\Controllers\Admin\AuditLogController@index` — JSON endpoint (NOT Inertia) for the modal. Server-side paginated `audit_logs`, filterable by `user_id`, `actions[]` (AuditAction values), `subject_types[]` (**short class names** like `Application`, `Department` — translated to FQCNs via `AuditLogIndexRequest::SUBJECT_TYPES` since no app-wide morphMap is configured), `from`/`to` date range. Sort fixed on `occurred_at` (asc/desc); secondary sort on `id` for stable pagination. Eager-loads actor `user:id,name,email`. Response payload: `{data: [...], meta: {current_page, last_page, per_page, total}, options: {actions, subject_types}}` so the modal can populate its own filter dropdowns from the same call.

**Form Request (10.1.4):** `App\Http\Requests\Admin\AuditLogIndexRequest` owns `SUBJECT_TYPES` whitelist (Application, ApplicationDocument, StudentProfile, User, Role, Department, ProgramOffering, DocumentType, LevelCredentialRequirement) + the validation rules; `subjectFqcns()` helper translates short names → FQCNs.

**Routes (10.1.5):** `routes/admin.php` restructured — wrapped under a single `admin/` prefix + `role:admin` group hosting `admin.dashboard`, `admin.audit-logs.index`, and a nested `references.` sub-group with the existing 16 reference routes. Total routes 36 → 38.

**Tests (10.1.6) — 32 new Pest cases, 359/359 green:**
- `AdminDashboardTest` (5) — role + status totals, recent-admissions limit, every enum case present, zero-state, soft-deleted users excluded from `totals.users`.
- `AuditLogIndexTest` (9) — payload shape, filters by user_id / actions / subject_types / date range, pagination, options.actions completeness, 422 on unknown subject_type and reversed date range.
- `AdminAuthorizationTest` (14) — guest redirect + non-admin role 403 + roleless 403 across `admin.dashboard` and `admin.audit-logs.index` (dataset-driven).
- `ReferenceAuditingTest` (4) — Created/Updated/Deleted/Restored audit rows for the four retrofitted models (one model per case).

**Why JSON for the audit endpoint, not Inertia partial reload:** the modal lives on the dashboard page and needs frequent filter-driven refreshes. Inertia partial reload (`router.reload({only: [...]})`) would force a page-state diff each time and serialize the whole props tree. A plain JSON endpoint matches the pattern Phase 8 already established for the cascading-dropdown lookups in `routes/web.php` (`api/v1/program-offerings`, `api/v1/level-requirements`).

### Phase 10.2 — Admin Dashboard UI + Audit Log Modal (`9a664da`)

**`AuditLogModal` component (10.2.1):** New `resources/js/components/admin/AuditLogModal.vue` — encapsulates the entire modal. Receives `:visible` v-model and emits `update:visible`. PrimeVue `Dialog` (modal, max-width 1200px) hosts a 5-column filter grid (`InputNumber` actor, `MultiSelect` actions + subject_types with `filter` enabled, two `DatePicker`s for from/to), a Clear/Refresh action row, an inline `Message` for fetch errors, and the lazy/server-side `DataTable`. The DataTable is sortable on `occurred_at` (asc/desc), uses `expanded-rows` v-model with a `<Column expander />` to show changes/context JSON in `<pre>` blocks. Action labels are mapped from `options.actions` and colored via `actionSeverity()` (login_failed → danger; created/restored/role_assigned/application_decided → success; updated/status_changed → info; deleted → warn; default secondary). Filter options are loaded once from the first response and cached locally; subsequent reloads only fetch rows. Empty actor uses literal label "System / anonymous"; missing subject shows "—".

**`fetch()` plumbing (10.2.2):** Same pattern as Phase 8's cascading dropdowns — plain `fetch()` with `credentials: 'same-origin'` + `Accept: application/json`. `URL.searchParams.append('actions[]', value)` is used for array params so Laravel's request → array conversion works without bracketed string keys. Wayfinder `auditLogs.index.url()` provides the base URL.

**Rebuilt `dashboards/Admin.vue` (10.2.3):** Replaces the Phase 1 placeholder. Sections from top:
1. Header card — "Administrator" title with `[Open audit log]` Button on the right.
2. Three tile cards in a row — totals.users / totals.applications / totals.student_profiles with iconography (Users / FileText / GraduationCap).
3. Two cards in a row — Users by role + Applications by status, both rendering the `[{role/status, label, count}]` arrays as a one-line-per-row list (`flex justify-between` with monospace counts).
4. Recent admissions card — `DataTable` with matricule (mono), student (name + email), programme (department + degree label · L{level}), academic year, enrolled date. Empty-state message included.
5. Two shortcut cards — "Reference data" (Link to `admin.references.index`) + "Audit log" (Card with description and `[Open audit log]` button in the footer).
6. `<AuditLogModal v-model:visible="auditModalVisible" />` mounted at the page root.

**Per-page imports:** `Card`, `MultiSelect`, `Tag`, `DatePicker`, `Message`, `InputNumber` are imported per-page (not in the globally registered list in `resources/js/app.ts`). `DataTable`, `Column`, `Dialog`, `Button` come from the global registration.

**No backend changes:** Phase 10.2 is purely the frontend on top of Phase 10.1's controllers. No new routes, no new tests (the JSON endpoint and dashboard render are already covered by `AuditLogIndexTest` (9) + `AdminDashboardTest` (5)). At the time, true Pest 4 browser tests (`visit()`) required a Playwright + `tests/Browser` setup the project lacked, so the Phase 10 plan's "browser test" item was satisfied by HTTP-level coverage. **Superseded by AUD-029 (§15, Fix Phase 6):** a minimal Pest 4 browser smoke suite now exists under `tests/Browser` — the same applies to the Phase 2 (line ~255) and Phase 8 (line ~388) "browser test" items.

**Audit:** `vendor/bin/pint --dirty --format agent` ✓, 359/359 Pest green, `npm run build` ✓ (added per-page chunks for `multiselect`, `datepicker`), `npm run lint:check` ✓, `npm run format:check` ✓ (one autofix pass on the two new Vue files), `npm run types:check` shows no new errors in the two added files (the pre-existing `.form` property errors on starter-kit auth/settings pages are unchanged from `master`), `php artisan route:list --except-vendor` still 38 routes.

**Cross-phase contracts honored / deferred:**
- ✅ Audit log modal lands per the §8 deliverable (Dialog + lazy DataTable + actor/action/subject/date filters).
- ✅ Admin dashboard surfaces summary tiles + reference shortcut + recent admissions table.
- ⏳ Soft-deleted reference rows (Phase 4 "Design decision" deferral) — not surfaced; admins can't restore trashed `Department`/`ProgramOffering`/etc. from the UI yet.
- ⏳ Staff user-management page (re-uses Phase 9 restore mechanism for staff accounts) — out of Phase 10.2 scope per the agreed split.
- ⏳ `User` opting into `RecordsAudit` for profile-settings changes — still deferred; auth events already cover login lifecycle, this is purely about settings mutations.
- ⏳ Notification listeners on `ApplicationDecided` event — still stubs.
- ⏳ Per-role sidebar/navigation polish — still deferred.
- ⏳ Shared status/severity/degree label helpers — currently duplicated across `Applicant.vue`, `sao/applications/Index.vue`, and the new `AuditLogModal.vue` (action map only). Consolidate when one of these pages is otherwise being modified.

### Phase 10.2 follow-up — Default admin seeder (`1f8ae87`)

Small DX retrofit landed alongside the Phase 10.2 browser-check session. `database/seeders/DatabaseSeeder.php` now provisions an `admin@example.com` / `password` user with the Admin role and `email_verified_at = now()`, idempotent via `firstOrCreate`. This makes `php artisan migrate:fresh --seed` yield a loginable admin out of the box for browser-checking the new dashboard. The pre-existing `Test User` factory call is retained. Tests are unaffected (they explicitly call `RolesSeeder` in `tests/Pest.php` `beforeEach`, never `DatabaseSeeder`); 359/359 still green.

### Admin User Management module (`ac997ac` → `e99fc2e` → `f46c02c`, post-roadmap)

Shipped after Phase 10 as a three-commit module (backend / UI / polish). Scope: admin-provisioned **staff + admin** accounts only (students arrive via admission, applicants via self-registration). Key contracts: invite-link credentials (no password set by the admin — a password-reset-style setup link is mailed via queued `UserInvitationMail`), `CreateUserAction` + `ChangeUserRoleAction` own all writes (incl. `WritesRoleProfile::writeProfile()` restore-or-create for per-role profiles), users DataTable with role/status/search filters, Edit page with role-aware profile forms + change-role dialog + deactivate/restore/resend-invite. Full design contracts live in the `project_admin_user_management.md` memory — **read it before touching `CreateUserAction`/`ChangeUserRoleAction`.** Audit fix phases later layered onto this module: `employee_id` capture (AUD-007), `User` auditing via `RecordsAudit` (AUD-022), shaped Edit props (AUD-031), `RoleName::label()` dropdown labels (AUD-027).

### Roadmap status

Phases 1–10 plus the user-management module are all shipped; the §8 design contract is fully satisfied. `php artisan route:list --except-vendor` currently shows **54 routes** (38 at Phase 10; growth = 9 user-management routes, 4 reference-restore routes from AUD-021, and small interim additions). Most of the deferred follow-ups listed under the per-phase "Cross-phase contracts honored / deferred" sections were since closed by audit fix phases: reference restore UI (AUD-021), `User` `RecordsAudit` (AUD-022), `ApplicationDecided` notification listener (AUD-002), shared status/label helpers (AUD-027), staff user management (the UM module). Still genuinely deferred: per-role sidebar/navigation polish (B13) and the B1–B15 backlog in AUDIT.md. No Phase 11 is planned — when a deferred item becomes urgent, design it in a fresh planning pass.

### Process reminder

Per `feedback_phased_implementation.md`: never start phase N+1 until phase N's audit checklist is green and the commit exists. Update this section + the `project_implementation_progress.md` memory together at every phase-boundary commit.

---

## 15. Global Audit & Remediation (started 2026-06-11)

**Session date:** 2026-06-11. A full security/performance/gap/quality audit of the codebase at `e044f81` was run via four parallel domain agents (methodology captured in the global `codebase-audit` skill at `~/.claude/skills/codebase-audit/`; reusable `security-auditor` + `performance-auditor` agent definitions at `~/.claude/agents/`).

**Artifacts (committed in `f460901`):**
- **`AUDIT.md` (repo root) — the single source of truth for remediation.** 34 findings (1 Critical, 8 High, 16 Medium, 9 Low), each formatted as a ready-to-open GitHub issue with severity, location, problem, proposed solution, and acceptance criteria. Per-finding `Status:` lines are updated to `Fixed in <sha>` as fixes land. Ends with a 15-item deferred-work backlog table (B1–B15) and a 6-phase suggested fix order.
- `plan/audit/{security,performance,gap,quality}-findings.md` — the raw domain reports (SEC-n / PERF-n / GAP-n / QUAL-n IDs cross-referenced from AUDIT.md).

**Remediation status (update at every fix-phase commit):**

| Fix phase | Findings | Status | Commit |
|---|---|---|---|
| 1 — Core flow correctness | AUD-001, 010, 003, 005, 006 | ✅ Done | `1956bf2` |
| 2 — Quick wins | AUD-009, 015, 016, 019, 008, 030, 012 | ✅ Done | `fa56b44` |
| 3 — Auth hardening | AUD-004, 017, 028, 011, 025 | ✅ Done | `512a97c` |
| 4 — Feature gaps | AUD-002, 007, 021, 024, 022 | ✅ Done | `a93f9ba` |
| 5 — Structural cleanups | AUD-018, 020, 027, 031, 013, 014, 023 | ✅ Done | `e1255e3` |
| 6 — Docs & throttles | AUD-033, 032, 029, 026, 034 | ✅ Done | `ea3b426`, `62e86ff`, `55778e6` |

**Fix Phase 1 (`1956bf2`) — what changed:**
- All three SAO actions (`Decide`, `Triage`, `RestorePriorEnrollment`) re-fetch the application — and the prior profile, where relevant — under `lockForUpdate()` *inside* their transactions and re-run the status guards there, so concurrent decisions 422 instead of corrupting state (AUD-001).
- `Application::canTransitionTo()` now encodes the full matrix: **Draft → Submitted only**; interim → interim/terminal; terminal → nothing. Decide + restore-prior route through it (AUD-010). New public `Application::OPEN_STATUSES` (Draft + interim trio).
- `promoteToStudent()` restore-or-creates the `StudentProfile` (unique `user_id` includes trashed rows): trashed → restore + **fresh matricule** ("admit as new"); active → enrollment fields update but **matricule never changes** (it's a login identifier). `RoleAssigned` audit guarded for already-Students (AUD-003).
- One open application per applicant: `StoreApplicationRequest::after()` + an in-transaction re-check under a per-user `lockForUpdate` on the `users` row (a per-user mutex with no gap-lock risk). Re-applying after any terminal decision remains allowed (AUD-005).
- Matricule generation now uses a `matricule_sequences (year PK, last_number)` counter table (new migration `2026_06_11_120000`), lazy-seeded from the highest already-issued number per year; query-builder only, no Eloquent model, no timestamps. Constant lock scope, immune to force-deleted profiles (AUD-006).
- 8 new Pest cases (returning-applicant admit, active-profile admit, Draft refusals on decide+triage, concurrent-finalize 422, force-delete sequence survival, duplicate-submit 422, re-apply-after-decision). **388/388 green**, Pint clean, `migrate:fresh --seed` ✓.

**Fix Phase 2 (`fa56b44`) — what changed:**
- `ApplicationController::store()` writes uploads to disk *before* opening the transaction (collecting metadata rows), then deletes the stored files in a `catch` if the transaction rolls back — no filesystem I/O inside the transaction, no orphans on failure; covered by a forced-rollback test via an `Application::created` hook (AUD-009).
- `Create.vue` formats `date_of_birth` from local date components (`toLocalDateString()`) instead of `toISOString()`, fixing the one-day-early shift for UTC+ applicants; the submission test asserts the literal stored date (AUD-015).
- `Create.vue` cascading lookups extracted into `loadOfferings()`/`loadLevelRequirements()` with `catch` + inline error `Message` + Retry button, mirroring the AuditLogModal pattern (AUD-016).
- `applications` migration (edited in place): composite `(status, submitted_at)` + `(user_id, status)` replace the single-column status/submitted_at indexes (AUD-019). Caveat: a multi-status `IN` still filesorts the index-filtered subset (MySQL limitation); single-status queries are sort-free.
- `audit_logs` migration: composite `(occurred_at, id)`, `(user_id, occurred_at)`, `(action, occurred_at)`, `(subject_type, occurred_at)`; single-column indexes dropped; `paginate()` kept since the modal consumes `total`/`last_page` (AUD-008).
- `DocumentDownloadController` downloads from the default disk (`Storage::download()`), matching the upload path's `$file->store()` (AUD-030).
- `DatabaseSeeder`'s known-credential accounts (`test@`/`admin@example.com`) now gated to local/testing — `LocalStaffSeeder` already was; new `DatabaseSeederTest` asserts production seeding creates zero users (AUD-012).
- **391/391 green**, Pint clean, `npm run build` ✓, `migrate:fresh --seed` ✓.

**Fix Phase 3 (`512a97c`) — what changed:**
- **Reactivation policy rewritten (§13 above, AUD-004):** `/register` never touches trashed rows (identical 422 to active emails); reactivation moved to the password-reset flow — `PasswordBrokerUserProvider` (new, broker-only, `users-with-trashed` provider in config/auth.php) includes trashed non-staff users, and `ResetUserPassword::reactivate()` restores + detaches roles once the token proves mailbox ownership. Trashed staff/admin filtered out — admin restore only. The 4 old-policy RegistrationTest cases were replaced by new-policy cases (register-side) + 4 reset-side reactivation cases.
- `RoleRevoked` audit rows (one per detached role, `reactivated: true` context) written during reactivation alongside the `Restored` row (AUD-028).
- Concurrent duplicate registration: `UniqueConstraintViolationException` caught and re-thrown as the standard email-taken 422; tested via a `creating`-hook race simulation (AUD-017).
- Rate limiters (AUD-011): `register` 5/min/IP, `forgot-password` 3/min per email+IP (also on reset-password), `verification` 3/min per user (wired via `fortify.limiters.verification`). Register/forgot/reset throttles attach in an `app()->booted()` callback in `FortifyServiceProvider` — `refreshNameLookups()` is required there because the route-name table predates Fortify's chained `->name()` calls.
- Login resolver collapsed to one query (OR across email/employee_id + matricule EXISTS) with a dummy bcrypt check on the not-found path to flatten timing (AUD-025); query count asserted in test.
- New `RoleName::staff()` helper (single source for the staff-role set — AUD-027 will migrate the two hand-written copies).
- **399/399 green** (+8 net new tests), Pint clean, `migrate:fresh --seed` ✓.

**Fix Phase 4 (`a93f9ba`) — what changed:**
- Applicants are finally notified: a queued `SendApplicationDecisionNotification` listener (auto-discovered) handles `ApplicationDecided` and sends `ApplicationDecisionMail` to `application->contact_email` with per-decision copy — admitted (incl. matricule), rejected/waitlisted (incl. decision notes), and a restore-prior merge variant (incl. the restored historical matricule). Exactly one mail per terminal decision; triage moves send nothing (AUD-002).
- `employee_id` is now writable: optional field on the admin Create/Edit user forms, normalized lowercase in the Form Requests, unique, format-guarded (`no @`, no `stm-` prefix) so it can never shadow the email/matricule login namespaces; persisted via `forceFill` (stays out of `$fillable`), shown + searchable in the users DataTable. Employee-ID login works end-to-end from day one (AUD-007).
- All four reference CRUDs (departments, offerings, document types, level requirements) gained a "Show deleted" toggle (`?trashed=1`) and a Restore action (`POST .../restore`, `withTrashed()` binding). Restore refuses while a parent row is trashed; key-conflict guards were deliberately **not** added because every reference unique index spans trashed rows — a re-take is impossible at the DB level (AUD-021).
- `User` now uses `RecordsAudit`; the trait gained `auditRedact()` (password, 2FA secrets) — redacted keys appear in Updated diffs as `[redacted]` and are omitted from snapshots. `CreateUserAction` collapses to a single save (one auto Created row, manual row removed); `ResetUserPassword::reactivate()` uses `restoreQuietly()` so its contextual manual `Restored` row stays the only one. Admin restore/deactivate and registration are now audited for free (AUD-022).
- Dashboards de-placeholdered: Student gets a real controller (enrollment summary — matricule, programme, level, year, status — plus application history with null-safe offering shaping); Lecturer/Accountant get profile cards + honest "module coming soon" states; starter-kit repo/docs links removed from sidebar + header and `AppLogo` rebranded (AUD-024).
- New routes use invokable `App\Http\Controllers\Dashboards\*DashboardController` classes replacing the `Route::inertia` placeholders.
- **422\422 green** (+23 net new tests across 5 new files), Pint + ESLint + vue-tsc clean, `npm run build` ✓.

**Fix Phase 5 (`e1255e3`) — what changed:**
- Role checks de-duplicated (AUD-018): `hasRole`/`hasAnyRole` answer from the loaded `roles` relation when present (enum-safe `contains`), falling back to EXISTS; `assignRole`/`removeRole` unset the cached relation. `HandleInertiaRequests::share()` (which Inertia resolves *before* route middleware) and `RoleDashboardResolver::pathFor()` (login happens mid-request, after share saw a guest) each `loadMissing('roles')`. New `RoleQueryEfficiencyTest` asserts exactly one `role_user` query per authenticated page load.
- PrimeVue globals removed (AUD-020): all 8 globally-registered components converted to per-page imports (17 files); only `ToastService` + `tooltip` directive stay app-level; `<Toast />` imported by the layout that mounts it. Main chunk **936.29 kB → 451.06 kB** (gzip 208.02 → 106.49 kB); `chunkSizeWarningLimit` override deleted so the default 500 kB warning is the regression guard. CLAUDE.md UI section updated to the new convention.
- Duplication collapsed (AUD-027): new `resources/js/lib/statusDisplay.ts` is the single site for application-status/enrollment-status/degree/role labels + Tag severities (9 pages refactored; fixed Student.vue's stale `excluded` enrollment key — the real enum value is `withdrawn`). `Application::TERMINAL_STATUSES`/`INTERIM_STATUSES` made public and now drive `TriageApplicationRequest`, `DecideApplicationRequest` (terminal minus Withdrawn), and the SAO queue's default filter. The triplicated `levelWithinOfferingRange()` closure became the shared `App\Rules\LevelWithinOfferingRange` (`DataAwareRule`). `RoleName::label()` feeds both `UserInvitationMail` and the admin role dropdowns ('Administrator'/'SAO' style).
- Props shaped (AUD-031): shared `auth.user` now exposes only id/name/email/timestamps (+`email_verified_at`); admin users Edit ships shaped lecturer/accountant/sao profile arrays (`hired_at` as date string) instead of raw models.
- Trashed-offering hardening (AUD-013): `ProgramOffering::applications()` added; destroy refuses while applications reference the offering; `Application::programOffering()` and `StudentProfile::programOffering()` resolve `withTrashed()` so drifted historical data renders instead of 500ing (tested by trashing an offering under a submitted application).
- NID/BIRTH protected (AUD-014): `DocumentType::PROTECTED_CODES` is the single source for the always-required pair (StoreApplicationRequest + the form's lookup reference it); destroy and code-rename of protected types are refused (name edits stay allowed).
- Level-range narrowing guarded (AUD-023): `ProgramOfferingUpdateRequest::after()` rejects a narrowed `[min_level, max_level]` that would orphan credential-requirement levels or strand open (Draft/interim) applications, naming the blocking levels; decided applications don't block; widening is unrestricted.
- **433/433 green** (+11 net new tests), Pint + Prettier + ESLint + vue-tsc clean, `npm run build` ✓.

**Stale-doc note resolved (AUD-033, 2026-06-12):** §14 was refreshed — status table now covers Phase 10 + the UM-A/B/C user-management module, carries a supersession note pointing here, and records the current 54-route count; CLAUDE.md's Database section now describes the real route layout (no `routes/api.php`). `git log` + `AUDIT.md` remain authoritative for remediation state.

**Fix Phase 6 (in progress, 2026-06-13) — what changed:**
- Applicant dashboard bounded (AUD-032): `ApplicationController::dashboard` now caps the query at `MAX_DASHBOARD_APPLICATIONS = 50` (the one previously-unbounded `->get()` on a growing table). The prop stays a flat array — the PrimeVue DataTable already paginates client-side — so no frontend change. A realistic applicant holds a handful of applications; 50 is a safety ceiling, not a UX limit.
- **Audit-log retention policy decided (AUD-032):** audit records are retained **2 years** (`AuditLog::RETENTION_DAYS = 730`), then removed by the new `audit:prune` artisan command. The command deletes via the query builder (`DB::table(...)->where('occurred_at', '<', $cutoff)->delete()`) **by design** — the `AuditLog` model's `deleting` guard blocks Eloquent deletes to keep records immutable, and the prune is the one sanctioned bypass. `--days` overrides the horizon (must be ≥ 1). Registered on the scheduler in `routes/console.php` as a daily `withoutOverlapping()` job; it only does work where a scheduler runs (production), and is a no-op locally. Retention can be revisited at deployment scale (cross-ref AUD-034 hardening baseline).
- **Pest 4 browser smoke suite added (AUD-029):** the browser-test promises in Phases 2/8/10 (previously waved through as "HTTP coverage suffices") are now real. `pestphp/pest-plugin-browser` (composer dev, needs `ext-sockets`) + `playwright` (npm dev) back a `tests/Browser/SmokeTest.php` with 3 `visit()` tests: login renders + signs a user in, the applicant application form renders without JS errors (the cascading-dropdown surface that AUD-015/016 slipped through), and the admin audit-log modal opens. `tests/Pest.php` binds `RefreshDatabase`/`TestCase` + role seeding to the `Browser` group; `phpunit.xml` gains a `Browser` testsuite; `tests/Browser/Screenshots` is gitignored. CI: the `tests.yml` matrix job now runs `--testsuite=Unit,Feature` (fast, no Playwright), and a **separate `browser` job** installs Chromium (`npx playwright install --with-deps chromium`) and runs `--testsuite=Browser`, isolating browser flakiness from the feature matrix. Local fast loop: `php artisan test --testsuite=Feature`; browser run needs `npx playwright install chromium` once.
- **Endpoint throttling added (AUD-026):** the three authenticated-but-unthrottled JSON/download surfaces now carry per-user rate limiters defined in `FortifyServiceProvider::configureRateLimiting()`. `throttle:lookups` (60/min, keyed by user id) guards the `api/v1` cascading-dropdown group and the document-download route; `throttle:audit-logs` (30/min — tighter, it reads the growing audit table) guards the admin audit-log modal endpoint. Ceilings are generous enough that normal UI flows never trip them; past threshold the endpoints 429. `tests/Feature/EndpointThrottleTest.php` asserts each limiter via the `RateLimiter::increment(md5(...))` pattern used by `AuthThrottleTest`.
- **Production hardening baseline documented (AUD-034):** see §16. `.env.example` gained a header pointer to the checklist and a commented `SESSION_SECURE_COOKIE=false` knob (local default; true in prod).

**Merged to `main` + PHP floor corrected (2026-06-13, `d77cd56`):** the full audit-remediation line (18 commits, `e044f81..50954f0`) was pushed and merged into `main` via PR #52. The merge surfaced a CI bug in the AUD-029 `tests.yml`: its matrix ran a PHP **8.3** leg, but the locked `symfony/error-handler v8.0.8` requires PHP ≥ 8.4, so `composer install` could never succeed on 8.3. Fix: matrix narrowed to `['8.4', '8.5']`, `composer.json` `php` constraint bumped `^8.3 → ^8.4` (the project already targets 8.4 per CLAUDE.md/Boost), lock content-hash resynced via `composer update --lock`. **8.4 is now the hard floor** — do not re-add an 8.3 matrix leg.

## 16. Production deployment hardening baseline (AUD-034)

The project's `.env.example` carries local-development defaults (`APP_DEBUG=true`, plaintext cookies, `MAIL_MAILER=log`, `FILESYSTEM_DISK=local`). None of those are safe in production. Work through this checklist before exposing the app publicly; each item cross-references the audit finding that motivates it.

### Environment

- [ ] `APP_ENV=production`, `APP_DEBUG=false` — with debug on, an unhandled exception (e.g. the `QueryException` behind **AUD-003**) renders a stack trace that leaks schema, file paths, and env values to the visitor.
- [ ] `APP_KEY` set (a real generated key — `php artisan key:generate`), never the example blank.
- [ ] `APP_URL` set to the canonical `https://` origin.

### Transport & session security

- [ ] Serve over HTTPS only; redirect plaintext to TLS at the edge.
- [ ] `SESSION_SECURE_COOKIE=true` so the session cookie is never transmitted over http.
- [ ] Consider `SESSION_ENCRYPT=true` and a locked-down `SESSION_DOMAIN`.

### Queue worker (required, not optional)

- [ ] Run a durable queue worker (`php artisan queue:work` under a supervisor). Decision mail (**AUD-002**) and the user-invitation mail are **queued** — without a running worker, applicants and invited staff are never actually notified. `QUEUE_CONNECTION=database` is fine; just ensure the worker process exists.

### Scheduler

- [ ] Wire the Laravel scheduler (`* * * * * php artisan schedule:run`). The audit-log retention job `audit:prune` (**AUD-032**, 2-year horizon) only does work where a scheduler runs; it is a no-op locally.

### Storage / filesystem

- [ ] Decide `FILESYSTEM_DISK` deliberately. Uploads and downloads both resolve the **default** disk (**AUD-030**), so switching to `s3` is a single-knob change — but it must be set *before* any documents are stored, or historical downloads 404. If using `s3`, set the `AWS_*` credentials and bucket.

### Database seeding

- [ ] Never seed the known-credential demo accounts in production. The `DatabaseSeeder` already gates `test@`/`admin@example.com` and `LocalStaffSeeder` to local/testing (**AUD-012**); production `db:seed` creates zero users. Provision the first admin out-of-band.

### Mail

- [ ] Configure a real transactional mailer (`MAIL_MAILER`/`MAIL_HOST`/credentials); `log` swallows every outbound message.

### Rate limiting

- [ ] Already in code (**AUD-011**, **AUD-026**): auth flows and the `api/v1`/download/audit-log endpoints are throttled. No env action needed, but if running multiple app instances behind a load balancer, point the limiter at a shared store (`CACHE_STORE=redis`) so the buckets are global rather than per-instance.

**Acceptance:** this checklist exists and each item names the finding it closes — AUD-003, AUD-002, AUD-032, AUD-030, AUD-012, AUD-011, AUD-026.

## 17. Post-audit backlog (started 2026-06-13)

With all 34 audit findings Fixed, work moved to the B1–B15 feature backlog (open GH issues). **Shipped & merged to `main`** (PRs #54 etc.): the quick-polish set — **B10** per-user audit drill-down (`a434e2b`, #27), **B9** phone secondary login identifier (`950feb5`, #25), **B13** role-aware sidebar nav (`52aa2a2`, #34), **B12** self-service staff profile settings (`a48aa4c`, #33). B9/B12/B13 were drafted by parallel worktree sub-agents and integrated by the orchestrator (the agents are sandboxed from running the gate/committing — see memory [[feedback-parallel-subagent-integration]]).

**#6 Payment validation + tamper-proof school receipts (B1) — ✅ SHIPPED, merged to `main` via PR #56 (closes #6 + #16) 2026-06-13.** Full plan `plan/payments/plan.md`. Three phases: **P1** admin fee config (`1083c32`) — `FeeSchedule`/`FeeInstallment`, `admin.fees.*` CRUD; **P2** student slip-upload submission + accountant validation queue (`a06fc06`) — `PaymentSubmission`, `routes/student.php` + `routes/accountant.php`, `ReviewPaymentAction`; **P3** HMAC receipts + public verification (`fecdc5e`) — immutable `SchoolReceipt` (HMAC over `receipt_number|matricule|amount_xaf|academic_year` keyed by APP_KEY), public `receipts/verify/{number}`, printable QR receipt. Money is integer XAF. New dep: `qrcode` (approved).

**#8 Tuition deferral + payment-standing access gating (B2) — ✅ SHIPPED, merged to `main` via PR #57 (closed #8) 2026-06-14.** Full plan `plan/exam-gating/plan.md`. Three phases: **G1** standing engine + deferral model (`a6f7741`) — `PaymentStandingService` (the gate rule), `TuitionDeferral`, `DeferralStatus`/`PaymentStanding` enums; **G2** request→approve flow (`a3ffada`) — student `student.deferrals.store`, accountant `accountant.deferrals.*` via `ReviewDeferralAction`; **G3** standing surfaces (`ff080c9`) — student standing badge + deferral CTA, staff standing-check `GET /standing`. Standing is informational (no enrollment change); deferral = student requests → accountant approves(deadline)/rejects; threshold driven by #6 installment due-dates.

**#11 Course management (B3) — ✅ SHIPPED, merged to `main` via PR #58 (squash `c177bb9`, closed #11) 2026-06-15.** Full plan `plan/course-management/plan.md`. Locked design: implicit cohort membership (student ↔ course by matching `program_offering_id` + `level` + `academic_year`; no enrollment table); full vertical, one PR closing #11; assignments include file upload + grade; **native-browser inline file viewer (no new dep)** built as shared phase C0 and retrofitted onto payment slips + admission documents. Phases **C0** shared inline viewer → **C1** course catalog + lecturer assignment + SAO plan approval (`approve-course-plan` gate) → **C2** attendance (`mark-attendance` gate) → **C3** assignments (file upload + grade) → **C4** CA + exam results + SAO publish (`publish-results` gate) + student view + disputes. Built via **parallel specialized sub-agents** (`laravel-backend-drafter` + `vue-inertia-drafter` + `pest-test-drafter`, created in `~/.claude/agents/`) per [[feedback-parallelism-subagents]] + the integration recipe in [[feedback-parallel-subagent-integration]].

Shipped on-branch: **C0** (`88391d8`) shared `FileViewerDialog.vue` + single-action view controllers; **C1** (`370deb7`) catalog/assignment/plan-approval; **C2** (`327a173`) sessions + cohort attendance — `CourseSession`/`AttendanceRecord`, `SessionStatus`/`AttendanceStatus`, `MarkAttendance` action (lockForUpdate + Approved re-guard + cohort restrict + `updateOrCreate` + audit), scope-bound `lecturer.courses.sessions.*`, `student.attendance.index`. **Security follow-up (`b757458`):** the C0 inline viewers were hardened against stored XSS — stored-MIME allowlist (pdf/png/jpeg → 415 otherwise), forced `Content-Type` + `X-Content-Type-Options: nosniff` + sandbox CSP, CR/LF-stripped filename (replaces `addslashes`). **C3** (`8236b0f`) assignments — `Assignment`/`AssignmentSubmission`, `AssignmentSubmissionStatus`, cohort-only one-submission-per-student upload (late flag; resubmit replaces file with out-of-tx cleanup per AUD-009), inline view + download + grade (`GradeSubmission`, score ≤ `max_score`); audit `AssignmentCreated`/`AssignmentSubmitted`/`AssignmentGraded`. **C4** (`2002c45`) results + disputes — `CourseResult` (computed `final_score` = `round(0.3·CA + 0.7·exam)` via private `computedFinalScore()` to avoid accessor recursion; letter grade A≥80/B≥70/C≥60/D≥50/F; `CA_WEIGHT`/`EXAM_WEIGHT` consts) + `ResultDispute` (`DisputeStatus::isTerminal()`); `ResultStatus`/`DisputeStatus` enums; actions `Lecturer\RecordCourseResults` (cohort-filtered upsert, skips published + non-cohort rows), `Sao\PublishCourseResults` (`publish-results` gate, fully-scored drafts only, ValidationException if none), `ReviewResultDispute` (root `App\Actions`, lockForUpdate terminal-guard); student published-only view + one-open-dispute-per-result, SAO dispute-resolution queue; audit `ResultRecorded`/`ResultsPublished`/`DisputeRaised`/`DisputeResolved`. **C4 scoping:** dispute resolution is SAO/Admin-only (no lecturer surface); queued mail intentionally omitted (audit covers it); CA/exam each 0–100, weighted 30/70.

Three conventions to respect: course status enums emit **lowercase `->value`** (statusDisplay maps + all Vue `=== 'status'` comparisons aligned lowercase — C1 had shipped always-false TitleCase comparisons that disabled the SAO approve / lecturer submit controls); `app.ts` **lazy-loads the auth + settings layouts** (`defineAsyncComponent`) to keep the entry chunk under the 500 kB Vite warning; and (added during C3) the **role-aware nav lives in an async `AppSidebarNav.vue`** so every per-feature Wayfinder route barrel code-splits out of the entry chunk (entry held at ~445 kB through C3+C4) — do not move the nav route-barrel imports back into `AppSidebar.vue`, and do not revert the layout lazy-loads to eager imports. The `tests/Browser/SmokeTest` audit-modal test is load-sensitive (hard 5 s `assertSee`); flakes under heavy machine load, passes in isolation.

**#12 Lecturer absence notifications (B4) — ✅ SHIPPED, merged to `main` via PR #59 (squash `9d15eba`, closed #12) 2026-06-15.** Full plan + memory [[project-absence-notifications-12]]. When a lecturer cancels (declares absence for) or reschedules a **future, currently-scheduled** `CourseSession`, every active cohort student gets a queued **email + in-app (database) notification**. First use of **Laravel Notifications** (`CourseSessionChangedNotification`, `via() => ['mail','database']`, standard `notifications` table) instead of a bespoke Mailable — the reusable multi-channel pattern; dispatched via the existing Event→queued Listener convention (`CourseSessionChanged` → `SendCourseSessionChangedNotification`, fan-out to `Course::cohortStudents()` on the worker). Cancel stores an optional `cancellation_reason`; reschedule carries the previous time + new `AuditAction::CourseSessionRescheduled`. Guards: past/already-cancelled sessions and topic/duration-only edits send nothing. Student dashboard gains an in-app feed (per-item + mark-all-read; `student.notifications.read[.all]`); lecturer `Sessions.vue` cancel dialog takes the reason. Built via the 3 parallel drafters; gate green (688 tests).

**#18 Notification channel strategy (B6) — ✅ DECIDED + closed 2026-06-15** (closing comment on the issue). Strategy now documented: **email** = transactional 1:1 outcomes (the four existing Mailables, unchanged); **in-app (database)** = broadcasts/cohort fan-out + anything to surface in-app, via Laravel Notifications (established by #12); **SMS deferred**. Standard going forward: new notifications use `Illuminate\Notifications\Notification` (mail + database) through the Event→queued Listener convention. Out of scope (future issues if prioritised): SMS, per-user notification preferences, other-role feeds, a standalone notifications-centre page.

**#30 Bulk staff import (CSV) (B11) — ✅ SHIPPED, merged to `main` via PR #60 (squash `252a36a`, closed #30) 2026-06-16.** Admin uploads a CSV → **two-step preview** (server validates + per-row OK/error preview, writes nothing) → confirm imports valid rows, reports invalid. Stateless: confirm re-sends the file and the server re-validates at commit time. Every valid row goes through the existing **`CreateUserAction::execute()`** (so all admin-module contracts hold — see [[project-admin-user-management]]); roles limited to `CREATABLE_ROLES` (student/applicant rows are row errors). Validation mirrors `StoreUserRequest` (email-unique-incl-trashed; the employee_id regex is now the shared `StoreUserRequest::EMPLOYEE_ID_REGEX` const) + in-file dup detection + case-insensitive `department_code`→`department_id`. Native `fgetcsv` (no dep), downloadable template, 500-row cap, new `AuditAction::UsersImported`. `App\Actions\Admin\ImportStaffUsers`; `admin.users.import[.template|.preview|.store]`; `admin/users/Import.vue`. Gate green (694 tests).

**#39 Reference-data caching (B15) — ✅ SHIPPED, merged to `main` via PR #62 (squash `6166a84`, closed #39) 2026-06-17.** Store-agnostic read-through cache (`App\Services\ReferenceDataCache`) for the applicant cascading-dropdown reference reads (departments, offerings, level requirements, protected document types) — fixed enumerable keys, **no cache tags** (works identically on file/redis/array), 1-day TTL backstop, `flush()`. `ApplicationController` create()/offerings()/levelRequirements() read through it + filter in PHP; the 4 admin reference controllers `flush()` after every write; `DatabaseSeeder` flushes post-seed. Added `predis/predis` (pure-PHP Redis client — chosen over phpredis to avoid Windows DLL hassle). Local `.env` → `CACHE_STORE=redis` + `REDIS_CLIENT=predis` (Laragon runs Redis on :6379); `.env.example` default flipped `database`→`file`. Tests pin `CACHE_STORE=array` (phpunit.xml) so CI needs no Redis; suite 695 green.

**#20 Multi-role role-switcher (B7) — ✅ CLOSED as not-planned 2026-06-17.** No audience: the only real multi-role case (Applicant+Student) is already served by the union nav (`AppSidebarNav`), and multi-role *staff* — the case a switcher actually needs — can't exist since `ChangeUserRoleAction`/`CreateUserAction` enforce single-role. Reopen only if multi-role staff assignment is wanted (build the switcher *with* it, not standalone).

**STATUS (2026-06-18):** Entire substantive backlog complete. **#37** invite-flow Mailtrap walkthrough — ✅ done + **closed** (manual QA conclusive: admin-create → queued `UserInvitationMail` → Mailtrap → single-use setup link → password set → login → role-dashboard redirect, plus resend-invite token rotation, all verified end-to-end; no code change — it was a deferred manual-QA item, not a defect). **#39** shipped, **#20** closed not-planned. **#22** (shadcn→PrimeVue) is now **CLOSED not-planned (2026-06-22)** — login + register were migrated via the redesign pilot (§18, #63), and shadcn-vue/PrimeVue coexistence is sanctioned CLAUDE.md policy, so the remaining auth/settings pages convert *opportunistically when otherwise modified* rather than via a standing tracking issue (reopen only for a deliberate, scheduled full migration). The only open backlog issue is now **#71/B16** student transcript generation (§21). The SchuLyf redesign that drove §18 is itself complete (see below).

## 18. Frontend redesign — SchuLyf (started 2026-06-17)

Reskinning the app from the Laravel starter-kit default into a branded design system, run by the `senior-frontend-engineer` specialist (`~/.claude/agents/senior-frontend-engineer.md`) through **wireframes → mockups → integration**, PILOT-FIRST. Deliverables in `plan/frontend-redesign/` (`overview.md`, `wireframes/`, `prototype/`).

- **Brand:** name **SchuLyf**; primary = Tailwind **emerald** (emerald-600); neutral slate; Inter; rounded-xl cards. Tagline candidate "Your campus life, organized."
- **Pilot slice:** the auth + applicant funnel — app shell, login, register, applicant dashboard, application form. Lock the design language here, then roll out to the staff surfaces (SAO/admin/accountant/lecturer/student + feature pages).
- **Phases & deliverables:** wireframes (low-fi ASCII, no color) → mockups (styled, emerald realized; engage the `frontend-design` skill) → integration (wire real props/Wayfinder routes; the parallelizable phase — fan out frontend drafters).
- **Prototype phase (2026-06-17):** v1 ASCII wireframes (`wireframes/pilot-funnel.md`) → a navigable **static HTML/CSS/JS prototype** (`prototype/`, Tailwind Play + Lucide CDNs, throwaway) that locked the 4 decisions — **A** shared sidebar shell, **B** split brand/form auth, **C** dashboard status-summary chips, **D** 4-step application stepper. Owner validated and chose **global brand + rename-everywhere**.
- **Pilot ✅ SHIPPED — merged to `main` via PR #63 (squash `ef1dc2d`) 2026-06-17.** Global brand foundation: emerald `primary-*` scale + repointed shadcn `--primary`/`--ring`/sidebar tokens to emerald (aligns with PrimeVue Aura, already emerald), **Inter** font, `.brand-pattern`/`.brand-glow`, `APP_NAME="SchuLyf"`, mortarboard logo. Auth: `AuthSplitLayout` rebuilt as the emerald brand/form split + theme toggle; Login + Register migrated shadcn→PrimeVue (the high-traffic auth pages — the rest of **#22** stayed open for the remaining auth/settings surfaces, finally **closed not-planned 2026-06-22**; see §17 STATUS). Applicant dashboard: hero + status chips. Application form: 4-step PrimeVue `Stepper` (all cascade/validation/AUD-015 logic preserved). `plan/frontend-redesign/` (incl. the prototype) is now committed; `plan` added to eslint `ignores` (scratch, not app source — see gotcha below).
- **Staff dashboards ✅ SHIPPED — merged to `main` via PR #64 (squash `fa0f909`) 2026-06-17.** All 5 role dashboards (SAO / Accountant / Admin / Lecturer / Student) get the hero + summary chips + restyled-cards pattern via a new reusable `resources/js/components/StatCard.vue` (label + lucide icon + value + `tone`); Applicant refactored onto it (single source of truth). Lecturer's stale "coming soon" card removed (courses shipped). Admin kept its "Administrator"/"Open audit log"/`AuditLogModal` browser-smoke hooks.
- **Gotchas:** `eslint .` lints the whole repo → committing the prototype's `app.js` failed CI until `plan` was added to eslint `ignores`. **Stale-branch false alarm (2026-06-19):** a "Lint Frontend failed" email later arrived from the *already-merged* pilot branch `feat/frontend-redesign-pilot` (PR #63) re-firing CI — its `eslint.config.js` predates the `plan` ignore, so it still linted the prototype `app.js` (two `catch (_)` unused-var errors). `main` + active branches were green throughout; resolved by deleting the stale branch (already gone from the remote — `git fetch --prune` cleared the local ref). Triage recipe in memory [[reference-ci-stale-branch-false-alarm]]. `npm run format` reformats **all** of `resources/` — scope Prettier to changed files. PrimeVue `Password`/`InputText` put the `name` prop on the inner input via `$formName`, so Inertia `<Form>` serialization + Pest `fill()` both work.
- **Staff feature pages ✅ SHIPPED — merged to `main` via PR #65 (squash `1a497b5`) 2026-06-18.** The locked design language rolled onto all **31** staff feature pages across 5 phase-commits (SAO 5 · Accountant 4 · Lecturer 7 · Admin 10 · Student+shared 5) + a foundation commit (emerald Inertia progress bar `#10b981` + the §17/§18 status sync). **Recipe** (purely presentational — no props/routes/forms/logic touched): swap `<div class="space-y-4 p-4">` for `mx-auto max-w-{3xl form | 5xl detail | 6xl table} space-y-6 p-4 sm:p-6`; add the dashboard hero (`<section>` = uppercase emerald eyebrow `text-primary-700 dark:text-primary-400` + `text-2xl font-bold tracking-tight` title + muted subtitle); lift the page-level control (filter / New / Back) into the hero; drop the now-redundant `Card #title` (card keeps only `#content`); delete the title's now-unused lucide icon import. Course-scoped lecturer pages use the section as eyebrow + `CODE · Title` as H1. **Deliberately skipped** (purpose-built layouts that must NOT take the app hero): `student/payments/Receipt.vue` (print receipt + QR) and `receipts/Verify.vue` (public full-screen verify). Per-phase gate (scoped Prettier + ESLint, vue-tsc, Vite build, targeted Pest) + final whole-repo `eslint .` clean and full **Pest 695/695**. NOTE: `npm run format:check` flags 5 PRE-EXISTING drift files on `main` (AuditLogModal, AppSidebar, statusDisplay.ts, Verify, Receipt) — not redesign work; leave them (CI's lint job runs `prettier --write` with its auto-commit step disabled, so it never fails the build; `format:check` is not a CI gate).
- **Landing page → login + Welcome page removed — merged to `main` via PR #66 (squash `ac7ffd4`) 2026-06-18.** With the auth funnel now the app's front door, `routes/web.php` `Route::inertia('/', 'Welcome', …)` became `Route::redirect('/', '/login')->name('home')` — now `ANY /`, so every verb redirects; authenticated users then bounce on to their role dashboard via Fortify's guest middleware on the `login` route. The `home` route **name is preserved**, so `route('home')`, the Wayfinder `home()` helper, and the three auth-layout logo links still resolve (to `/`, which redirects). `resources/js/pages/Welcome.vue` deleted and its `case name === 'Welcome'` branch dropped from the `app.ts` layout resolver. `ExampleTest` now asserts `/` redirects **both** guests and authenticated users to login (the 3 existing `assertRedirect(route('home'))` tests are unaffected — they check the unchanged target `/`).
- **Initiative status:** the visible-feature SchuLyf redesign is **complete end-to-end** — auth + applicant pilot (#63), all 5 role dashboards (#64), all 31 staff feature pages (#65), plus the landing-page redirect / Welcome removal (#66). Restyle recipe + the live resume point live in memory [[project-frontend-redesign]].

## 19. Documentation initiative (started 2026-06-18)

Tracks GitHub issue **#68** — thoroughly document the whole application. Owner-defined process (in order): **(1)** agree the doc types + scope, **(2)** build skills that assist the task, **(3)** build specialized agents (one per doc type) and fan out to produce docs in parallel. Full scope/build plan in **`plan/documentation/plan.md`**. Memory [[documentation-initiative]] holds the live resume point.

**Scope locked 2026-06-18 (maximal on all four forks):** in-repo **Markdown** under a new `/docs` tree + root `README.md` (no docs-site dependency); **all audiences** incl. 6 per-role end-user guides; a **formal numbered ADR folder** (`docs/adr/`, ~20 ADRs); and an **inline PHPDoc/comment pass** over models/actions/services. **Guardrail:** docs describe the **shipped code, not `context.md`** — several §4 decisions were superseded (notably the multi-role pivot intent → single-role-for-staff as built; the §15 audit + §17/§18 changes override earlier per-phase notes). Every doc/ADR is verified against source before it ships.

**Tooling provisioned GLOBALLY under `~/.claude/` (not in this repo):** 5 doc-writing skills (`write-reference-doc`, `write-module-doc`, `write-user-guide`, `write-adr`, `docs-refresh`) + 5 drafter agents (`docs-architecture-writer`, `docs-reference-writer`, `docs-module-writer`, `docs-user-guide-writer`, `adr-writer`). Drafters write files; the orchestrator verifies + commits (the established drafter→orchestrator recipe). All doc phases run on branch **`docs/initiative-68`** (per-phase commits, one PR at the end, not yet pushed).

**Locked doc tree:** `README.md` + `docs/{index,architecture,onboarding,data-model,routes,security,testing,deployment}.md` + `docs/modules/{admissions,payments,exam-gating,course-management,notifications,admin-user-management}.md` + `docs/guides/{applicant,student,sao,accountant,lecturer,admin}.md` + `docs/adr/`.

**Build phases:** D0 foundation · D1 cross-cutting dev docs · D2 6 module docs · D3 ADRs · D4 6 user guides · D5 PHPDoc pass (runs Pint+Pest) · D6 README + cross-link. Markdown-only phases don't touch the app gate — **ESLint ignores `docs/`** so the whole-repo lint gate is unaffected, and `format:check` is not a CI gate (per §18).

- **D0 ✅ committed `2f9dd69`.** `/docs` skeleton (`index.md` TOC + `adr/README.md` index seeding ~20 Proposed ADRs) + `plan/documentation/plan.md` + the 5 skills + 5 agents.
- **D1 ✅ committed `cdc9708`.** The 7 cross-cutting developer docs, drafted by 3 parallel agents (2× architecture-writer + 1× reference-writer) and verified against source: `docs/{architecture,onboarding,data-model,routes,security,testing,deployment}.md`. Confirmed against code: **122** app routes (incl. `settings/appearance`), the course/attendance FK on-delete rules, the **8 ability gates + 33 `AuditAction` cases**, the **four-identifier** login resolver (email/employee_id/**phone**/matricule — phone is B9, NOT three as the old plan framed it), and a full Pest run **699 tests / 2,614 assertions green**. Reconciled the `routes.md` Fortify note (it implied email-only login + a static `/dashboard`; now points to the resolver + `LoginResponse` role-priority redirect). `index.md` dev-doc statuses flipped ✅. Notable: the drafters reliably caught plan-vs-code drift on their own.
- **D2 ✅ committed `021be54`.** The 6 module docs (`docs/modules/{admissions,payments,exam-gating,course-management,notifications,admin-user-management}.md`), drafted by **6 parallel `docs-module-writer` agents**, each verified against shipped source. Orchestrator spot-checks passed (receipt HMAC payload `receipt_number|matricule|amount_xaf|academic_year` keyed by APP_KEY; `Course::cohortStudents()` = `program_offering_id`+`level`+`academic_year`+`status=Active`; `CA_WEIGHT 0.3`/`EXAM_WEIGHT 0.7` via private `computedFinalScore()`; `CREATABLE_ROLES` = Lecturer/Accountant/Sao/Admin) and **all cross-links resolve**. index.md module statuses → ✅.
  - **Cross-cutting drift the drafters independently caught:** the *workflow* ability gates (`process-admission`, `decide-application`, `validate-payment`, the deferral surfaces, dispute review) are **defined in `AppServiceProvider::ABILITIES` + tested in `AbilityGatesTest` but never invoked at call sites** — authZ is enforced by `role:` route middleware + per-resource ownership. Only `view-audit-log` and the course `approve-course-plan`/`publish-results`/`mark-attendance` gates are actually wired. → **candidate ADR + backlog item** (wire `Gate::authorize` at call sites, or retire the unused gates). The docs describe authZ as it *actually* enforces.
  - Other as-built notes captured: no standalone student-deferrals list route (history rides the payments `Index` prop); `qrcode` is an **npm/client-side** dep, not composer; the relation is **`cohortStudents()`** not `cohort()` as the plan named it; publish/dispute mail was **not** shipped (audit-only); only `UserInvitationMail` is itself `ShouldQueue` (the other 3 Mailables queue via their listeners). Single-role-staff drift reconfirmed (matches `security.md §2.3`).
- **D3 ✅ committed `9fcf0ec`.** The ADR folder — **22 ADRs** (`docs/adr/0001…0022`) + updated index, drafted by **5 parallel `adr-writer` agents** (clustered: identity/roles, data-model conventions, domain structure, security/integrity, frontend/caching/lifecycle), each verifying its decisions against shipped source. Both D2 drifts became ADRs: **0002** (single-role-staff supersedes the multi-role pivot intent) and **0022** (the gate-enforcement gap). All 22 Accepted; cross-links resolve.
  - **Harness note:** the `adr-writer` sub-agents' **`Write` tool was sandbox-denied** (a new wrinkle on top of the known shell/commit denial in [[feedback-parallel-subagent-integration]] — drafter *Write* is also blocked here). They returned complete, code-verified findings; the **orchestrator authored the 22 files** from those findings rather than round-tripping content. Net effect: agents = parallel research/verification, orchestrator = file landing + index + gate + commit.
  - **Correction to the line 1118 drift note (verified by the D3 grep of `app/`):** only **3 of 8** ability gates are wired — `approve-course-plan` (`Sao/CourseController.php:128/139`), `publish-results` (`:150`), `mark-attendance` (`Lecturer/CourseSessionController.php:198` + `authorizeOwnership()`). **`view-audit-log` is NOT wired** (D1/D2 framing overcounted it) — the audit endpoint is guarded by the `role:admin` route-group middleware, not the gate. The 5 uninvoked gates: `process-admission`, `decide-application`, `validate-payment`, `manage-references`, `view-audit-log`. ADR-0022 carries the full evidence table; `security.md §2.1/§3.4` wording ("the gate guards the endpoint") wants a follow-up tightening.
- **D4 ✅ committed `1b32704`.** The 6 per-role user guides (`docs/guides/{applicant,student,sao,accountant,lecturer,admin}.md`), drafted by **6 parallel `docs-user-guide-writer` agents**, each verified against the role's actual routes + Inertia pages (task-oriented "what you see and click", no internals). index.md guide statuses → ✅; all cross-links resolve. As planned around the Write-denial, the agents returned complete UI-grounded Markdown and the **orchestrator authored the 6 files** (cleaning the agents' `&gt;`/`&amp;` HTML entities back to plain `>`/`&`). All 6 agents completed cleanly on this run (the earlier session-limit failures on admin+lecturer did not recur). Each guide ends with a maintainer-notes block listing the few unconfirmed labels (e.g. exact `AppearanceTabs` option text, sidebar/`NavUser` menu wording, the invite-acceptance screen copy, whether 2FA is offered per role) — small wording checks for a future screenshot pass, not content gaps.
- **D5 ✅ committed `41a4c4b`.** PHPDoc / relationship-generic pass over **30 files** (25 models + 2 model concerns + `PaymentStandingService`/`PaymentStandingResult`/`RoleDashboardResolver`/`CreateUserAction`), **359 insertions, 0 deletions — documentation-only** (no behaviour/signature/type-hint changes). Drafted by **5 parallel `laravel-backend-drafter` agents** clustered by domain (identity/roles/audit · admissions/reference · finance · course-mgmt · actions/services sweep). **Edit/Write was sandbox-denied for the backend drafters too** (not just the doc agents — confirms the denial is environment-wide for sub-agents), so they returned edit pairs and the **orchestrator applied them** — unescaping HTML entities and **hoisting class docblocks above the `#[Fillable(...)]` attribute** (drafters 2/3/4 had placed them between attribute and `class`). Findings: the **action/service layer was already documented to the `ReviewPaymentAction` bar** — only **3 genuine gaps** (`RoleDashboardResolver` class+`pathFor`, `CreateUserAction::sendInvitation`); the real gap was **models** (no relationship generics, no class docblocks; even the newer course models lacked `BelongsTo` generics). House style anchored on `Course.php` (`@return BelongsTo<Related, $this>`); **no Larastan/PHPStan in the project**, so the generics are pure documentation (can't fail a gate). Gate: **Pint clean**; Pest **Unit+Feature 696 / 2,602 green**.
  - **Gotcha recorded:** the **`tests/Browser` suite hangs in this session** — a `php artisan test --compact` run sat **22 min at ~1.3 CPU-s** (blocked, not computing; browser smoke tests need a live server/Playwright browser that isn't available here). Killed it and ran `--testsuite=Unit,Feature` instead (696 tests = the 699 baseline minus the 3 browser smoke tests). For app-gate verification of backend-only changes here, **scope Pest to `--testsuite=Unit,Feature`**; don't run the default suite (it will hang on Browser). See [[reference-browser-suite-hangs-locally]].
- **D6 ✅ committed `db07ccb`.** Root **`README.md`** (overview of the five problem areas, stack, quick-start, quality-gate table + a full index into `/docs`); project **`CLAUDE.md`** gained a `## Documentation` section + the **docs-refresh-after-feature** rule; **`onboarding.md §5`** now lists `docs-refresh` as the gate's final step; the Laragon-env **`D:\laragon\CLAUDE.md`** (outside this repo, edited on disk only) now points at the docs tree. Verified **all 270 README + docs cross-links resolve, 0 broken**. Markdown/CLAUDE-only — no app gate.
- **Initiative #68 COMPLETE.** All phases **D0–D6 shipped** on branch `docs/initiative-68` (deliverable commits `2f9dd69` · `cdc9708` · `021be54` · `9fcf0ec` · `1b32704` · `41a4c4b` · `db07ccb`, plus per-phase context commits). Full deliverable set: `/docs` = index + 7 cross-cutting dev docs + 6 module docs + 22 ADRs + 6 per-role user guides; root `README.md`; and a PHPDoc / relationship-generic pass over `app/` (models, actions, services). Built end-to-end via 5 doc-writing skills + 6 drafter agents under the drafter→orchestrator recipe (sub-agent Write is sandbox-denied here, so drafters returned content/edits and the orchestrator authored/applied + ran every gate + committed).
- **MERGED → `main` 2026-06-21 via squash `c0d2c21` (PR #69, Closes #68; branch deleted).** All 4 CI checks were green pre-merge — incl. the `browser` job, which proves the browser suite is healthy and its local hang is purely an env limitation (no live browser). Issue #68 closed.
- **DEFERRED — decision to be made later:** whether to tighten `security.md §2.1/§3.4` wording re: the `view-audit-log` gate (the audit endpoint is guarded by `role:admin` route middleware, **not** the ability gate; only 3 of 8 ability gates are wired at call sites — see ADR-0022). Options when revisited: a small docs PR, file a backlog issue, or drop it. Not actively pending pickup.

## 20. Academic report (.docx) — school deliverable (started 2026-06-22)

A **non-code deliverable**: the school requires a written report on the project. Modelled on a validated sample report the owner supplied at `plan/tuto-report/final report.pdf` (an IUG / University Institute of the Gulf of Guinea real-estate-management project, "Written and Presented by GROUP 12") — matched for **structure, tone, and typography** (Times New Roman 12 / 1.5 line spacing / justified body / running title header / "Written and Presented by …" footer + page numbers / roman front-matter + arabic body). Owner choices (via AskUserQuestion): format = **Word .docx**, figures = **rendered diagrams + screenshots**, depth = **"deeper, using our `/docs`"**.

- **Deliverables (all under `plan/report/`):** `build_report.py` (python-docx builder, ~45-page output, with an editable **IDENTITY BLOCK** of `<< … >>` cover placeholders at the top) · `build/SchuLyf-Student-Management-System-Report.docx` · `build/SchuLyf-Report-preview.pdf` · `diagrams/*.mmd` + rendered `*.png` · `screenshots/*.png` (9 real app screenshots) · `seed_demo.php` + `capture_screenshots.mjs` (the screenshot pipeline).
- **Report structure (reference skeleton → SchuLyf):** Cover → List of Tables → List of Figures → TOC → General Introduction → Structure of the Work → **Ch.1 State of the Art** (evolution table, existing-systems comparison, conceptualization) → **Ch.2 Analysis & Design** in 3 Parts (preliminary study + justification; functional + non-functional requirements; architecture + **UML/ER figures** + DB design + resource/cost tables) → **Ch.3 Results & Discussion** (9 captioned real screenshots, Figures 11–19 + testing + discussion) → General Conclusion → References. **11 tables + 19 figures**, content grounded in the `/docs` tree (§19): HMAC receipts, payment-standing gate, immutable audit log, the 6-role model.
- **Toolchain that works on THIS machine** (no LibreOffice / Ghostscript / pdftoppm / headless-PDF-raster available): diagrams via `npx -y @mermaid-js/mermaid-cli` pointed at installed **Chrome** through `diagrams/_puppeteer.json` (**executablePath must use forward slashes** — backslashes break the JSON parse); DOCX→PDF + TOC/LOF/LOT field refresh via **Word COM in PowerShell** (`New-Object -ComObject Word.Application`; `TablesOfContents/TablesOfFigures.Update()`; `ExportAsFixedFormat($pdf, 17)`); `pdftotext` (poppler/mingw64) for text-level verification; **PyMuPDF** (`pip install pymupdf`, installed) to rasterize PDF pages to PNG for visual QA — the Read tool's own PDF rasterizer fails (`pdftoppm not found`), so render pages with `fitz` then Read the PNGs. The docx carries `w:updateFields` so Word also refreshes fields on open. Verified: Word paginates to **45 pages**, all field lists populate with correct roman/arabic page numbers, no leaked field codes.
- **Styling decision:** rendered in **plain black Times New Roman to match the reference** (initial SchuLyf-emerald accents on header/topic-box/table-headers were toned down to black/gray per the "match the reference" instruction). An emerald-branded variant is a one-edit switch if the owner prefers it.
- **Revision 2026-06-22 — "diagrams too large + no real screenshots" complaint RESOLVED:**
  - **Fit-to-page:** `figure()` now scales every image to the A4 printable box on **both** axes (`PAGE_MAX_W=6.0"`, `PAGE_MAX_H=8.2"`; PNG size read via `struct`), so a tall diagram can never overflow. The **use-case diagram (6.1:1 tall — the worst overflow) was split into two TB-band panels** (`01-usecase-1/2.png`: admissions/payments/exam + courses/admin) shown under **one caption** via new `figure_group([...])`, so it stays Figure 2 and no downstream figure numbers shift. Root cause of why a single balanced layout was impossible: **Mermaid ignores subgraph `direction` when cross-subgraph edges exist**. Confirmed in the PDF: use-case (p23) + both activity diagrams (p27, p29) now sit fully inside the margins with captions.
  - **Real screenshots:** the 7 placeholder boxes are gone — `seed_demo.php` (local-only, NOT wired into DatabaseSeeder) seeds applicant@/student@example.com (pwd `password`), a SAO queue, and a **validated→HMAC-receipt + pending** payment with GD-drawn slip PNGs; then `capture_screenshots.mjs` (Playwright — already a devDependency) logs in per role and captures 9 screens (Figures 11–19), incl. the accountant **inline slip viewer** open. Reproduce: `php artisan migrate:fresh --seed` → `php artisan tinker --execute="require base_path('plan/report/seed_demo.php');"` → set `public/hot` aside (so built assets serve) → `node plan/report/capture_screenshots.mjs`. **Gotcha:** `email_verified_at` is NOT in User's `#[Fillable]`, so the seeder uses `markEmailAsVerified()` — applicant/student routes enforce `verified` (staff routes don't), so unverified demo users would bounce to the verify-email page.
- **Still pending from the owner:** cover-page identity (university / faculty / department / option / author + matricule / supervisor / academic year) — fill the IDENTITY BLOCK (+ `RUNNING_HEADER`/`FOOTER_BYLINE`) or hand over the values to regenerate; optionally confirm plain-vs-emerald look. Live state in memory [[project-academic-report]].

## 21. Student transcript generation (B16 / #71 — scoped 2026-06-22, NOT yet started)

New feature backlog item filed from an owner request: generate an **official, printable/downloadable academic transcript** that aggregates a student's **published** `CourseResult`s across all semesters/levels into one credit-weighted record (per-semester + cumulative standing). Filed as GitHub issue **#71** (labels `enhancement` + `backlog`); full scope + decision log live in the issue body. No code yet.

- **Feasibility:** reconstructable from existing data — each `course_results` row joins to a `Course` carrying its own `code`/`title`/`credits`/`semester`/`level`/`academic_year`, so the full multi-year history comes from `CourseResult`s alone (the `student_profile`'s single `level`/`academic_year` is only the *current* cohort; **no enrollment-history table needed**). Today only the current-cohort per-course view exists (`Student\CourseResultController@index`).
- **Gaps to close:** (1) no grade-point/GPA layer (letter grades A–F exist via `CourseResult::grade`, but no points or credit-weighted GPA/CGPA); (2) no cross-semester aggregation; (3) no PDF/document pipeline anywhere in the app.
- **Phased plan:** **T0** `TranscriptService` (group published results by `academic_year → semester`, join course metadata, compute per-semester + cumulative credits/average/GPA-CGPA, grade→point map) → **T1** backend/routes/authz (student own + SAO/admin any; **only `Published` results**; respect student status; audit) → **T2** mpdf HTML/CSS template modelled on the owner's reference image + embedded verification QR → **T3** frontend ("Download transcript" on student results, "Generate transcript" on SAO student detail) → **T4** Pest (authz, only-published, GPA math, PDF response, verify endpoint) + `docs-refresh` (`docs/modules/transcripts.md`, routes ref, ADRs for GPA scale + PDF dep + verification) + demo seed.
- **Decisions LOCKED 2026-06-22:**
  - **PDF library = `mpdf/mpdf`** — pure PHP (no Chromium/Node runtime dep → keeps Laravel Cloud deploy simple), strong Unicode for French/Cameroonian names, native repeating table headers + per-page footers/page-numbers + watermark. Preferred over `barryvdh/laravel-dompdf` (weaker multi-page headers/page-numbers) and `spatie/browsershot` (rejected — Chromium in the production runtime for a document that doesn't need that fidelity). **Revisit→Browsershot only if the reference transcript turns out visually elaborate.** Dep-add approved in principle.
  - **Verification = IN SCOPE** — the transcript is a *verifiable* document (directly serves the system's founding tamper-proofing motivation). **Reuse the shipped receipt-verification pattern** (§17, #6/#16): HMAC `signature` over bound identity + result set + issue date on an immutable record, a **public unauthenticated** verify endpoint that re-derives the HMAC and shows the authentic summary only when it checks out (forged/tampered/reused/unknown all read "invalid", no existence oracle) — mirror `Receipts\VerifyReceiptController` + `SchoolReceipt::verifies()` + `resources/js/pages/receipts/Verify.vue`. Render a **QR** on the PDF pointing at the public verify URL, generated as **SVG** via `simplesoftwareio/simple-qrcode` (SVG output needs no gd/imagick; embeds natively in mpdf).
- **Still OPEN (resolve at kickoff):** **GPA scale** — add grade points + credit-weighted GPA/CGPA (recommended, e.g. A=4…F=0) vs percentages + letter grades only; likely settled by the reference image.
- **New deps (approved in principle):** `mpdf/mpdf`, `simplesoftwareio/simple-qrcode` — both pure-PHP, fine on Windows/Laragon + Laravel Cloud.
- **BLOCKED on:** the owner supplying a real transcript image (drives the T2 template) + the GPA-scale decision, before implementation starts. Live state in memory [[project-implementation-progress]].
- **Log status:** this §21 scope shipped to `main` via PR #72 (squash `0b0730b`, 2026-06-22, bundled with the §20 academic-report deliverable). #22 (shadcn→PrimeVue) was **closed not-planned** the same day, logged in §17/§18 via PR #73 (squash `a34ac45`) — `main` now at `a34ac45`. #71/B16 is the only open backlog issue.

## 22. Maintenance fixes — course-plan review, page widths, local-tunnel proxy (2026-06-24)

Two small PRs landed off the back of a user question about the lecturer "Course plan" form. No new routes, schema, or backlog movement — `main` advances `a34ac45 → 339c62c (PR #74) → 9f23c08 (PR #75)`.

- **PR #74 (squash `339c62c`, two distinct concerns):**
  - **`fix(sao)` — course-plan review.** Tracing the lecturer plan form surfaced two real defects in SAO review: (1) the reviewer **never saw the plan** — `description` wasn't in the `Sao\CourseController@index` payload and `sao/courses/Form.vue` doesn't render it, so approve/reject happened blind; (2) **reject was silently broken** — `sao/courses/Index.vue` posted `plan_review_notes` while `RejectCoursePlanRequest`/controller read `notes`, so every reject 422'd with the error bound to a key the template never showed. Fix: added `description` + `plan_review_notes` to the index payload; replaced the inline Approve/Reject buttons + standalone reject dialog with **one consolidated "Review plan"/"View plan" dialog** that shows the plan content (Approve/Reject inline for `submitted`, read-only otherwise, surfaces prior rejection notes); aligned the reject field to `notes`. Also renamed the lecturer `Plan.vue` field label **"Description" → "Course plan"** (still the same `description` column). New test asserts the index payload now carries `description` + `plan_review_notes`. Docs synced: `docs/guides/sao.md` Task 6 + `docs/modules/course-management.md`. Detail in [[project-course-management-11]].
  - **`style(ui)` — page-container widths.** Added `w-full` to the root container of **39 pages**: page roots sit in a flex-column `main` (`SidebarInset`); `mx-auto` cancels the stretch so content stopped short of its `max-w`. One-line change per file, purely presentational. See [[feedback-page-container-w-full]].
- **PR #75 (squash `9f23c08`, two commits):**
  - **`chore`** — `bootstrap/app.php` now `trustProxies(at: ['127.0.0.1', '::1'])` so redirects/CSRF/absolute URLs use the public forwarded host/scheme when served through a local Cloudflare Tunnel (loopback-only → no-op elsewhere). This makes the local-tunnel sharing setup committed rather than a working-tree edit. See [[reference-cloudflare-tunnel-sharing]].
  - **`docs(context)`** — committed the §21 "Log status" line above.
- **Gate:** both PRs green on all four CI checks (`ci 8.4`, `ci 8.5`, `quality`, `browser`) before squash-merge; branches deleted.

## 23. Fees-module installment sub-form responsiveness (2026-06-25, PR #76)

While share-testing as Admin over the local Cloudflare Tunnel, the owner found the **Edit fee schedule** dialog's **installment sub-form "so poorly rendered"** and asked to fix it accounting for Tailwind responsive breakpoints. Single-file change in `resources/js/pages/admin/fees/Index.vue`, **merged to `main` via PR #76 (squash `3a2dee2`, 2026-06-25, bundled with the §22 context-log catch-up); all 4 CI checks green, branch deleted.**

- **Defects diagnosed:** each installment was one cramped fixed 5-col grid `sm:grid-cols-[4rem_1fr_8rem_9rem_2rem]` — the `2rem` last column was narrower than a PrimeVue **rounded** remove button (overflow/misalign), `8rem` amount clipped the `XAF` currency value, `9rem` cramped the `DatePicker`; below `sm` it collapsed to an ugly stack with a lone floating button. The `Dialog` was a hard `46rem` with **no `:breakpoints`** so it overflowed on tablet/phone.
- **Fix:** (1) `Dialog` got `:breakpoints="{ '768px': '92vw' }"` (scales to viewport ≤768px). (2) Each installment is now a bordered card (`rounded-lg border bg-muted/30 p-3`) with a header row — "Installment N" label + a correctly-sized `size="small"` rounded remove button — over a responsive field grid `grid gap-3 sm:grid-cols-2 lg:grid-cols-[5rem_minmax(0,1fr)_10rem_11rem]`, all inputs given `fluid`. Stacks 1-col `<sm`, 2-col at `sm`, 4-track at `lg` (fits inside the 46rem dialog without clipping). Renamed the `#` label → "Sequence".
- **Scope:** purely presentational — `addInstallment`/`removeInstallment`/`submit`, the `installmentsTotal` computed + total line, transform/store logic all unchanged; no test added (CSS layout, no behaviour change). Standing PrimeVue-docs-first rule honoured (confirmed `Dialog` `breakpoints` format + `fluid` via https://primevue.org/dialog). `npm run build` run so the change is live over the production-bundle-served tunnel (no `npm run dev` while sharing — see [[reference-cloudflare-tunnel-sharing]]).
- **Session housekeeping:** the Cloudflare Quick Tunnel + `php artisan serve --port=8000` started for share-testing were both stopped at end of session.
- **Log status:** this §23 (and §22) shipped in PR #76 (`3a2dee2`); the original §23 header read "local — UNCOMMITTED" while still in the working tree — corrected to "PR #76" in a follow-up doc commit once merged.

## 24. Student "My courses" page + plan-vs-shipped omissions audit (2026-07-03)

Field testers (mates on the shared tunnel) reported a Student-role user **cannot see the courses they'll be taking per semester**. Investigation verdict: **planned but never scheduled** — `plan/course-management/plan.md` L15 Roles prose promised "views own **courses**/attendance/assignments/results", but no phase C0–C4 carried a student course-list deliverable; the three student screens only surfaced courses as grouping headers, `Course.semester` was never selected by any Student controller, and the code-first docs pass quietly narrowed the role line ("View own attendance / assignments / published results") — documenting the gap without flagging it. A spec→phase decomposition leak.

- **Filed + fixed:** issue **#78** (labels enhancement+backlog) → branch `feat/student-courses` → **PR #79** (OPEN at log time). Commits: `76f397d` feature, `fc7a650` docs sync.
  - `GET student/courses` (`student.courses.index`, group `role:student,admin`) → new `Student\CourseController@index`: approved cohort courses (same 4-clause inline cohort filter as siblings — deliberately NOT extracted into a shared scope; that refactor would touch 4 files and belongs in its own change), `with('lecturer.user:id,name')` + `withCount(['sessions','assignments'])`, ordered semester→code. `cohort` prop reuses the dashboard's null-safe offering shape (withTrashed offering, nullable department) so `degreeLabel()` works unchanged.
  - `student/courses/Index.vue`: client-side per-semester grouping (Card per semester: course count + total credits; DataTable: code, title+description, credits, lecturer|"Not yet assigned", session/assignment counts), programme·level·year context line, empty state. Sidebar "My courses" (BookOpen) + dashboard quick-link tile, both between My payments and My attendance.
  - `StudentCoursesTest` (4 tests/54 assertions): semester ordering+lecturer+counts; submitted-same-cohort and approved-different-level excluded; profile-less renders empty+null cohort; lecturer 403. Helper `myCoursesStudent()` (unique prefix — Pest file functions are global; `c2Attend*` lives in the sibling).
  - **Gate:** Pint ✅, Unit+Feature **701 passed** (2,670 assertions), build ✅ (no chunk regression), vue-tsc ✅, ESLint ✅. No schema change → no migrate:fresh needed.
  - **Docs synced** (docs-refresh): `routes.md` 121→122 + row; `modules/course-management.md` (three→four student screens in §1, roles row, student routes row, tests row, file-map ×2); `guides/student.md` (capabilities bullet, 4→5 quick-link tiles, new walkthrough §6 "See the courses you'll be taking each semester", old §6–9 renumbered §7–10 — verified no numeric/anchor cross-references existed); `index.md` student-guide summary line.
- **Omissions audit** (parallel fork over plan/*.md + context.md + project-brief + docs vs shipped routes/controllers/pages/mailables). **Four untracked findings, ranked — FILED 2026-07-03 as backlog issues #80/#81/#82/#83 (labels enhancement+backlog, in this order):**
  1. **Applicants can't respond to `DocumentsRequested` — dead-end status.** §4.5 promised post-submission "upload missing documents" (`context.md` L78); no route/page exists to add/replace a document on an existing application, and the AUD-005 one-open-application guard blocks re-applying while one sits in `DocumentsRequested`. Applicant has NO in-app action; guide papers over it. Omission, size M (building blocks: `ApplicationDocument` unique per type, upload validation, shared view/download, #18 notification pattern). **Highest impact — strands real applicants like the paper process did.**
  2. **Results-publish / dispute-resolution notifications dropped.** C4 "optional queued mail — include if cheap" (`plan/course-management/plan.md` L78) consciously omitted at C4 (`context.md` L1072) but never filed; students must poll. Size S — one Notification + dispatch in `PublishCourseResults`/`ReviewResultDispute`, pattern exists since #12 (`CourseSessionChangedNotification`).
  3. **`ApplicationStatus::Draft` is a dead state.** Schema+state machine (incl. AUD-010 Draft→Submitted transition) support drafts; `store()` creates straight to Submitted, nothing ever persists Draft. Omission/dead code, size S–M (ship save-as-draft for the 4-step form, or delete the case+transitions).
  4. **ADR-0022 gate-enforcement backlog item never filed.** `context.md` L1118 promised "candidate ADR **+ backlog item**"; ADR shipped, item didn't. Verified: only 3/8 gates wired; `process-admission`, `decide-application`, `validate-payment`, `manage-references`, `view-audit-log` declared+tested but never invoked (role middleware covers all surfaces — defence-in-depth debt). Includes the deferred `security.md` §2.1/§3.4 wording fix. Size S.
  - **Already tracked / consciously closed (no action):** transcript #71; notifications-centre/preferences/SMS (#18 close-out "future issues if prioritised"); bank-CSV reconciliation (`plan/payments/plan.md` L28 "possible later enhancement"); lecturer dispute surface (C4 scoping); facility-access enforcement (#8 "standing, not enforcement"); standalone deferral-list route (shipped as payments Index prop); #20/#22 closed not-planned.
- **Update (2026-07-03, same day):** PR #79 squash-merged to `main` as **`b7f145c`** (all 4 CI checks green, branch deleted, #78 auto-closed). Findings 1–4 then filed as **#80** (DocumentsRequested dead end, M), **#81** (results/dispute notifications, S), **#82** (Draft dead state, S–M — issue offers ship-or-delete resolutions), **#83** (wire-or-retire the 5 uninvoked gates + `security.md` §2.1/§3.4 wording, S — this IS the promised ADR-0022 backlog item, and absorbs the deferred §19 doc fix). Each issue body carries the evidence + scope sketch + open design questions.
- **Next resume point:** discuss implementation of #80–#83 with the owner (recommended order 80 → 83); nothing is in flight.

## 25. #80 DocumentsRequested response — designed + planned, NOT yet implemented (2026-07-03)

Brainstormed, spec'd and planned the applicant response flow for `DocumentsRequested` (#80, the top omissions-audit finding from §24). **No application code written yet** — the owner chose **subagent-driven execution** (superpowers:subagent-driven-development) to run **later**.

- **Decisions locked with the owner (full detail in `plan/documents-requested/design.md`):** documents only (no demographic edits); **structured per-document review** — `application_documents` gains three-state `status` (`pending` on upload *and* resubmit / `accepted` / `rejected` + `review_notes`, `reviewed_by`, `reviewed_at`); SAO accept/reject per document (reason required on reject); triage → `DocumentsRequested` **guarded** (≥1 rejected doc, triage `notes` relaxed to optional); applicant replaces each rejected doc individually (Approach 1, in-place row update — the unique `(application_id, document_type_id)` index includes trashed rows so recreate is off the table); **auto-flip** `DocumentsRequested → Submitted` when no rejected doc remains (shared `ResolvesDocumentsRequested` trait; also fires when SAO *accepts* the last rejected doc); **email only** — queued mail to `contact_email` on every entry into the status (event → ShouldQueue listener → `Mail::send`, mirroring the decision mail). Out of scope: requesting never-submitted document types, demographics corrections, in-app/SAO notifications, `OPEN_STATUSES` changes.
- **Artifacts (branch `feat/documents-requested-response`, off `main` @ `ef51f16`):** spec `plan/documents-requested/design.md` (`bb76490`, owner-approved) + implementation plan `plan/documents-requested/plan.md` (`0fef040`, 9 TDD tasks with complete code: T1 schema/enum/factory → T2 SAO endpoints+concern → T3 triage guard → T4 mail → T5 applicant replace → T6 SAO UI → T7 applicant UI → T8 full gate → T9 docs/ADR-0023/PR). Plan deliberately refines the spec's route URI to plural `applications/{application}/documents/{document}` (matches download/view siblings).
- **Existing-test impact when executing:** `TriageApplicationTest` — the "requires notes" case is *replaced* by the ≥1-rejected guard case; "persists notes" gains a rejected-doc fixture (plan T3 has the exact code).
- **Resume:** run `plan/documents-requested/plan.md` task-by-task via subagent-driven-development on the existing branch, then PR closing #80. #81–#83 still await their own implementation discussions.
