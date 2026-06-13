# Security Audit — Student Management System

Read-only application-security review. Scope: routing/auth surface, authorization, the
admissions + admin user-management + reactivation flows, file upload/download, audit log,
rate limiting, enumeration, mass assignment, injection, secrets, and session config.

**Overall:** the codebase is in good shape. Authorization is consistently enforced via the
`role` middleware plus explicit ownership checks; the document-download IDOR surface is
correctly guarded and well-tested; form requests are thorough; no SQL injection or unsafe
`v-html`/mass-assignment was found. The findings below are mostly hardening items, with one
High around the unauthenticated reactivation path and one Medium around missing throttling.

Severity counts: **Critical 0 · High 1 · Medium 2 · Low 3 · Info 2**

---

## [SEC-1] Require email-ownership proof before reactivating a soft-deleted account on registration

- Severity: **High** / Category: Security (Broken Access Control / Account Integrity) / Location: `app/Actions/Fortify/CreateNewUser.php:33-70`, route `POST /register` (`routes` via Fortify, middleware `web`, `guest:web` — **no throttle**) / Attacker model: **anonymous**

**Problem**
Registration doubles as an account-reactivation path. When the submitted email matches a
**soft-deleted** user row, `CreateNewUser::create()` unconditionally:

1. `restore()`s the row (un-deactivates it),
2. `forceFill()` overwrites `name` and `password` with attacker-supplied values,
3. nulls `email_verified_at` and `remember_token`,
4. detaches all roles.

No proof that the registrant controls the mailbox is required before these destructive
writes happen. Concrete abuse by an anonymous attacker who knows a deactivated email
(staff emails follow the predictable `firstname@institution` pattern; student/staff emails
are easily guessed or leaked):

- **Reverses an access-revocation control.** Deactivation (soft delete) is how an admin
  cuts off a dismissed/compromised staff account. Anyone can silently un-delete that row,
  turning it back into an active (if roleless) account and breaking the admin's mental model
  that "deactivated = gone".
- **Destroys the intended SAO re-attach flow.** The Phase 9 / §13.4 reactivation contract
  assumes the prior row is still `trashed`; once an attacker flips it to active+roleless,
  `RestorePriorEnrollment` and the admin "Restore" button no longer see a trashed row.
- **Overwrites the legitimate user's `name`/`password`** with attacker values (integrity/DoS
  of the dormant identity).
- **User enumeration / state oracle:** an active email returns `422` (unique rule), a
  soft-deleted email returns a successful redirect+login, and an unknown email creates a new
  user — three distinguishable outcomes reveal account state to an anonymous caller.

Actual session access is still gated by `verified` middleware and the role detach, so the
attacker does not *immediately* gain a privileged session — that containment is why this is
High and not Critical — but unauthenticated, destructive, arbitrary-account state change
that reverses a security control is a High-severity access-control defect.

**Proposed solution**
Do not mutate an existing (even soft-deleted) row from an unauthenticated registration POST
without ownership proof. Options, in order of preference:

1. **Verify-first reactivation.** When the email matches a trashed row, do *not* restore or
   overwrite anything inline. Instead send a verification/setup link to that address and only
   perform `restore()`+overwrite after the link is followed (proving mailbox control). Until
   then, return the same generic "check your email" response used for a brand-new
   registration so the response is indistinguishable.
2. **Move reactivation out of self-service entirely** and make it admin-only
   (`UserController::restore` already exists and is `role:admin`-guarded), so anonymous
   registration with a known-deactivated email yields the same generic "if an account exists,
   we've emailed you" response and never touches the row.

Also normalize the three response branches (new / active-duplicate / trashed-match) to a
single generic outcome to kill the enumeration oracle, and add throttling per SEC-2.

**Acceptance criteria**
- An anonymous `POST /register` with a soft-deleted email does **not** change that row's
  `name`, `password`, `email_verified_at`, `deleted_at`, or role assignments until mailbox
  ownership is proven (new feature test).
- The HTTP response/redirect for new-email, active-email, and trashed-email registrations is
  observably identical (status, redirect target, flash) — assert in a test that exercises all
  three (enumeration test).
- Existing reactivation tests in `tests/Feature/Auth/RegistrationTest.php` are updated to the
  verify-first contract rather than inline restore.
- A negative test asserts a deactivated **staff/admin** email cannot be flipped to active by
  an anonymous registration.

---

## [SEC-2] Add rate limiting to registration, password-reset, and email-verification endpoints

- Severity: **Medium** / Category: Security (Rate Limiting / Brute Force / Resource Abuse) / Location: `app/Providers/FortifyServiceProvider.php:110-121` (only `login` + `two-factor` limiters defined), `config/fortify.php` (`limiters` map) / Attacker model: **anonymous**

**Problem**
`php artisan route:list -v` confirms only `POST /login` (`throttle:login`) and
`POST /two-factor-challenge` (`throttle:two-factor`) are throttled. The following are served
with `web` + `guest:web` only and have **no rate limit**:

- `POST /register` — enables high-volume enumeration / mass reactivation abuse (see SEC-1) and
  bulk account creation.
- `POST /forgot-password` (`password.email`) — password-reset **mail bombing** of any address
  and enumeration via timing/response.
- `POST /reset-password` (`password.update`) — brute force of reset tokens.
- `POST /email/verification-notification` (`verification.send`) — verification-mail bombing.

Reset tokens are long random strings (hard to brute in practice), but the mail-bombing and
enumeration vectors are real and cheap.

**Proposed solution**
Define named limiters and attach them. Either via `config/fortify.php` where supported, or by
adding `->middleware('throttle:...')` equivalents. Concretely, register limiters in
`FortifyServiceProvider::configureRateLimiting()`:

```php
RateLimiter::for('forgot-password', fn (Request $r) =>
    Limit::perMinute(5)->by(Str::lower((string) $r->input('email')).'|'.$r->ip()));
RateLimiter::for('reset-password', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
RateLimiter::for('register', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
RateLimiter::for('verification-notification', fn (Request $r) =>
    Limit::perMinute(3)->by(optional($r->user())->id ?: $r->ip()));
```

then bind them to the Fortify routes (Fortify exposes a `limiters` config for `login`/
`two-factor`; for the others, apply the throttle middleware to the route group or publish the
Fortify routes and add `->middleware('throttle:register')` etc.).

**Acceptance criteria**
- A feature test asserts the 6th `POST /forgot-password` within a minute from one IP returns
  `429`.
- Same for `register`, `reset-password`, and `email/verification-notification`.
- `route:list -v` shows a `throttle:*` entry on each of those four routes.

---

## [SEC-3] Do not provision a default admin (`admin@example.com` / `password`) outside local

- Severity: **Medium** / Category: Security (Hardcoded Credentials / Insecure Defaults) / Location: `database/seeders/DatabaseSeeder.php:24-42`, `database/seeders/LocalStaffSeeder.php:61-68` / Attacker model: **anonymous (if seeded in a deployed env)**

**Problem**
`DatabaseSeeder` unconditionally creates `test@example.com` and `admin@example.com`, both with
`password` (and `LocalStaffSeeder` adds `lecturer@/accountant@/sao@example.com`, same password),
all with `email_verified_at` pre-set and `admin@example.com` granted the Admin role. There is no
`app()->isProduction()` / `app()->environment('local')` guard. A `php artisan migrate --seed`
(or `db:seed`) accidentally run against a staging/production database mints a known-credential
admin that an anonymous attacker can log straight into. The known local credentials are accepted
for local dev, but the seeder must refuse to plant them in a non-local environment.

**Proposed solution**
Guard the credential-seeding in `DatabaseSeeder::run()` (and `LocalStaffSeeder`) behind an
environment check:

```php
if (! app()->environment('local', 'testing')) {
    return; // never seed demo/admin credentials outside local/testing
}
```

or move the demo users into a dedicated `LocalSeeder` that is only ever called from the local
seed path. For real environments, provision the first admin via a console command that forces a
random password + invitation link (the `CreateUserAction` pattern already does this safely).

**Acceptance criteria**
- Running the seeder with `APP_ENV=production` creates **no** `*@example.com` users and no
  default-password admin (test using `App::detectEnvironment` or config override).
- Local/testing seeding behavior is unchanged.

---

## [SEC-4] Throttle the unauthenticated-adjacent JSON/lookup and download endpoints

- Severity: **Low** / Category: Security (Rate Limiting / Resource Abuse) / Location: `routes/web.php:36-43` (`application.documents.download`, `api.v1.program-offerings.index`, `api.v1.level-requirements.index`), `routes/admin.php:19` (`admin.audit-logs.index`) / Attacker model: **authenticated applicant / admin**

**Problem**
These endpoints sit behind `auth`+`verified` (and `role:admin` for audit-logs) but have no
per-route throttle. The two `api/v1` lookup endpoints and the audit-log JSON endpoint are
queried in loops by any authenticated user/admin; the document-download endpoint streams files.
None expose another user's data (authorization is correct), so impact is limited to DB/IO load
amplification by an authenticated actor — hence Low — but a cheap throttle is warranted.

**Proposed solution**
Add a modest `throttle:60,1` (or a named limiter) to the `api/v1` group and the document
download route, and `throttle:120,1` to `admin.audit-logs.index`.

**Acceptance criteria**
- `route:list -v` shows a `throttle:*` entry on the `api/v1/*`, document-download, and
  `admin/audit-logs` routes.
- Existing functional tests for those endpoints still pass.

---

## [SEC-5] Record an audit entry for profile name/email changes and admin user updates

- Severity: **Low** / Category: Security (Audit Completeness / Traceability) / Location: `app/Http/Controllers/Settings/ProfileController.php:31-44`, `app/Http/Controllers/Admin/UserController.php:114-135` / Attacker model: **authenticated user / admin**

**Problem**
The `User` model intentionally does **not** use `RecordsAudit` (confirmed by the comment in
`CreateUserAction.php:46-48`). As a result, two sensitive identity mutations leave no trail in
the otherwise-immutable audit log:

- Self-service profile update (`ProfileController::update`) — including **email change**, which
  also nulls `email_verified_at`. An email change is a classic account-takeover precursor and
  should be auditable.
- Admin `UserController::update` (name + staff profile fields).

Role assignment/revocation and user creation *are* audited (via manual `AuditLog::record`
calls), so the gap is specifically these two update paths.

**Proposed solution**
Add explicit `AuditLog::record(AuditAction::Updated, $user, $diff, userId: $actorId)` calls in
both controllers after a successful save, reusing the diff shape from `RecordsAudit::auditDiff()`
(exclude `password`/token fields — they're already not changed here). For email changes, include
the before/after email in `changes`.

**Acceptance criteria**
- Changing one's own email writes an `Updated` audit row referencing the User subject, with the
  email before/after in `changes` and **no** password/token material.
- Admin updating a user's name/profile writes an `Updated` audit row attributed to the admin's
  `user_id`.
- Tests assert both, and assert no sensitive fields leak into `changes`.

---

## [SEC-6] Login identifier-existence timing oracle in `authenticateUsing`

- Severity: **Low** / Category: Security (User Enumeration) / Location: `app/Providers/FortifyServiceProvider.php:58-73` / Attacker model: **anonymous**

**Problem**
The custom resolver runs `Hash::check()` **only when a user is found** (`$user !== null && Hash::check(...)`).
For a non-existent identifier no hash comparison occurs, so responses for "unknown identifier"
return measurably faster than "known identifier, wrong password". Combined with the three
identifier types (email / employee_id / matricule), this is a timing oracle for whether an
email, employee ID, or matricule exists. The `throttle:login` limiter (5/min) blunts but does
not eliminate it. Low because matricules/employee IDs are semi-public and login is throttled.

**Proposed solution**
Always perform a constant-work hash comparison even when no user is found, e.g. compare against
a precomputed dummy bcrypt hash when `$user === null`:

```php
$hash = $user?->password ?? '$2y$12$'.str_repeat('x', 53); // or a real Hash::make() done once at boot
if ($user !== null && Hash::check($password, $hash)) { return $user; }
Hash::check($password, $hash); // burn equivalent time on the miss path
return null;
```

**Acceptance criteria**
- The miss path (`unknown identifier`) and the wrong-password path both invoke `Hash::check`
  once (assertable by mocking/spy or by a coarse timing-parity test).
- Existing authentication tests still pass.

---

## [SEC-7] Confirm `Content-Disposition` filename handling on document download (no action expected)

- Severity: **Info** (needs verification) / Category: Security (Header Injection) / Location: `app/Http/Controllers/Applications/DocumentDownloadController.php:28-31` / Attacker model: **authenticated applicant (uploader)**

**Problem**
`original_filename` is stored verbatim from `UploadedFile::getClientOriginalName()`
(`ApplicationController::store:174`) and passed as the download filename to
`Storage::disk('local')->download($document->file_path, $document->original_filename)`. Laravel
delegates to Symfony's `HeaderUtils::makeDisposition()`, which sanitizes and RFC-encodes the
filename and emits an ASCII fallback, so header/response-splitting injection is **not**
exploitable here. The stored `file_path` is a hashed name from `$file->store('applications')`
(no path traversal). No fix required; recorded so a future refactor doesn't replace the safe
`download()` helper with hand-built headers.

**Proposed solution**
Keep using `Storage::download()` / `response()->download()`; never interpolate
`original_filename` into a manually constructed `Content-Disposition` header. Optionally store a
sanitized copy of the filename at upload time.

**Acceptance criteria**
- A test uploads a document whose original name contains `"`, `;`, CR/LF and `../`, downloads it,
  and asserts the response is `200` with a single, properly-encoded `Content-Disposition` header
  (no header split, no traversal).

---

## [SEC-8] Production session/debug hardening defaults

- Severity: **Info** / Category: Security (Insecure Defaults) / Location: `.env.example` (`APP_DEBUG=true`, no `SESSION_SECURE_COOKIE`), `config/session.php` (`secure => env('SESSION_SECURE_COOKIE')` default null, `same_site => 'lax'`) / Attacker model: **network attacker (prod misconfig)**

**Problem**
`.env.example` ships `APP_DEBUG=true` and does not set `SESSION_SECURE_COOKIE`, so in a
deployment that copies the example, debug stack traces leak and the session cookie is not forced
`Secure`. `http_only` (true) and `same_site` (`lax`) defaults are fine. These are deployment
concerns, not code defects, but worth a documented production checklist since the app handles
PII (applications, documents, audit IPs).

**Proposed solution**
Document a production `.env` baseline: `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`,
`SESSION_ENCRYPT=true` (optional), and ensure HTTPS-only cookies. Consider a deploy-time check
that fails if `APP_DEBUG=true` while `APP_ENV=production`.

**Acceptance criteria**
- A documented production env baseline exists (deploy docs / `.env.production` template).
- Optional: a boot-time assertion or health check flags `APP_DEBUG && isProduction()`.

---

## Verified-clean areas (no finding)

- **Document download authorization** — owner-or-SAO/admin check is correct and covered by
  `tests/Feature/Applications/DocumentDownloadTest.php` (owner, SAO, admin allowed; other
  applicant, non-SAO/admin staff, cross-application, and guest all rejected).
- **Application::show ownership** — `abort_if($application->user_id !== $request->user()->id, 403)`
  (`ApplicationController.php:87`).
- **SAO routes** — guarded by `role:sao,admin`; `RestorePriorEnrollment` re-validates
  `prior->user_id === applicant->id` (`RestorePriorEnrollment.php:41-45`), closing the
  `prior_profile_id` IDOR despite the loose `exists` rule in the form request.
- **Terminal-state guards** — `DecideApplicationAction`/`RestorePriorEnrollment` reject already
  finalized applications.
- **Mass assignment** — all `create()`/`fill()` calls use explicit, validated field lists; no
  `$request->all()` into models; `User` `#[Fillable]` limited to `name/email/password`.
- **Password change** — `current_password` + `password.confirm` middleware enforced
  (`PasswordUpdateRequest`, `SecurityController`).
- **SQL injection** — only `selectRaw('status, COUNT(*) ...')` with no user input; all filters
  use bound query-builder methods.
- **XSS** — single `v-html` is `qrCodeSvg` from Fortify's server-generated 2FA QR
  (`TwoFactorSetupModal.vue:174`); user-controlled strings render through Vue's escaped
  interpolation.
- **Audit log immutability + secret exclusion** — `AuditLog` blocks update/delete;
  `RecordsAudit::auditExclude()` strips password/token/2FA fields; manual `record()` calls log
  only non-sensitive attributes.
- **Secrets** — no `.env` committed (`.gitignore` covers `.env*`); no hardcoded secrets outside
  seeders.
- **Invitation flow** — `CreateUserAction` sets a random 64-char password, never logs it, sends a
  Fortify reset token (72h expiry); `resendInvite` is `role:admin`-guarded.
