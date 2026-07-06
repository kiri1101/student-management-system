# Testing

How SchuLyf is tested, how to run the suite, and the conventions a new test should follow. The suite
is the project’s safety net for the audit-driven hardening described in [security.md](security.md);
keep it green.

> Cross-references: [onboarding.md](onboarding.md) (the full quality gate around the test run),
> [architecture.md](architecture.md) (what each layer is), [security.md](security.md) (the
> behaviours many tests assert).

---

## 1. The shape of the suite

[Pest v4](https://pestphp.com) on top of PHPUnit 12. Three suites are declared in `phpunit.xml`:

| Suite | Directory | Purpose |
|---|---|---|
| Unit | `tests/Unit` | Isolated, no framework boot needed (currently just the scaffold `ExampleTest`) |
| Feature | `tests/Feature` | The bulk of coverage — HTTP-level, full framework boot, real DB |
| Browser | `tests/Browser` | Pest 4 real-browser smoke suite for JS-heavy surfaces |

Most tests are **feature tests** (`php artisan make:test SomeFeatureTest`); reach for a unit test
(`--unit`) only when there’s genuinely framework-free logic to isolate. The suite is large —
**699 tests / 2,614 assertions** in the default `php artisan test` run (datasets expand a single
`it()` into many cases, e.g. `tests/Feature/Admin/AdminAuthorizationTest.php` runs every non-admin
role against every admin endpoint). The `tests/Browser` smoke suite runs as a separate job (§2).

### Test environment (`phpunit.xml`)

Tests run against an **in-memory SQLite** database with cheap, deterministic settings:

| Env | Value | Why |
|---|---|---|
| `APP_ENV` | `testing` | enables the local/testing branches (no prod password rules, etc.) |
| `DB_CONNECTION` / `DB_DATABASE` | `sqlite` / `:memory:` | fast, isolated; `RefreshDatabase` migrates per run |
| `BCRYPT_ROUNDS` | `4` | fast hashing |
| `CACHE_STORE` | `array` | **pinned** — reference-data caching must not bleed between tests |
| `MAIL_MAILER` | `array` | mail captured, never sent (`Mail::fake()` style assertions) |
| `QUEUE_CONNECTION` | `sync` | queued jobs/mail run inline so they’re assertable |
| `SESSION_DRIVER` | `array` | no session files |

> **Note on SQLite vs MySQL.** Production is MySQL; tests are SQLite. A class of concurrency/locking
> behaviour (sequence races, `lockForUpdate`, gap locks) **cannot** be exercised under SQLite — this
> is called out in `AUDIT.md` (e.g. `AUD-006`). Treat lock-sensitive code with extra review; the
> tests prove logic, not MySQL concurrency.

---

## 2. Running tests

```bash
php artisan test --compact            # whole suite, compact output
php artisan test --compact --filter=DecideApplication   # one test / file by name
vendor/bin/pest --filter="signs a user in"              # by description, via Pest directly
php artisan test tests/Feature/Audit                    # a directory
```

Run the **minimum** needed to prove a change — a filename or `--filter` over the whole suite.

The **Browser** suite needs a real Chromium and is **not** part of the default `php artisan test`
fan-out in CI — it runs as a separate job (`AUD-029`). Run it explicitly:

```bash
php artisan test tests/Browser --compact
```

---

## 3. The `tests/Browser` smoke suite

A deliberately small Pest 4 browser suite (`tests/Browser/SmokeTest.php`) covering exactly the
client-side surfaces HTTP feature tests can’t reach — the same class of regression that slipped
through earlier (`AUD-015`/`AUD-016`). It is **not** deep interaction coverage; that stays in the
HTTP feature suite.

| Smoke test | Asserts |
|---|---|
| Login page | renders with `assertNoJavaScriptErrors`, signs a user in, `assertAuthenticated` |
| Applicant application form | step-1 wizard renders cleanly (`assertNoJavaScriptErrors` + `assertNoConsoleLogs`) |
| Admin audit-log modal | `/admin/dashboard` → click *Open audit log* → modal renders (`Actor` visible) |

These use Pest’s `visit()` / `assertSee()` / `click()` / `fill()` API and `assertNoJavaScriptErrors`.

> **Flakiness caution (audit-modal smoke).** The audit-log modal test opens a modal and then
> `assertSee`s its first rendered content. It depends on the modal’s data fetch completing within
> Pest’s default visibility wait — under machine load the fetch can lose the race and the assertion
> flakes, while it passes reliably **in isolation**. If it fails, re-run it alone
> (`php artisan test tests/Browser --filter="audit-log"`) before treating it as a real regression.

---

## 4. Conventions

### Bootstrapping (`tests/Pest.php`)

- `TestCase` + `RefreshDatabase` are applied to **Feature** and **Browser** suites.
- A `beforeEach` seeds `RolesSeeder` for the directories that need roles
  (`Feature/{Auth,Dashboards,Admin,Applications,Sao}` + `Browser`). **Directories outside that list
  must seed roles themselves** — e.g. `tests/Feature/Files/InlineFileViewTest.php` has its own
  `beforeEach(fn () => $this->seed(RolesSeeder::class))`. Follow that pattern for new top-level
  feature directories.
- Global helper `userWithRole(RoleName $role): User` creates a user and assigns the role — the
  standard way to act as a privileged user:

  ```php
  $this->actingAs(userWithRole(RoleName::Admin));
  ```

### Factories & seeders

- Always build models with **factories**, preferring custom states over manual attribute soup —
  e.g. `UserFactory::staff()` sets `employee_id` directly, `StudentProfile::factory()`, etc. Check
  the factory for an existing state before hand-rolling attributes.
- Reference data comes from seeders. `RolesSeeder` is the one most tests need; richer fixtures pull
  in the relevant domain seeders.

### Assertions & doubles

- `Mail::fake()` / `Notification::fake()` for outbound mail and notifications (queue is `sync`, so
  queued mail/listeners run inline and are assertable — e.g.
  `ApplicationDecisionNotificationTest`).
- Audit side effects are asserted by querying `AuditLog` for the expected `action` + `subject` (see
  `tests/Feature/Audit/RecordsAuditTest.php` — it asserts exact before/after diffs, secret
  stripping, and that timestamp-only changes write **no** row).
- Datasets drive exhaustive matrix coverage (gate × role, status transitions) from one test body —
  prefer them over copy-pasting cases.

### What must be tested

Per the project rules, **every change ships with a test** — a new test or an updated one, run and
green. Don’t add a verification script or tinker when a feature test can prove the behaviour. Don’t
delete tests without approval.

---

## 5. Where things live (orientation)

| Area | Representative tests |
|---|---|
| Auth & identifiers | `Auth/{AuthenticationTest,PhoneIdentifierTest,AuthThrottleTest,PasswordResetTest,TwoFactorChallengeTest}` |
| Authorization | `Admin/AdminAuthorizationTest`, `Sao/AuthorizationTest` |
| Audit log | `Audit/{RecordsAuditTest,AuthEventsTest,UserAuditTest,PruneAuditLogsTest}`, `Admin/AuditLogIndexTest` |
| Admissions | `Applications/*`, `Sao/*` |
| Payments & receipts | `Payments/{SchoolReceiptIssuanceTest,VerifyReceiptTest,PaymentStandingTest}`, `Student/ReportPaymentTest`, `Accountant/ReviewPaymentTest` |
| Exam gating / deferrals | `Standing/StandingCheckTest`, `Student/RequestDeferralTest`, `Accountant/ReviewDeferralTest` |
| Courses | `Courses/*` (attendance, assignments, results, disputes, sessions) |
| File viewers | `Files/InlineFileViewTest` |
| Admin user mgmt | `Admin/{CreateUserTest,ManageUsersTest,EmployeeIdTest,ImportStaffUsersTest}` |
| Throttling | `EndpointThrottleTest`, `Auth/AuthThrottleTest` |

---

*Sources verified: `phpunit.xml`, `tests/Pest.php`, `tests/Browser/SmokeTest.php`, and
representative files under `tests/Feature/` (`Admin/AdminAuthorizationTest`, `Audit/RecordsAuditTest`,
`Files/InlineFileViewTest`). The 699/2,614 figure is from a full `php artisan test` run at this
baseline.*
