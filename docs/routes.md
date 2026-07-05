# Routes & endpoints

Route reference for SchuLyf, extracted from the route files under `routes/` and reconciled against
`php artisan route:list --except-vendor` (125 app routes). This page is **structure only** — for the
behaviour behind each action, follow the linked module docs.

- **No `routes/api.php`.** Everything is a session-authenticated **web** route. The cross-cutting
  registration lives in `routes/web.php`, which `require`s the per-area files:
  `settings.php`, `admin.php`, `sao.php`, `lecturer.php`, `student.php`, `accountant.php`.
  `routes/console.php` holds the scheduler, not HTTP routes.
- **Auth routes** (login, register, password reset, email verification, 2FA, password confirmation)
  are registered by **Laravel Fortify** (vendor) — see [Authentication (Fortify)](#authentication-fortify).

## Middleware & gates used below

| Token | Meaning |
|---|---|
| `auth` | Authenticated (web guard) |
| `verified` | Email verified |
| `role:a,b` | `EnsureUserHasRole` (alias `role`) — passes if the user holds **any** of the listed [`RoleName`](data-model.md#enum-rolename) roles |
| `throttle:lookups` | 60/min per user/IP — JSON lookups + file-serving endpoints |
| `throttle:audit-logs` | 30/min per user/IP — admin audit-log modal |
| `throttle:6,1` | 6/min — password update |
| `scopeBindings` | Nested route-model binding scoped to the parent |
| `withTrashed` | Route-model binding includes soft-deleted rows (restore actions) |

`role` is aliased in `bootstrap/app.php`; the throttle limiters are defined in
`app/Providers/FortifyServiceProvider.php`.

---

## Public & shared (`routes/web.php`)

### Public (unauthenticated)

| Method | URI | Name | Controller@action | Middleware/Gate |
|---|---|---|---|---|
| ANY | `/` | `home` | redirect → `/login` | — |
| GET | `receipts/verify/{receipt_number}` | `receipts.verify` | `Receipts\VerifyReceiptController` (invokable) | `throttle:lookups` |

Behaviour: receipt verify → [Payments & receipts](modules/payments.md).

### Authenticated shared — `auth`, `verified`

| Method | URI | Name | Controller@action | Middleware/Gate |
|---|---|---|---|---|
| GET | `dashboard` | `dashboard` | `DashboardController` (role router) | — |
| GET | `lecturer/dashboard` | `lecturer.dashboard` | `Dashboards\LecturerDashboardController` | `role:lecturer` |
| GET | `student/dashboard` | `student.dashboard` | `Dashboards\StudentDashboardController` | `role:student` |
| GET | `applicant/dashboard` | `applicant.dashboard` | `Applications\ApplicationController@dashboard` | — (roleless fallback) |
| GET | `application/new` | `application.create` | `Applications\ApplicationController@create` | — |
| POST | `application` | `application.store` | `Applications\ApplicationController@store` | — |
| GET | `application/{application}` | `application.show` | `Applications\ApplicationController@show` | — |
| GET | `applications/{application}/documents/{document}/download` | `application.documents.download` | `Applications\DocumentDownloadController` | `scopeBindings`, `throttle:lookups` |
| GET | `applications/{application}/documents/{document}/view` | `application.documents.view` | `Applications\DocumentViewController` | `scopeBindings`, `throttle:lookups` |
| POST | `applications/{application}/documents/{document}` | `application.documents.replace` | `Applications\ApplicationController@replaceDocument` | `scopeBindings` (controller `403`s non-owner) |
| GET | `payments/{payment}/slip` | `payments.slip` | `Payments\PaymentSlipDownloadController` | `throttle:lookups` (controller enforces ownership/role) |
| GET | `payments/{payment}/slip/view` | `payments.slip.view` | `Payments\PaymentSlipViewController` | `throttle:lookups` (controller enforces ownership/role) |
| GET | `assignment-submissions/{submission}` | `assignments.submission.download` | `Assignments\SubmissionDownloadController` | `throttle:lookups` (controller enforces ownership/role) |
| GET | `assignment-submissions/{submission}/view` | `assignments.submission.view` | `Assignments\SubmissionViewController` | `throttle:lookups` (controller enforces ownership/role) |
| GET | `standing` | `standing.check` | `StandingController` (invokable) | `role:sao,accountant,admin` |

Behaviour: applications → [Admissions](modules/admissions.md); slips/standing → [Payments](modules/payments.md) / [Exam gating](modules/exam-gating.md); submissions → [Course management](modules/course-management.md).

### `api/v1` JSON lookups — prefix `api/v1`, name `api.v1.`, `throttle:lookups`

Same-origin `fetch()` targets (cascading-dropdown lookups), **not** a token API. Inside the
`auth`/`verified` group.

| Method | URI | Name | Controller@action | Middleware/Gate |
|---|---|---|---|---|
| GET | `api/v1/program-offerings` | `api.v1.program-offerings.index` | `Applications\ApplicationController@offerings` | `throttle:lookups` |
| GET | `api/v1/level-requirements` | `api.v1.level-requirements.index` | `Applications\ApplicationController@levelRequirements` | `throttle:lookups` |

---

## Settings (`routes/settings.php`)

Two groups. Profile + staff-profile under `auth` only; the rest under `auth` + `verified`.

### `auth`

| Method | URI | Name | Controller@action | Middleware/Gate |
|---|---|---|---|---|
| GET | `settings` | — | redirect → `/settings/profile` | `auth` |
| GET | `settings/profile` | `profile.edit` | `Settings\ProfileController@edit` | `auth` |
| PATCH | `settings/profile` | `profile.update` | `Settings\ProfileController@update` | `auth` |
| GET | `settings/staff-profile` | `staff-profile.edit` | `Settings\StaffProfileController@edit` | `auth` (controller 403s non-staff; self-only) |
| PATCH | `settings/staff-profile` | `staff-profile.update` | `Settings\StaffProfileController@update` | `auth` (self-only) |

### `auth`, `verified`

| Method | URI | Name | Controller@action | Middleware/Gate |
|---|---|---|---|---|
| DELETE | `settings/profile` | `profile.destroy` | `Settings\ProfileController@destroy` | `auth`, `verified` |
| GET | `settings/security` | `security.edit` | `Settings\SecurityController@edit` | `auth`, `verified` |
| PUT | `settings/password` | `user-password.update` | `Settings\SecurityController@update` | `auth`, `verified`, `throttle:6,1` |
| GET | `settings/appearance` | `appearance.edit` | Inertia view `settings/Appearance` | `auth`, `verified` |

---

## Admin (`routes/admin.php`)

Group: prefix `admin`, name `admin.`, `auth` + `verified` + **`role:admin`**.

### Dashboard & audit

| Method | URI | Name | Controller@action | Middleware/Gate |
|---|---|---|---|---|
| GET | `admin/dashboard` | `admin.dashboard` | `Admin\DashboardController` (invokable) | `role:admin` |
| GET | `admin/audit-logs` | `admin.audit-logs.index` | `Admin\AuditLogController@index` | `role:admin`, `throttle:audit-logs` |

### User management

| Method | URI | Name | Controller@action | Middleware/Gate |
|---|---|---|---|---|
| GET | `admin/users` | `admin.users.index` | `Admin\UserController@index` | `role:admin` |
| GET | `admin/users/create` | `admin.users.create` | `Admin\UserController@create` | `role:admin` |
| GET | `admin/users/import` | `admin.users.import` | `Admin\UserController@importForm` | `role:admin` |
| GET | `admin/users/import/template` | `admin.users.import.template` | `Admin\UserController@importTemplate` | `role:admin` |
| POST | `admin/users/import/preview` | `admin.users.import.preview` | `Admin\UserController@importPreview` | `role:admin` |
| POST | `admin/users/import` | `admin.users.import.store` | `Admin\UserController@import` | `role:admin` |
| POST | `admin/users` | `admin.users.store` | `Admin\UserController@store` | `role:admin` |
| GET | `admin/users/{user}/edit` | `admin.users.edit` | `Admin\UserController@edit` | `role:admin` |
| PATCH | `admin/users/{user}` | `admin.users.update` | `Admin\UserController@update` | `role:admin` |
| DELETE | `admin/users/{user}` | `admin.users.destroy` | `Admin\UserController@destroy` | `role:admin` |
| POST | `admin/users/{user}/restore` | `admin.users.restore` | `Admin\UserController@restore` | `role:admin`, `withTrashed` |
| POST | `admin/users/{user}/resend-invite` | `admin.users.resend-invite` | `Admin\UserController@resendInvite` | `role:admin` |
| PATCH | `admin/users/{user}/role` | `admin.users.role` | `Admin\UserController@changeRole` | `role:admin` |

> The literal `users/import…` routes are declared before `users/{user}` so the literal segment wins.

Behaviour: [Admin user management](modules/admin-user-management.md).

### Fee configuration — prefix `admin/fees`, name `admin.fees.`

| Method | URI | Name | Controller@action | Middleware/Gate |
|---|---|---|---|---|
| GET | `admin/fees` | `admin.fees.index` | `Admin\Fees\FeeScheduleController@index` | `role:admin` |
| POST | `admin/fees` | `admin.fees.store` | `Admin\Fees\FeeScheduleController@store` | `role:admin` |
| PATCH | `admin/fees/{fee_schedule}` | `admin.fees.update` | `Admin\Fees\FeeScheduleController@update` | `role:admin` |
| DELETE | `admin/fees/{fee_schedule}` | `admin.fees.destroy` | `Admin\Fees\FeeScheduleController@destroy` | `role:admin` |
| POST | `admin/fees/{fee_schedule}/restore` | `admin.fees.restore` | `Admin\Fees\FeeScheduleController@restore` | `role:admin`, `withTrashed` |

Behaviour: [Payments & receipts](modules/payments.md).

### Reference data — prefix `admin/references`, name `admin.references.`

| Method | URI | Name | Controller@action | Middleware/Gate |
|---|---|---|---|---|
| GET | `admin/references` | `admin.references.index` | Inertia view `admin/references/Index` | `role:admin` |
| GET | `admin/references/departments` | `admin.references.departments.index` | `Admin\References\DepartmentController@index` | `role:admin` |
| POST | `admin/references/departments` | `admin.references.departments.store` | `Admin\References\DepartmentController@store` | `role:admin` |
| PATCH | `admin/references/departments/{department}` | `admin.references.departments.update` | `Admin\References\DepartmentController@update` | `role:admin` |
| DELETE | `admin/references/departments/{department}` | `admin.references.departments.destroy` | `Admin\References\DepartmentController@destroy` | `role:admin` |
| POST | `admin/references/departments/{department}/restore` | `admin.references.departments.restore` | `Admin\References\DepartmentController@restore` | `role:admin`, `withTrashed` |
| GET | `admin/references/program-offerings` | `admin.references.program-offerings.index` | `Admin\References\ProgramOfferingController@index` | `role:admin` |
| POST | `admin/references/program-offerings` | `admin.references.program-offerings.store` | `Admin\References\ProgramOfferingController@store` | `role:admin` |
| PATCH | `admin/references/program-offerings/{program_offering}` | `admin.references.program-offerings.update` | `Admin\References\ProgramOfferingController@update` | `role:admin` |
| DELETE | `admin/references/program-offerings/{program_offering}` | `admin.references.program-offerings.destroy` | `Admin\References\ProgramOfferingController@destroy` | `role:admin` |
| POST | `admin/references/program-offerings/{program_offering}/restore` | `admin.references.program-offerings.restore` | `Admin\References\ProgramOfferingController@restore` | `role:admin`, `withTrashed` |
| GET | `admin/references/document-types` | `admin.references.document-types.index` | `Admin\References\DocumentTypeController@index` | `role:admin` |
| POST | `admin/references/document-types` | `admin.references.document-types.store` | `Admin\References\DocumentTypeController@store` | `role:admin` |
| PATCH | `admin/references/document-types/{document_type}` | `admin.references.document-types.update` | `Admin\References\DocumentTypeController@update` | `role:admin` |
| DELETE | `admin/references/document-types/{document_type}` | `admin.references.document-types.destroy` | `Admin\References\DocumentTypeController@destroy` | `role:admin` |
| POST | `admin/references/document-types/{document_type}/restore` | `admin.references.document-types.restore` | `Admin\References\DocumentTypeController@restore` | `role:admin`, `withTrashed` |
| GET | `admin/references/level-requirements` | `admin.references.level-requirements.index` | `Admin\References\LevelCredentialRequirementController@index` | `role:admin` |
| POST | `admin/references/level-requirements` | `admin.references.level-requirements.store` | `Admin\References\LevelCredentialRequirementController@store` | `role:admin` |
| PATCH | `admin/references/level-requirements/{level_credential_requirement}` | `admin.references.level-requirements.update` | `Admin\References\LevelCredentialRequirementController@update` | `role:admin` |
| DELETE | `admin/references/level-requirements/{level_credential_requirement}` | `admin.references.level-requirements.destroy` | `Admin\References\LevelCredentialRequirementController@destroy` | `role:admin` |
| POST | `admin/references/level-requirements/{level_credential_requirement}/restore` | `admin.references.level-requirements.restore` | `Admin\References\LevelCredentialRequirementController@restore` | `role:admin`, `withTrashed` |

Behaviour: [Admissions](modules/admissions.md) (reference data drives the application form).

---

## SAO (`routes/sao.php`)

Group: prefix `sao`, name `sao.`, `auth` + `verified` + **`role:sao,admin`**.

| Method | URI | Name | Controller@action | Middleware/Gate |
|---|---|---|---|---|
| GET | `sao/dashboard` | `sao.dashboard` | `Sao\ApplicationReviewController@dashboard` | `role:sao,admin` |
| GET | `sao/applications` | `sao.applications.index` | `Sao\ApplicationReviewController@index` | `role:sao,admin` |
| GET | `sao/applications/{application}` | `sao.applications.show` | `Sao\ApplicationReviewController@show` | `role:sao,admin` |
| POST | `sao/applications/{application}/triage` | `sao.applications.triage` | `Sao\ApplicationReviewController@triage` | `role:sao,admin` |
| POST | `sao/applications/{application}/decide` | `sao.applications.decide` | `Sao\ApplicationReviewController@decide` | `role:sao,admin` |
| POST | `sao/applications/{application}/restore-prior` | `sao.applications.restorePrior` | `Sao\ApplicationReviewController@restorePrior` | `role:sao,admin` |
| POST | `sao/applications/{application}/documents/{document}/accept` | `sao.applications.documents.accept` | `Sao\ApplicationReviewController@acceptDocument` | `role:sao,admin`, `scopeBindings` |
| POST | `sao/applications/{application}/documents/{document}/reject` | `sao.applications.documents.reject` | `Sao\ApplicationReviewController@rejectDocument` | `role:sao,admin`, `scopeBindings` |
| GET | `sao/courses` | `sao.courses.index` | `Sao\CourseController@index` | `role:sao,admin` |
| GET | `sao/courses/create` | `sao.courses.create` | `Sao\CourseController@create` | `role:sao,admin` |
| POST | `sao/courses` | `sao.courses.store` | `Sao\CourseController@store` | `role:sao,admin` |
| GET | `sao/courses/{course}/edit` | `sao.courses.edit` | `Sao\CourseController@edit` | `role:sao,admin` |
| PATCH | `sao/courses/{course}` | `sao.courses.update` | `Sao\CourseController@update` | `role:sao,admin` |
| POST | `sao/courses/{course}/assign-lecturer` | `sao.courses.assignLecturer` | `Sao\CourseController@assignLecturer` | `role:sao,admin` |
| POST | `sao/courses/{course}/approve` | `sao.courses.approve` | `Sao\CourseController@approve` | `role:sao,admin` |
| POST | `sao/courses/{course}/reject` | `sao.courses.reject` | `Sao\CourseController@reject` | `role:sao,admin` |
| POST | `sao/courses/{course}/publish-results` | `sao.courses.publishResults` | `Sao\CourseController@publishResults` | `role:sao,admin` |
| GET | `sao/disputes` | `sao.disputes.index` | `Sao\ResultDisputeController@index` | `role:sao,admin` |
| POST | `sao/disputes/{dispute}/review` | `sao.disputes.review` | `Sao\ResultDisputeController@review` | `role:sao,admin` |

Behaviour: applications → [Admissions](modules/admissions.md); courses/disputes → [Course management](modules/course-management.md).

---

## Lecturer (`routes/lecturer.php`)

Group: prefix `lecturer`, name `lecturer.`, `auth` + `verified` + **`role:lecturer`**. The
sessions/attendance/assignments/results block is wrapped in `scopeBindings` (children bound to the
parent `{course}`).

| Method | URI | Name | Controller@action | Middleware/Gate |
|---|---|---|---|---|
| GET | `lecturer/courses` | `lecturer.courses.index` | `Lecturer\CourseController@index` | `role:lecturer` |
| GET | `lecturer/courses/{course}/edit` | `lecturer.courses.edit` | `Lecturer\CourseController@edit` | `role:lecturer` |
| PATCH | `lecturer/courses/{course}/plan` | `lecturer.courses.update` | `Lecturer\CourseController@update` | `role:lecturer` |
| POST | `lecturer/courses/{course}/submit` | `lecturer.courses.submit` | `Lecturer\CourseController@submit` | `role:lecturer` |
| GET | `lecturer/courses/{course}/sessions` | `lecturer.courses.sessions.index` | `Lecturer\CourseSessionController@index` | `role:lecturer`, `scopeBindings` |
| POST | `lecturer/courses/{course}/sessions` | `lecturer.courses.sessions.store` | `Lecturer\CourseSessionController@store` | `role:lecturer`, `scopeBindings` |
| PATCH | `lecturer/courses/{course}/sessions/{session}` | `lecturer.courses.sessions.update` | `Lecturer\CourseSessionController@update` | `role:lecturer`, `scopeBindings` |
| DELETE | `lecturer/courses/{course}/sessions/{session}` | `lecturer.courses.sessions.destroy` | `Lecturer\CourseSessionController@destroy` | `role:lecturer`, `scopeBindings` |
| GET | `lecturer/courses/{course}/sessions/{session}/attendance` | `lecturer.courses.sessions.attendance` | `Lecturer\CourseSessionController@attendance` | `role:lecturer`, `scopeBindings` |
| POST | `lecturer/courses/{course}/sessions/{session}/attendance` | `lecturer.courses.sessions.markAttendance` | `Lecturer\CourseSessionController@markAttendance` | `role:lecturer`, `scopeBindings` |
| GET | `lecturer/courses/{course}/assignments` | `lecturer.courses.assignments.index` | `Lecturer\AssignmentController@index` | `role:lecturer`, `scopeBindings` |
| POST | `lecturer/courses/{course}/assignments` | `lecturer.courses.assignments.store` | `Lecturer\AssignmentController@store` | `role:lecturer`, `scopeBindings` |
| PATCH | `lecturer/courses/{course}/assignments/{assignment}` | `lecturer.courses.assignments.update` | `Lecturer\AssignmentController@update` | `role:lecturer`, `scopeBindings` |
| DELETE | `lecturer/courses/{course}/assignments/{assignment}` | `lecturer.courses.assignments.destroy` | `Lecturer\AssignmentController@destroy` | `role:lecturer`, `scopeBindings` |
| GET | `lecturer/courses/{course}/assignments/{assignment}/submissions` | `lecturer.courses.assignments.submissions` | `Lecturer\AssignmentController@submissions` | `role:lecturer`, `scopeBindings` |
| POST | `lecturer/courses/{course}/assignments/{assignment}/submissions/{submission}/grade` | `lecturer.courses.assignments.grade` | `Lecturer\AssignmentController@grade` | `role:lecturer`, `scopeBindings` |
| GET | `lecturer/courses/{course}/results` | `lecturer.courses.results.index` | `Lecturer\CourseResultController@index` | `role:lecturer`, `scopeBindings` |
| POST | `lecturer/courses/{course}/results` | `lecturer.courses.results.store` | `Lecturer\CourseResultController@store` | `role:lecturer`, `scopeBindings` |

Behaviour: [Course management](modules/course-management.md).

---

## Student (`routes/student.php`)

Group: prefix `student`, name `student.`, `auth` + `verified` + **`role:student,admin`**.

| Method | URI | Name | Controller@action | Middleware/Gate |
|---|---|---|---|---|
| GET | `student/payments` | `student.payments.index` | `Student\PaymentController@index` | `role:student,admin` |
| POST | `student/payments` | `student.payments.store` | `Student\PaymentController@store` | `role:student,admin` |
| GET | `student/payments/{payment}/receipt` | `student.payments.receipt` | `Student\PaymentController@receipt` | `role:student,admin` |
| POST | `student/deferrals` | `student.deferrals.store` | `Student\DeferralController@store` | `role:student,admin` |
| GET | `student/courses` | `student.courses.index` | `Student\CourseController@index` | `role:student,admin` |
| GET | `student/attendance` | `student.attendance.index` | `Student\AttendanceController@index` | `role:student,admin` |
| GET | `student/assignments` | `student.assignments.index` | `Student\AssignmentController@index` | `role:student,admin` |
| POST | `student/assignments/{assignment}/submit` | `student.assignments.submit` | `Student\AssignmentController@submit` | `role:student,admin` |
| GET | `student/results` | `student.results.index` | `Student\CourseResultController@index` | `role:student,admin` |
| POST | `student/results/{result}/dispute` | `student.results.dispute` | `Student\CourseResultController@dispute` | `role:student,admin` |
| POST | `student/notifications/{notification}/read` | `student.notifications.read` | `Student\NotificationController@markAsRead` | `role:student,admin` |
| POST | `student/notifications/read-all` | `student.notifications.read-all` | `Student\NotificationController@markAllAsRead` | `role:student,admin` |

Behaviour: payments/deferrals → [Payments](modules/payments.md) / [Exam gating](modules/exam-gating.md); courses/attendance/assignments/results → [Course management](modules/course-management.md); notifications → [Notifications](modules/notifications.md).

---

## Accountant (`routes/accountant.php`)

Group: prefix `accountant`, name `accountant.`, `auth` + `verified` + **`role:accountant,admin`**.

| Method | URI | Name | Controller@action | Middleware/Gate |
|---|---|---|---|---|
| GET | `accountant/dashboard` | `accountant.dashboard` | `Dashboards\AccountantDashboardController` (invokable) | `role:accountant,admin` |
| GET | `accountant/payments` | `accountant.payments.index` | `Accountant\PaymentController@index` | `role:accountant,admin` |
| GET | `accountant/payments/{payment}` | `accountant.payments.show` | `Accountant\PaymentController@show` | `role:accountant,admin` |
| POST | `accountant/payments/{payment}/validate` | `accountant.payments.validate` | `Accountant\PaymentController@validatePayment` | `role:accountant,admin` |
| POST | `accountant/payments/{payment}/reject` | `accountant.payments.reject` | `Accountant\PaymentController@reject` | `role:accountant,admin` |
| GET | `accountant/deferrals` | `accountant.deferrals.index` | `Accountant\DeferralController@index` | `role:accountant,admin` |
| GET | `accountant/deferrals/{deferral}` | `accountant.deferrals.show` | `Accountant\DeferralController@show` | `role:accountant,admin` |
| POST | `accountant/deferrals/{deferral}/approve` | `accountant.deferrals.approve` | `Accountant\DeferralController@approve` | `role:accountant,admin` |
| POST | `accountant/deferrals/{deferral}/reject` | `accountant.deferrals.reject` | `Accountant\DeferralController@reject` | `role:accountant,admin` |

Behaviour: payments → [Payments & receipts](modules/payments.md); deferrals → [Exam gating](modules/exam-gating.md).

---

## Authentication (Fortify)

Registered by **Laravel Fortify** (vendor), not in the app route files. Prefix is empty
(`config/fortify.php`), guard `web`, view routes enabled. Features: registration, password reset,
email verification, two-factor (with confirm + confirmPassword). The configured username field is
`email` (lowercased), but `FortifyServiceProvider` overrides resolution so the login field accepts
**four identifiers** (email / employee_id / phone / matricule) — see [Security §1.1](security.md).
Successful login is handled by `App\Http\Responses\LoginResponse`, which redirects by **role
priority** (Admin → SAO → Accountant → Lecturer → Student → Applicant), not a fixed `/dashboard`.

| Method | URI | Name | Middleware / Limiter |
|---|---|---|---|
| GET | `login` | `login` | `guest` |
| POST | `login` | — | `guest`, `throttle:login` (5/min by email+IP) |
| POST | `logout` | `logout` | `auth` |
| GET | `register` | `register` | `guest` |
| POST | `register` | — | `guest` |
| GET | `forgot-password` | `password.request` | `guest` |
| POST | `forgot-password` | `password.email` | `guest` |
| GET | `reset-password/{token}` | `password.reset` | `guest` |
| POST | `reset-password` | `password.update` | `guest` |
| GET | `email/verify` | `verification.notice` | `auth` |
| GET | `email/verify/{id}/{hash}` | `verification.verify` | `auth`, `signed`, `throttle:6,1` |
| POST | `email/verification-notification` | `verification.send` | `auth`, `throttle:verification` (3/min) |
| GET | `user/confirm-password` | `password.confirm` | `auth` |
| POST | `user/confirm-password` | — | `auth` |
| GET | `user/confirmed-password-status` | `password.confirmation` | `auth` |
| GET | `two-factor-challenge` | `two-factor.login` | `guest` |
| POST | `two-factor-challenge` | — | `guest`, `throttle:two-factor` (5/min) |
| POST | `user/two-factor-authentication` | `two-factor.enable` | `auth`, `password.confirm` |
| DELETE | `user/two-factor-authentication` | `two-factor.disable` | `auth`, `password.confirm` |
| POST | `user/confirmed-two-factor-authentication` | `two-factor.confirm` | `auth`, `password.confirm` |
| GET | `user/two-factor-qr-code` | `two-factor.qr-code` | `auth`, `password.confirm` |
| GET | `user/two-factor-recovery-codes` | `two-factor.recovery-codes` | `auth`, `password.confirm` |
| POST | `user/two-factor-recovery-codes` | — | `auth`, `password.confirm` |
| GET | `user/two-factor-secret-key` | `two-factor.secret-key` | `auth`, `password.confirm` |

> Exact Fortify route names/paths depend on the installed Fortify version; verify with
> `php artisan route:list --only-vendor`. App-side customisations (login redirect, limiters,
> username lowercasing) live in `app/Providers/FortifyServiceProvider.php` and `config/fortify.php`.
> Auth behaviour: [Security](security.md).

---

## Console (`routes/console.php`)

Not HTTP routes — the scheduler. `audit:prune` (`App\Console\Commands\PruneAuditLogs`) runs daily,
`withoutOverlapping`, removing `audit_logs` older than `AuditLog::RETENTION_DAYS` (730 days).
