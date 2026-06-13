# Performance Audit — Student Management System

Date: 2026-06-11 · Auditor: performance review (read-only) · Branch: `master` @ `e044f81`

Scope: hot paths ranked by production traffic — login (audit insert per attempt), Inertia shared props (every request), applicant dashboard, application submit, SAO queue, admin dashboard, audit-log modal. Stack: Laravel 13 / PHP 8.4 / MySQL / Inertia v3 + Vue 3 / Vite.

Baseline scale assumptions used throughout: **~10k applications, ~15k application_documents, ~5k users, ~1M audit_logs rows after 2–3 academic years** (audit_logs grows on every significant write *and* every login/logout/failed-login), reference tables < 200 rows each.

Severity counts: Critical 0 · High 3 · Medium 4 · Low 4 · Info 2

---

## [PERF-1] Add composite indexes to audit_logs for the filtered/sorted modal query and tame the per-page COUNT(*)

- Severity: High / Category: Performance — index coverage / Location: `database/migrations/2026_05_04_120000_create_audit_logs_table.php:11-22`, `app/Http/Controllers/Admin/AuditLogController.php:21-46`
- Scale assumption: 1M+ `audit_logs` rows (fastest-growing table: one row per write + per login/logout/failed-login).

**Problem** — The audit-log endpoint builds this query (AuditLogController.php:21-46):

```
WHERE user_id = ? AND action IN (...) AND subject_type IN (...) AND occurred_at BETWEEN ? AND ?
ORDER BY occurred_at {asc|desc}, id {asc|desc}
LIMIT n OFFSET m  -- plus a COUNT(*) over the same WHERE from paginate()
```

The migration only defines **single-column** indexes: `occurred_at` (line 18), `user_id` (line 20), `action` (line 21), plus the `nullableMorphs('subject')` composite `(subject_type, subject_id)` (line 16). MySQL can pick only one of them per query:

- Filter by `action` (or `user_id`) + sort by `occurred_at` → index intersection or single-index scan **plus a filesort** of every matched row. A common filter like `action IN ('logged_in')` matches hundreds of thousands of rows at 1M scale; the filesort spills to disk.
- The unfiltered default (just `ORDER BY occurred_at DESC, id DESC`) can use the `occurred_at` index, but the tiebreaker `id` is not part of it, so MySQL still filesorts when `occurred_at` values collide and cannot do a pure index-ordered scan.
- `paginate($rows)` (line 45) issues `COUNT(*)` over the filtered set **on every page change / filter keystroke** in the modal — a full secondary-index scan of matched rows at 1M scale (hundreds of ms each).

**Proposed solution** — quick-win (index DDL only; project convention is editing migrations in place + `migrate:fresh`):

```php
// replace the three single-column indexes with:
$table->index(['occurred_at', 'id']);                 // default sort, both directions
$table->index(['user_id', 'occurred_at']);            // user filter + date sort
$table->index(['action', 'occurred_at']);             // action filter + date sort
$table->index(['subject_type', 'occurred_at']);       // subject filter + date sort
// keep nullableMorphs' (subject_type, subject_id) for subject() lookups
```

Structural (second step, only needed once row counts are demonstrably large): replace `paginate()` with `simplePaginate()` for the modal (it only needs prev/next + current page; `meta.last_page`/`total` at AuditLogController.php:67-69 would be dropped or replaced with a "has more" flag), eliminating the COUNT entirely.

**Acceptance criteria**
- `EXPLAIN` on the combined filter query (user_id + actions + date range + ORDER BY occurred_at,id) shows an index range scan with `Using filesort` absent for the single-filter cases.
- Default unfiltered first page does no filesort (uses `(occurred_at, id)`).
- Audit-log feature tests still pass (`php artisan test --compact --filter=AuditLog`).
- If simplePaginate adopted: frontend pager works without `last_page`.

---

## [PERF-2] Replace lock-all-rows-for-the-year matricule generation with a sequence row

- Severity: High / Category: Performance — lock contention / Location: `app/Actions/Sao/DecideApplicationAction.php:87-96`, `app/Models/StudentProfile.php:68-75`
- Scale assumption: 2k–5k admits per academic year; multiple SAOs deciding concurrently during the admission window.

**Problem** — `promoteToStudent()` runs, inside the decision transaction:

```php
StudentProfile::withTrashed()
    ->where('matricule', 'like', "stm-{$year}-%")
    ->lockForUpdate()
    ->get(['id']);                      // DecideApplicationAction.php:91-94

$matricule = StudentProfile::nextMatriculeForYear($year);   // line 96
// → SELECT COUNT(*) ... WHERE matricule LIKE 'stm-2026-%'  (StudentProfile.php:70-73)
```

Three problems compound as the year fills up:

1. **O(n) lock set per admit** — every admit `SELECT ... FOR UPDATE`s and hydrates *every* `student_profiles` row of the current year (5k rows late in the season), just to act as a mutex. The hydrated collection is discarded.
2. **Full serialization + gap locks** — all concurrent admits queue behind each other for the duration of the whole transaction (which also includes the application save, profile insert, role pivot write, and two audit inserts). On the `matricule` unique index, the range lock also gap-locks, so an unrelated insert into the same year range blocks too. Two SAOs deciding simultaneously means one waits for the other's entire transaction; lock-wait timeouts become possible under burst load.
3. **Two scans per admit** — the lock query and the `COUNT(*)` both range-scan the same `stm-{year}-%` prefix.

**Proposed solution** — structural: a one-row-per-year sequence table acts as the mutex *and* the counter, shrinking the locked footprint to a single row:

```php
// migration: matricule_sequences (year PK, next_number unsigned int)
$row = DB::table('matricule_sequences')->where('year', $year)->lockForUpdate()->first();
if ($row === null) {
    DB::table('matricule_sequences')->insert(['year' => $year, 'next_number' => 2]);
    $number = 1;
} else {
    DB::table('matricule_sequences')->where('year', $year)->update(['next_number' => $row->next_number + 1]);
    $number = $row->next_number;
}
$matricule = sprintf('stm-%d-%04d', $year, $number);
```

The existing `matricule` unique constraint (create_student_profiles_table.php:15) remains the safety net. Intermediate quick-win if the sequence table is deferred: lock a single aggregate instead of all rows — `->lockForUpdate()->max('matricule')` (or `count()` with `FOR UPDATE` via `selectRaw`) — still serializes admits but stops hydrating thousands of models.

**Acceptance criteria**
- Concurrent-admit test (two transactions admitting in parallel) produces distinct sequential matricules with no deadlock/duplicate.
- No query in the admit path selects more than one row from `student_profiles` for locking purposes.
- Existing `DecideApplication` feature tests pass unchanged in observable behavior (matricule format `stm-YYYY-NNNN` preserved).

---

## [PERF-3] Move uploaded-file storage out of the application-submit DB transaction

- Severity: High / Category: Performance — transaction scope / Location: `app/Http/Controllers/Applications/ApplicationController.php:146-194`
- Scale assumption: admission-window bursts (dozens of concurrent submissions), 2–5 uploaded documents of up to several MB each per submission.

**Problem** — `store()` wraps the entire submission in `DB::transaction(...)` (line 146), and **inside** it calls `$file->store('applications')` per uploaded document (line 168). Each store is filesystem I/O (multi-MB copy; seconds if `FILESYSTEM_DISK` ever moves to S3). While the files copy, the transaction holds:

- the freshly inserted `applications` row + its `RecordsAudit` audit insert (Application uses `RecordsAudit`, so `Application::create` at line 147 fires an extra `audit_logs` INSERT inside the same transaction),
- one `application_documents` insert + audit insert per file,
- potentially the `role_user` pivot write + another audit insert (lines 183-190),
- and, critically, a DB connection from the pool for the full upload duration.

Under a burst of concurrent submissions this exhausts connections and lengthens lock lifetimes on `audit_logs`/`role_user` auto-inc internals — per-request latency for *everyone* rises with upload size. A failed file write mid-loop also rolls back the DB but leaves earlier stored files orphaned on disk (rollback does not delete files).

**Proposed solution** — structural: store all files **before** opening the transaction, keep only DB writes inside, and delete stored files in a `catch`:

```php
$stored = [];   // code => [path, original_filename, mime, size]
try {
    foreach ($request->requiredDocumentCodes() as $code) { /* $stored[$code] = ... store() ... */ }
    $application = DB::transaction(function () use (...) { /* inserts only, reading $stored */ });
} catch (Throwable $e) {
    foreach ($stored as $doc) { Storage::delete($doc['path']); }
    throw $e;
}
```

**Acceptance criteria**
- No `Storage`/`UploadedFile::store` call executes inside `DB::transaction` in `ApplicationController::store`.
- A simulated DB failure after file storage leaves no orphaned files in `storage/app/applications` (feature test with a forced exception).
- Existing submit feature tests pass; submitted application + documents rows unchanged in shape.

---

## [PERF-4] Stop re-querying roles multiple times per authenticated request (Inertia share + middleware + gates)

- Severity: Medium / Category: Performance — per-request query amplification / Location: `app/Http/Middleware/HandleInertiaRequests.php:45`, `app/Models/Concerns/HasRoles.php:16-33`, `app/Http/Middleware/EnsureUserHasRole.php:25`, `app/Providers/AppServiceProvider.php:84`
- Scale assumption: every authenticated Inertia request, all roles (this is the per-request constant factor, not table growth).

**Problem** — Roles are fetched from the DB several times per request, each time as a separate query, because `HasRoles` never uses the loaded relation:

1. `HandleInertiaRequests::share` (line 45): `$user?->roles->pluck('name')` lazy-loads the `roles` relation — query #1 on **every** Inertia request, including every partial reload/navigation.
2. `EnsureUserHasRole` (line 25) calls `hasAnyRole()`, which is `$this->roles()->whereIn(...)->exists()` (HasRoles.php:32) — a fresh `EXISTS` query (#2) on every role-guarded route, ignoring the relation share() just loaded (middleware order actually runs this before share, but either way: two queries).
3. Every `Gate::allows(...)` defined in AppServiceProvider.php:84 calls `hasAnyRole()` again — one more query per gate check (e.g. `DocumentDownloadController.php:24`). `RoleDashboardResolver::pathFor` (app/Services/RoleDashboardResolver.php:27-31) is the worst offender: it calls `hasRole()` in a loop — **up to 6 EXISTS queries** on every login redirect for a plain applicant.

Roles per user are tiny (1–2 rows); the cost is round-trips, not data volume — 3–8 extra queries on every authenticated request.

**Proposed solution** — quick-win: make the trait check the in-memory relation when loaded, and load it once:

```php
// HasRoles.php
public function hasAnyRole(array $roles): bool
{
    if ($roles === []) { return false; }
    $values = array_map(fn (RoleName $r): string => $r->value, $roles);

    return $this->roles                                   // loads once, memoized by Eloquent
        ->contains(fn (Role $role): bool => in_array($role->name->value, $values, true));
}
```

(`hasRole()` delegates to `hasAnyRole([$role])`.) In `HandleInertiaRequests::share`, use `$user?->loadMissing('roles')` first. Net effect: exactly **one** roles query per request, shared by share(), middleware, gates, and the dashboard resolver. Note `Role::name` is a `RoleName` cast — compare via `->value`.

**Acceptance criteria**
- A request to a role-guarded page executes exactly one query against `roles`/`role_user` (assert with `DB::enableQueryLog()` or `expectsDatabaseQueryCount` in a feature test).
- Login redirect for each role still lands on the correct dashboard (RoleDashboardResolver tests).
- `hasRole`/`hasAnyRole` behavior unchanged for users with zero roles.

---

## [PERF-5] Collapse the 3-query login identifier resolution into one query

- Severity: Medium / Category: Performance — hot-path queries / Location: `app/Providers/FortifyServiceProvider.php:58-73`
- Scale assumption: login is the single most frequent write-bearing action (every attempt also INSERTs an `audit_logs` row via `AppServiceProvider.php:93-122`); 5/min/IP rate limit bounds abuse but not aggregate volume.

**Problem** — `Fortify::authenticateUsing` resolves the identifier with up to three sequential queries:

```php
$user = User::query()->where('email', $identifier)->first();          // query 1
$user ??= User::query()->where('employee_id', $identifier)->first();  // query 2
$user ??= User::query()->whereHas('studentProfile',
    fn ($q) => $q->where('matricule', $identifier))->first();         // query 3
```

Every **failed** login (typo'd identifier, bot probing) pays all three queries plus the `Failed`-event audit INSERT — the most expensive path is the one attackers exercise. All three columns are uniquely indexed (`users.email`, `users.employee_id` in `0001_01_01_000000_create_users_table.php:17-18`; `student_profiles.matricule` in `2026_05_05_120000_create_student_profiles_table.php:15`), so each individual query is cheap, but it is 3 round-trips where 1 suffices.

**Proposed solution** — quick-win: single query with OR-ed unique-index lookups (each branch is an index seek; MySQL handles this well as a union of point lookups):

```php
$user = User::query()
    ->where(fn ($q) => $q
        ->where('email', $identifier)
        ->orWhere('employee_id', $identifier)
        ->orWhereHas('studentProfile', fn ($p) => $p->where('matricule', $identifier)))
    ->first();
```

Caveat to verify in tests: if the same string could ever match two different users on different columns, the original code had an explicit precedence (email > employee_id > matricule). If that precedence matters, keep the 3-step form but short-circuit cheaper: 3 queries is acceptable; in that case downgrade this to Low and only document the decision.

**Acceptance criteria**
- Successful login by email, by employee_id, and by matricule each works (existing Fortify feature tests).
- Failed login executes at most one `users` lookup query.
- Precedence behavior (if a fixture user is crafted with colliding identifiers) is explicitly tested or documented.

---

## [PERF-6] Add a composite (status, submitted_at) index for the SAO application queue and index the other sort columns

- Severity: Medium / Category: Performance — index coverage / Location: `database/migrations/2026_05_06_120000_create_applications_table.php:31-32`, `app/Http/Controllers/Sao/ApplicationReviewController.php:79-84`
- Scale assumption: 10k–50k `applications` rows after a few admission cycles.

**Problem** — The SAO queue query is:

```php
Application::query()
    ->whereIn('status', $statuses)          // default: 3 statuses
    ->orderBy($sortField, $sortOrder)       // submitted_at | created_at | level
    ->paginate($rows)                        // + COUNT(*) over the WHERE
```

The migration indexes `status` and `submitted_at` **separately** (lines 31-32). MySQL uses the `status` index for the `IN` filter, then **filesorts** the matched rows for the ORDER BY (an index can't serve both a multi-value range and an order from a different index). Two of the three whitelisted sort fields (`SORT_FIELDS` at ApplicationReviewController.php:43 — `created_at`, `level`) have **no index at all**, so sorting Decided-status archives by those columns scans + filesorts the full matched set. The same `status` index serves the dashboard `GROUP BY status` counts (lines 47-50) adequately.

**Proposed solution** — quick-win (edit migration in place per project convention):

```php
$table->index(['status', 'submitted_at']);   // queue default: filter + sort in one index
$table->index('user_id');                     // already created by constrained() — keep
// keep single 'submitted_at' if other queries sort without status; otherwise drop it
```

Note: with `IN (3 statuses)`, MySQL can still filesort unless it uses index_merge/skip-scan; the practical win is largest for single-status filters and the leading-column count queries. For `created_at`/`level` sorts, either add `['status', 'created_at']` or accept filesort and cap `rows` (already capped at 100, line 70) — at 50k rows a filesort of one status partition is tolerable; document the choice.

**Acceptance criteria**
- `EXPLAIN` for the default queue (3-status IN + submitted_at DESC) uses an index on `status` with no full-table scan.
- `EXPLAIN` for single-status + submitted_at sort shows no `Using filesort`.
- SAO index feature tests pass after `migrate:fresh --seed`.

---

## [PERF-7] Cut the main JS bundle: lazy-register heavy PrimeVue components instead of shipping them on every page

- Severity: Medium / Category: Performance — frontend bundle / Location: `resources/js/app.ts:3-13,57-65`, `vite.config.ts:35-37`
- Scale assumption: every visitor, every first paint — including the unauthenticated login/welcome pages on slow connections.

**Problem** — `app.ts` statically imports and globally registers 8 PrimeVue components (lines 3-13, 57-64): `Button`, `Column`, `DataTable`, `Dialog`, `FileUpload`, `InputText`, `Select`, `Toast`. Static imports in the entry module land in the **main chunk**, which loads on *every* page — the login page downloads and parses `DataTable` (the heaviest PrimeVue component) and `FileUpload` even though it renders neither. The bundle was already ~913KB at Phase 2 and `chunkSizeWarningLimit` was raised to 1000 (vite.config.ts:36) to silence the symptom rather than split the chunk. Inertia v3's vite plugin already code-splits *pages*; the global registrations defeat that for component code.

**Proposed solution** — structural:
1. Keep only truly app-wide pieces global: `Toast` + `ToastService`, `Tooltip` directive, optionally `Button`/`InputText` (small, used everywhere).
2. Convert `DataTable`, `Column`, `Dialog`, `FileUpload`, `Select` to **per-page imports** (`import DataTable from 'primevue/datatable'` in the pages that use them — the project already does this pattern for `Card` per CLAUDE.md). Each ends up in the page chunks that need it, deduplicated by Vite into a shared async chunk.
3. After the migration, lower `chunkSizeWarningLimit` back toward the 500 default so regressions warn again.

**Acceptance criteria**
- `npm run build` output: main entry chunk shrinks measurably (target: DataTable/FileUpload code no longer in the entry chunk — verify with `npx vite-bundle-visualizer` or build output sizes).
- Login page network tab loads no DataTable/FileUpload code.
- All pages using tables/dialogs/uploads still render (smoke-test SAO index, audit modal, application create).
- `chunkSizeWarningLimit` override removed or justified by a comment.

---

## [PERF-8] Stop rebuilding and resending static filter `options` on every audit-log page fetch

- Severity: Low / Category: Performance — payload & per-row work / Location: `app/Http/Controllers/Admin/AuditLogController.php:71-86,100`
- Scale assumption: every pagination/filter interaction in the audit modal.

**Problem** — Every JSON page response includes the full `options.actions` + `options.subject_types` arrays (lines 71-86), which never change between requests — wasted payload and serialization on each of the user's pagination clicks. Additionally `shortSubjectName()` calls `array_flip(AuditLogIndexRequest::SUBJECT_TYPES)` (line 100) once **per row** via the `transform` at line 48-51 — up to 100 flips per request. Both are micro-costs but free to fix.

**Proposed solution** — quick-win: send `options` only when requested (`$request->boolean('with_options')` on the modal's first fetch) or move them into the Inertia page props of the parent page; hoist the `array_flip` into a static cached property:

```php
private static ?array $fqcnToShort = null;
// $map = self::$fqcnToShort ??= array_flip(AuditLogIndexRequest::SUBJECT_TYPES);
```

**Acceptance criteria**
- Page-2+ fetches return no `options` key (or frontend reads options from initial props).
- `array_flip` executes at most once per request.
- Audit modal filters still populate; feature tests updated accordingly.

---

## [PERF-9] Bound the applicant dashboard application list

- Severity: Low / Category: Performance — unbounded query / Location: `app/Http/Controllers/Applications/ApplicationController.php:31-52`
- Scale assumption: per-user row counts stay small (a person submits a handful of applications over the years), so this is defensive, not urgent.

**Problem** — `dashboard()` runs an unbounded `->get()` of all the user's applications (lines 31-36). `applications.user_id` is FK-indexed (constrained() at create_applications_table.php:14), so the query is cheap, but there is no cap: a pathological or scripted account that creates hundreds of draft/submitted applications gets them all hydrated, mapped, and serialized into every dashboard render. The eager load `programOffering.department` (line 33) is correctly N+1-free.

**Proposed solution** — quick-win: add `->limit(50)` (or paginate when the product grows a "history" view). Pair with a per-user open-application cap at submit time if business rules allow.

**Acceptance criteria**
- Dashboard query carries a LIMIT.
- Dashboard feature test passes; a seeded user with > limit applications renders without error.

---

## [PERF-10] Deduplicate statusLabel/statusSeverity helpers shipped in four page chunks

- Severity: Low / Category: Performance — bundle duplication & maintenance / Location: `resources/js/pages/sao/applications/Review.vue:131-141`, `resources/js/pages/sao/applications/Index.vue:99-109`, `resources/js/pages/applicant/applications/Show.vue:89-99`, `resources/js/pages/dashboards/Applicant.vue:67-77`
- Scale assumption: constant — affects bundle size and consistency, not runtime scaling.

**Problem** — The same `statusLabel(status)` / `statusSeverity(status)` mapping functions are re-implemented in four separate page components. Since Inertia code-splits per page, each copy ships in its own chunk (small bytes), but more importantly the four copies can drift when a new `ApplicationStatus` case is added (label/severity mismatch across SAO vs applicant views).

**Proposed solution** — quick-win: extract to `resources/js/lib/applicationStatus.ts` exporting `statusLabel`, `statusSeverity` (typed against the status union), import in the four pages. Vite will hoist it into a shared chunk automatically.

**Acceptance criteria**
- One source of truth module; grep shows zero local `function statusLabel` definitions in pages.
- `npm run build` succeeds; the four pages render identical badges as before.

---

## [PERF-11] (Info) audit_logs has no retention/pruning path — and the model actively blocks one

- Severity: Info / Category: Performance — data growth / Location: `app/Models/AuditLog.php:32-41`, `database/migrations/2026_05_04_120000_create_audit_logs_table.php`
- Scale assumption: ~1M rows after 2–3 years at moderate usage (every write + every auth event); unbounded thereafter.

**Problem** — There is no pruning/archival strategy for the fastest-growing table, and the immutability guards (`static::deleting` throws, AuditLog.php:38-40) mean even a future `MassPrunable` or Eloquent-based cleanup job would throw. Failed-login rows are appendable by unauthenticated traffic (rate-limited to 5/min/IP at FortifyServiceProvider.php:116-120, but aggregate growth is unbounded). Index bloat from PERF-1 compounds this.

**Proposed solution** — structural, policy decision first: define a retention window (e.g. keep auth events 12 months, entity audit forever, or archive to cold storage). Implement as a scheduled command using the **query builder** (`DB::table('audit_logs')->where(...)->delete()` bypasses the Eloquent guard intentionally — comment why), chunked by `id`. No caching involved; the invalidation question doesn't arise.

**Acceptance criteria**
- Documented retention policy.
- Scheduled prune/archive command exists with a feature test proving old auth rows are removed and entity rows respected.
- Immutability guard still blocks ad-hoc Eloquent deletes.

---

## [PERF-12] (Info) Reference-data queries on the application form are cache candidates with a clear invalidation trigger

- Severity: Info / Category: Performance — read-heavy reference data / Location: `app/Http/Controllers/Applications/ApplicationController.php:72-78` (create), `:215-242` (offerings), `:264-280` (levelRequirements)
- Scale assumption: reference tables stay < 200 rows; queries are already index-backed and cheap — this only matters if the form endpoints become very hot (thousands of applicants hitting cascading dropdowns during the admission window) given `CACHE_STORE=database` makes each cache hit itself a DB query today.

**Problem** — `create()` queries `departments` + `document_types` on every form render; `offerings`/`levelRequirements` JSON endpoints re-query per dropdown change. Each is a small indexed read, so the win is modest — and with the current database cache store, `Cache::remember` would replace one cheap SELECT with another. This is only worth doing **together with** a Redis/file cache store.

**Proposed solution** — structural (optional): `Cache::remember('ref:departments', ...)`, `ref:offerings:{degree}:{dept}`, `ref:level-reqs:{offering}:{level}`. **Invalidation trigger exists and is precise**: the only writers are the four admin reference controllers (`app/Http/Controllers/Admin/References/DepartmentController.php`, `DocumentTypeController.php`, `ProgramOfferingController.php`, `LevelCredentialRequirementController.php`) — flush the relevant keys (or a `ref:` tag) in their store/update/destroy actions. Do not cache until the store is non-database; otherwise skip.

**Acceptance criteria**
- If implemented: cache hits serve the three endpoints; an admin edit to a department/offering/document-type/level-requirement is visible on the applicant form immediately after save (feature test: write via admin controller, assert fresh read).
- If skipped: decision recorded here; no `Cache::remember` without the invalidation hooks.

---

## Verified non-findings (checked, no action needed)

- **Eager loading is consistently correct**: every list/show path uses constrained `with()`/`load()` selects — `DashboardController.php:85-89`, `ApplicationReviewController.php:80,125-130`, `ApplicationController.php:33,89-92`, `AuditLogController.php:22`. No N+1 found in any transform/map (relations accessed in loops are all preloaded).
- **Invitation mail is queued and outside the transaction**: `UserInvitationMail` implements `ShouldQueue` (`app/Mail/UserInvitationMail.php:14`) and `CreateUserAction::sendInvitation` runs after `DB::transaction` returns (`app/Actions/Admin/CreateUserAction.php:69,78`) with `QUEUE_CONNECTION=database` and a worker in `composer run dev` (`queue:listen --tries=1`). Production deploy must run a worker — operational note, not a code defect.
- **`ApplicationDecided` event is dispatched `afterCommit`** (`DecideApplicationAction.php:79-81`) and currently has no listeners — no hidden sync work. The `$application->fresh()` inside it is one extra SELECT post-commit; fine.
- **Admin dashboard counts** (`DashboardController.php:27-29,64-67`) are full-index scans (`COUNT(*)`, `GROUP BY status` on indexed `status`) — acceptable at 10k–100k rows; revisit (cache with write-through invalidation or approximate counts) only if the dashboard slows.
- **SAO show prior-history lookups** (`ApplicationReviewController.php:132-162`) filter on `user_id`, which is FK-indexed on both `student_profiles` (unique) and `applications` (constrained) — bounded per-user row counts.
- **`nullableMorphs('subject')`** already provides the `(subject_type, subject_id)` composite for `subject()` morph lookups.
- **Session driver `database`** with indexed `sessions` table (users migration:32-39) — adequate at this scale; move sessions+cache+queue to Redis together if/when load demands (no separate finding).
