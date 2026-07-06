# Security model

How SchuLyf authenticates users, authorises actions, records significant writes, proves payment
receipts are genuine, and hardens the inline file viewers. Every claim here is verified against the
code as it stands; the global audit ([`AUDIT.md`](../AUDIT.md), findings `AUD-001…034`) is fully
remediated and informs much of the posture below.

> Cross-references: [architecture.md](architecture.md) (request lifecycle, layering),
> [routes.md](routes.md) (the route split + endpoint inventory), [data-model.md](data-model.md)
> (tables), and the [ADRs](adr/) for locked decisions.

---

## 1. Authentication (Fortify)

Auth is backed by [Laravel Fortify](https://laravel.com/docs/fortify) on the `web` guard. There is
no token API — every authenticated surface is a session-authenticated web route (see
[routes.md](routes.md)). Fortify features enabled in `config/fortify.php`:

| Feature | Notes |
|---|---|
| Registration | Public `/register`; applicant funnel only (see §1.4) |
| Reset passwords | Also the staff first-login path and the soft-deleted reactivation path |
| Email verification | `verified` middleware gates every authenticated route — see §1.3 |
| Two-factor auth | TOTP with `confirm: true` + `confirmPassword: true` (opt-in, per user) |

Username field is `email`; `lowercase_usernames` is `true`. Views are rendered through Inertia in
`FortifyServiceProvider::configureViews()` (`auth/Login`, `auth/Register`, `auth/ResetPassword`,
`auth/VerifyEmail`, `auth/TwoFactorChallenge`, `auth/ConfirmPassword`).

### 1.1 The four-identifier login resolver

`Fortify::authenticateUsing()` (in `FortifyServiceProvider::configureAuthentication()`) accepts the
single login field as any of **four** identifiers, resolved in **one** query:

| Identifier | Column / relation | Disambiguator |
|---|---|---|
| Email | `users.email` | contains `@` |
| Employee ID | `users.employee_id` | `[a-z0-9]` prefix |
| Phone | `users.phone` (normalised) | starts with `+` |
| Matricule | `student_profiles.matricule` (via `whereHas`) | `stm-…` prefix |

The namespaces can't collide, so a single `WHERE (email = ? OR employee_id = ? OR phone = ? OR
EXISTS matricule = ?)` is unambiguous. The phone clause is only added when the input normalises to a
phone (`self::normalizePhoneNumber()` ≠ null) so it never degrades into `phone IS NULL`.

> **Drift note:** the prompt and an earlier audit framed this as a *three*-identifier resolver
> (email / matricule / employee_id). The code resolves **four** — phone was added as the
> secondary identifier (backlog item B9). Phone matching is normalised before comparison.

**Timing-oracle defence (`AUD-025`).** On a resolver miss the code still runs
`Hash::check($password, self::DUMMY_PASSWORD_HASH)` against a fixed throwaway bcrypt hash before
returning `null`, so a login attempt costs one hash check whether or not the identifier resolves —
response time stops leaking which identifiers exist across all four namespaces.

### 1.2 Login throttling & other rate limits

Limiters live in `FortifyServiceProvider::configureRateLimiting()`; named limiters are wired in
either via `config/fortify.php`’s `limiters` map or `throttleFortifyRoutes()` (which attaches
`throttle:…` middleware after boot to routes Fortify doesn’t hook natively).

| Limiter | Budget | Key | Applied to |
|---|---|---|---|
| `login` | 5 / min | `lower(username)\|ip` | Login (`fortify.limiters.login`) |
| `two-factor` | 5 / min | `login.id` session value | 2FA challenge (`fortify.limiters.two-factor`) |
| `verification` | 3 / min | user id (else ip) | Verification send + click (`fortify.limiters.verification`) |
| `register` | 5 / min | ip | `register.store` route (`AUD-011`) |
| `forgot-password` | 3 / min | `lower(email)\|ip` | `password.email` + `password.update` (`AUD-011`) |
| `lookups` | 60 / min | user id (else ip) | `api/v1` lookups, file download/view, public receipt-verify (`AUD-026`) |
| `audit-logs` | 30 / min | user id (else ip) | Admin audit-log modal endpoint (`AUD-026`) |

### 1.3 Email verification

`User implements MustVerifyEmail`. The authenticated route groups carry the `verified` middleware
(e.g. `routes/web.php`’s `['auth', 'verified']` group), so an unverified account cannot reach any
dashboard or feature. The `verification` limiter caps notification sends and link click-throughs.

### 1.4 Password policy

`AppServiceProvider::configureDefaults()` sets `Password::defaults()`:

- **Production:** `min(12)` + mixed case + letters + numbers + symbols + `uncompromised()`
  (Have-I-Been-Pwned check).
- **Local / non-production:** no extra constraints (faster DX).

`DB::prohibitDestructiveCommands()` is also enabled in production.

### 1.5 Staff invite-link credential flow

Admins **never set staff passwords.** `App\Actions\Admin\CreateUserAction` provisions a
staff/admin user with a random 64-char password the user never sees, marks them
`email_verified_at = now()` (the invite link is itself proof of mailbox control), assigns exactly
one role + profile, then emails a single-use **password-reset link** via `UserInvitationMail`
(queued; `mail.user-invitation` markdown). The reset link IS the first-login path. The plaintext
password is never logged, audited, or stored — `RecordsAudit` writes a single `Created` row, with
`password` redacted (§3).

> `employee_id` is deliberately outside `$fillable`; admin provisioning is its only writer
> (`forceFill`), per `AUD-007`. See [modules/admin-user-management.md](modules/admin-user-management.md).

### 1.6 Soft-deleted account reactivation (verify-first)

Registering with a soft-deleted user’s email does **not** restore the row inline (the `AUD-004`
takeover hole). Instead, a custom `PasswordBrokerUserProvider` (registered as the
`eloquent-with-trashed` auth provider in `AppServiceProvider::configureAuthProviders()`) lets a
trashed **non-staff** user prove mailbox ownership through the password-reset flow;
`ResetUserPassword::reactivate()` restores the row and writes one `RoleRevoked` audit row per
detached role (`AUD-028`). Trashed staff/admin accounts are excluded — only an admin can restore
them. Registration responses are normalised so active / trashed / unknown emails are
indistinguishable (no enumeration oracle).

---

## 2. Authorization

Three layers, used together.

### 2.1 Authorization model

Authorization is two concrete layers — **`role:*` route middleware** (§2.2) and **per-resource
ownership checks** (§2.3). There is **no ability-gate registry**: the `AppServiceProvider`
`ABILITIES` gates were retired in [ADR-0025](../adr/0025-retire-ability-gates.md). Every gate merely
restated the roles of the route group its endpoints sat behind (only 3 of 8 were ever invoked, and
none could decide anything the route middleware didn't — `Gate::define(fn ($u) => $u->hasAnyRole($roles))`
is the same test `EnsureUserHasRole` already runs). Removing them changed no authorization outcome; the
denial paths are covered by endpoint tests (`AdminAuthorizationTest`, `CoursePlanApprovalTest`,
`PublishCourseResultsTest`, `MarkAttendanceTest`).

### 2.2 Role middleware

`EnsureUserHasRole` is aliased as `role`. It takes role enum *values*:
`role:admin`, `role:sao,admin`, etc. — passing when the user holds at least one named role
(`401` if unauthenticated, `403` otherwise). It guards the per-role dashboards in `routes/web.php`
and the role groups in `routes/admin.php` / `routes/sao.php`. See [routes.md](routes.md).

### 2.3 Per-resource ownership checks

Controllers that serve a specific resource check ownership directly (not every check is a Gate). The
inline file viewers (§5) are the canonical example: each verifies the requester is the owner *or*
holds a reviewing role before streaming. Cross-resource pairing is also validated — e.g.
`DocumentViewController` aborts `404` if the document’s `application_id` doesn’t match the route’s
application.

> **Drift note (single-role staff).** §4.3 of `plan/context.md` envisaged a multi-role pivot, but
> in practice `CreateUserAction` / `ChangeUserRoleAction` enforce **one** role per staff/admin user.
> The gate/middleware machinery is multi-role capable; the *provisioning* path is single-role today.
> A multi-role switcher UI remains a deferred backlog item (B7).

---

## 3. The immutable audit log

A tamper-evident trail of significant writes is a headline feature. Two pieces:

### 3.1 `App\Models\AuditLog` — append-only

- `booted()` throws `RuntimeException` on **any** `updating` or `deleting` — rows can be created but
  never mutated or deleted through Eloquent.
- `record(AuditAction $action, ?Model $subject, ?array $changes, array $context, ?int $userId)`
  writes a row, defaulting `user_id` to `Auth::id()` and stamping `occurred_at`.
- `buildContext()` automatically merges request `ip`, `user_agent`, and route `name` into every
  entry (caller-supplied keys win).
- **Retention:** `RETENTION_DAYS = 730` (2 years). The scheduled `audit:prune` command deletes via
  the **query builder** — the sanctioned bypass of the immutability guard (`AUD-032`).

### 3.2 `App\Models\Concerns\RecordsAudit` — significant-writes only

Models that `use RecordsAudit` auto-log lifecycle events through model hooks
(`created`/`updated`/`deleted`/`restored`) producing `Created` / `Updated` (with a before/after
diff) / `Deleted` / `Restored` rows. Sensitive handling:

- `auditExclude()` — `remember_token`, `created_at`, `updated_at`, `deleted_at` are omitted from
  snapshots *and* diffs (a timestamp-only change writes **no** `Updated` row).
- `auditRedact()` — `password`, `two_factor_secret`, `two_factor_recovery_codes`: the *fact* of a
  change is recorded but the value is masked as `[redacted]` (`AUD-022`).

`User` opts into the trait, so email/name/password changes are audited with secrets masked.

### 3.3 Audit actions enumerated

`App\Enums\AuditAction` (string-backed) covers every significant write across the app:

| Group | Cases |
|---|---|
| Generic lifecycle | `Created`, `Updated`, `Deleted`, `Restored`, `StatusChanged` |
| Roles & users | `RoleAssigned`, `RoleRevoked`, `UsersImported` |
| Auth events | `LoggedIn`, `LoginFailed`, `LoggedOut` |
| Admissions | `ApplicationDecided` |
| Payments | `PaymentValidated`, `PaymentRejected`, `ReceiptIssued` |
| Deferrals | `DeferralApproved`, `DeferralRejected` |
| Courses | `CourseCreated`, `LecturerAssigned`, `CoursePlanSubmitted`, `CoursePlanApproved`, `CoursePlanRejected`, `CourseSessionScheduled`, `CourseSessionCancelled`, `CourseSessionRescheduled`, `AttendanceMarked` |
| Assignments | `AssignmentCreated`, `AssignmentSubmitted`, `AssignmentGraded` |
| Results & disputes | `ResultRecorded`, `ResultsPublished`, `DisputeRaised`, `DisputeResolved` |

Auth events are recorded by `AppServiceProvider::configureAuditListeners()` (listening on
`Login` / `Failed` / `Logout`).

### 3.4 Reading the log

Admins read the log through a modal backed by `admin/audit-logs` (inside the `routes/admin.php`
`role:admin` group + `audit-logs` throttle). Filtering/sorting is index-backed
(`AUD-008`).

---

## 4. HMAC-signed school receipts + public verification

A school receipt (`App\Models\SchoolReceipt`) is the single proof of payment and must be
forgery- and tamper-proof.

- **Immutable:** like `AuditLog`, the model throws on `updating`/`deleting` — a receipt never
  changes after issuance.
- **Canonical payload:** `receiptNumber | matricule | amount_xaf | academicYear`
  (`canonicalPayload()`).
- **Signature:** `hash_hmac('sha256', payload, config('app.key'))` (`computeSignature()`). The key
  is `APP_KEY` — server-side only; no third party needs it to verify.
- **Verification:** `verifies()` re-derives the expected signature from the receipt’s *currently
  bound* identity (matricule + academic year on the submission, amount on the receipt) and compares
  with `hash_equals()` (constant-time). Any drift — forged number, edited amount, or a receipt
  reused by a different student — fails.
- **Issuance ordering:** receipt numbers come from a one-row-per-year `receipt_sequences` counter
  under `lockForUpdate()` (`RCP-YYYY-00001`), mirroring the matricule sequence (`AUD-006`).

**Public verify endpoint.** `GET receipts/verify/{receipt_number}` (`VerifyReceiptController`,
`routes/web.php`) is **unauthenticated** and `throttle:lookups`-limited. It re-derives the HMAC and
renders the bound identity (matricule, name, level, programme, amount, academic year, issued-at)
**only when authentic**. An unknown number and a bad signature both read as `invalid` — no oracle
for which receipt numbers exist. See [modules/payments.md](modules/payments.md).

---

## 5. File-viewer hardening

Three single-action controllers stream uploaded files **inline** to authorised viewers, all
applying the same hardening (the shared inline-viewer foundation):

| Controller | Resource | Owner | Reviewer roles |
|---|---|---|---|
| `Applications\DocumentViewController` | Application document | applicant | SAO, Admin |
| `Payments\PaymentSlipViewController` | Payment slip | reporting student | Accountant, Admin |
| `Assignments\SubmissionViewController` | Assignment submission | submitting student | course lecturer, Admin |

Each enforces the same controls before responding:

1. **Authorization** — owner-or-reviewer check (`403` otherwise); cross-resource pairing validated
   (`404` on mismatch).
2. **Stored-MIME allowlist** — `INLINE_SAFE_MIMES = ['application/pdf', 'image/png', 'image/jpeg']`;
   anything else is refused **`415`** rather than served, so an unexpectedly-stored type can never
   execute as script/markup against the viewer’s session. Mirrors each upload request’s allowlist.
3. **Forced `Content-Type`** — the stored, validated MIME is sent verbatim (the browser never
   sniffs).
4. **`X-Content-Type-Options: nosniff`**.
5. **Sandbox CSP** — `default-src 'none'; sandbox; img-src 'self'; object-src 'self'`.
6. **Header-safe filename** — `"`, `\r`, `\n` stripped from the original filename before it goes
   into `Content-Disposition: inline`, preventing HTML/SVG rendering and header injection.

Matching `*DownloadController`s serve the same files as attachments (same auth, default disk).
Inline-view behaviour is covered by `tests/Feature/Files/InlineFileViewTest.php`. See
[architecture.md](architecture.md) for the shared-foundation pattern.

---

## 6. Verified-clean / deferred

Recorded so refactors don’t regress them (per `AUDIT.md`):

- Document/file authorization (including mismatched-pair `404`s), `Application::show` ownership, and
  mass-assignment discipline (`#[Fillable]`) are clean.
- No SQL injection, no unsafe `v-html`, no committed secrets.
- Audit-log immutability and secret exclusion verified.

**Deferred / open:** a multi-role role-switcher UI (B7) and phone-as-secondary-identifier polish
(B9 — partly landed, see §1.1). See `AUDIT.md` “Tracked backlog”.

---

*Sources verified: `app/Providers/AppServiceProvider.php`, `app/Providers/FortifyServiceProvider.php`,
`config/fortify.php`, `app/Actions/Admin/CreateUserAction.php`, `app/Mail/UserInvitationMail.php`,
`app/Enums/AuditAction.php`, `app/Models/{AuditLog,SchoolReceipt}.php`,
`app/Models/Concerns/RecordsAudit.php`, the three `*ViewController`s, `app/Http/Middleware/EnsureUserHasRole.php`,
`app/Http/Controllers/Receipts/VerifyReceiptController.php`, `routes/web.php`, and `AUDIT.md`.*
