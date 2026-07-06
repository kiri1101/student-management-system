# Remove `ApplicationStatus::Draft` Dead State — Implementation Plan (#82)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Delete the unreachable `Draft` application status and every reference to it, so the state machine reflects reality — applications are born `Submitted` — with the full suite staying green.

**Architecture:** A behavior-preserving deletion. `ApplicationController::store()` already creates applications as `Submitted`; nothing persists a `Draft`. Remove the enum case, its `OPEN_STATUSES` membership, the unreachable `Draft → Submitted` transition branch, the migration default, the SAO prior-applications filter, the factory default, the frontend status-map entry, and the two tests that assert an (impossible) draft is refused. Record the decision in ADR-0024.

**Tech Stack:** Laravel 13 / PHP 8.4, Inertia + Vue 3, Pest v4, MySQL local / SQLite tests.

## Global Constraints

- **Behavior-preserving.** No change to how applications are submitted, triaged, or decided. The suite (minus the two now-impossible draft cases) must stay green — that is the safety net for this refactor; classic RED→GREEN TDD does not apply to a deletion.
- **Do not touch** the `draft` values of `CoursePlanStatus` / `ResultStatus` (different enums, still live), nor the course-plan/result entries in `statusDisplay.ts`.
- Edit the migration in place (local-only project) and re-run `php artisan migrate:fresh --seed --no-interaction`.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes. Tests: full runs `--testsuite=Unit,Feature` only. Frontend gate: `npm run build && npm run types:check && npm run lint:check` (no chunk-size warning).
- Commit per task on branch `chore/remove-draft-state`, message ending with:
  `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`

---

### Task 1: Remove the Draft state (backend + frontend + tests)

**Files:**
- Modify: `app/Enums/ApplicationStatus.php`
- Modify: `app/Models/Application.php`
- Modify: `database/migrations/2026_05_06_120000_create_applications_table.php`
- Modify: `app/Http/Controllers/Sao/ApplicationReviewController.php`
- Modify: `database/factories/ApplicationFactory.php`
- Modify: `resources/js/lib/statusDisplay.ts`
- Modify (remove one case each): `tests/Feature/Sao/DecideApplicationTest.php`, `tests/Feature/Sao/TriageApplicationTest.php`

**Interfaces:**
- Produces: `ApplicationStatus` with 7 cases (no `Draft`); `Application::OPEN_STATUSES === [...INTERIM_STATUSES]`; `Application::canTransitionTo(ApplicationStatus $next): bool` unchanged in signature (interim source → allowed).

- [ ] **Step 1: Remove the enum case**

`app/Enums/ApplicationStatus.php` — delete the `Draft` line:

```php
enum ApplicationStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case DocumentsRequested = 'documents_requested';
    case Admitted = 'admitted';
    case Rejected = 'rejected';
    case Waitlisted = 'waitlisted';
    case Withdrawn = 'withdrawn';
}
```

- [ ] **Step 2: Update the `Application` model**

`app/Models/Application.php`.

Class PHPDoc (line ~17) — drop `Draft` from the lifecycle:

```php
/**
 * An admission application moving through the SAO triage lifecycle
 * (Submitted → interim → terminal; see {@see ApplicationStatus}).
 *
```

`OPEN_STATUSES` — remove the `Draft` member and update its PHPDoc:

```php
    /**
     * Statuses that count as "in progress" for the one-open-application-per-
     * applicant rule (the interim trio).
     *
     * @var list<ApplicationStatus>
     */
    public const OPEN_STATUSES = [
        ...self::INTERIM_STATUSES,
    ];
```

`canTransitionTo()` — remove the Draft branch; the interim path returns `true`:

```php
    /**
     * Transition matrix: an interim application may move to any other interim
     * or terminal status; a terminal application may not transition. Valid
     * targets are further constrained by the SAO Form Requests
     * (TriageApplicationRequest / DecideApplicationRequest).
     */
    public function canTransitionTo(ApplicationStatus $next): bool
    {
        if (! in_array($this->status, self::INTERIM_STATUSES, strict: true)) {
            return false;
        }

        return true;
    }
```

- [ ] **Step 3: Change the migration column default**

`database/migrations/2026_05_06_120000_create_applications_table.php` — line ~23:

```php
            $table->string('status')->default(ApplicationStatus::Submitted->value);
```

- [ ] **Step 4: Remove the SAO prior-applications Draft filter**

`app/Http/Controllers/Sao/ApplicationReviewController.php` — in the `priorApplications` query, delete the `->whereNotIn('status', [ApplicationStatus::Draft])` line so it reads:

```php
        $priorApplications = Application::withTrashed()
            ->where('user_id', $application->user_id)
            ->where('id', '!=', $application->id)
            ->orderByDesc('id')
            ->get()
```

- [ ] **Step 5: Flip the factory default to Submitted**

`database/factories/ApplicationFactory.php` — in `definition()`, change the `status`/`submitted_at` lines:

```php
            'status' => ApplicationStatus::Submitted->value,
            'submitted_at' => now(),
```

Leave the `submitted()` state method unchanged.

- [ ] **Step 6: Remove the Draft entry from the application status map**

`resources/js/lib/statusDisplay.ts` — in the `APPLICATION_STATUS` map only, delete the `draft` line so it starts:

```ts
const APPLICATION_STATUS: Record<
    string,
    { label: string; severity: TagSeverity }
> = {
    submitted: { label: 'Submitted', severity: 'info' },
    under_review: { label: 'Under review', severity: 'warn' },
```

Do **not** touch the `draft` entries in the two lower maps (course-plan, result).

- [ ] **Step 7: Remove the two now-impossible draft tests**

Delete the entire `it('refuses to decide a draft application', …)` block from
`tests/Feature/Sao/DecideApplicationTest.php`:

```php
it('refuses to decide a draft application', function () {
    $application = Application::factory()->create();
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->post(route('sao.applications.decide', $application), [
        'status' => 'admitted',
    ]);

    $response->assertSessionHasErrors('status');
    expect($application->fresh()->status)->toBe(ApplicationStatus::Draft)
        ->and(StudentProfile::count())->toBe(0);
});
```

Delete the entire `it('refuses to triage a draft application', …)` block from
`tests/Feature/Sao/TriageApplicationTest.php`:

```php
it('refuses to triage a draft application', function () {
    $application = Application::factory()->create();
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->post(route('sao.applications.triage', $application), [
        'status' => 'under_review',
    ]);

    $response->assertSessionHasErrors('status');
    expect($application->fresh()->status)->toBe(ApplicationStatus::Draft);
});
```

(The non-interim-source guard stays covered by *"refuses to triage a terminal application"* and *"refuses a decision when the application was finalized concurrently."*)

- [ ] **Step 8: Rebuild the DB and run the full suite**

```bash
php artisan migrate:fresh --seed --no-interaction
php artisan test --compact --testsuite=Unit,Feature
```

Expected: clean migrate; suite green. **If any test fails because a bare `Application::factory()->create()` is now `Submitted` (with `submitted_at` set) instead of a null-`submitted_at` Draft**, fix that test by making its intent explicit — e.g. add `->state(['submitted_at' => null])` or set the status/`submitted_at` it actually needs. Do not re-introduce `Draft`. Re-run until green, and record each such fix in your report.

- [ ] **Step 9: Format and run the frontend gate**

```bash
vendor/bin/pint --dirty --format agent
npm run build && npm run types:check && npm run lint:check
```

Expected: all green, no chunk-size warning.

- [ ] **Step 10: Commit**

```bash
git add app/Enums/ApplicationStatus.php app/Models/Application.php database/migrations/2026_05_06_120000_create_applications_table.php app/Http/Controllers/Sao/ApplicationReviewController.php database/factories/ApplicationFactory.php resources/js/lib/statusDisplay.ts tests/Feature/Sao/DecideApplicationTest.php tests/Feature/Sao/TriageApplicationTest.php
git commit -m "refactor(admissions): remove the unreachable Draft application state (#82)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 2: ADR-0024, docs sync, PR

**Files:**
- Create: `docs/adr/0024-applications-born-submitted.md`
- Modify: `docs/adr/README.md` (index row)
- Modify: `docs/data-model.md` (`ApplicationStatus` enum drops `draft`)
- Modify: `docs/modules/admissions.md` (§3 status enums, `OPEN_STATUSES` wording, `canTransitionTo` matrix, §5.1 flow prose)
- Modify: `docs/index.md` (ADR count 23 → 24)
- Modify: `plan/context.md` (new § entry)

- [ ] **Step 1: Write ADR-0024** (via the write-adr skill): **context** — the schema + state machine supported `Draft` (§6.4 / Phase 7 design, AUD-010 added a `Draft → Submitted` transition), but `ApplicationController::store()` always created `Submitted` and nothing else persisted a draft, so `Draft` was dead code; **decision** — remove the `Draft` state; applications are **born `Submitted`**; **consequences** — simpler state machine, `OPEN_STATUSES` == the interim set, the AUD-010 `Draft → Submitted` transition is gone, the migration default is now `Submitted`; **rejected alternative** — build save-as-draft (would require nullable-everywhere `applications` columns + a draft-file storage/expiry lifecycle + block-vs-replace logic for the one-open guard, disproportionate for a one-time submit artifact — revisitable later). Status **Accepted**. Add the index row to `docs/adr/README.md`.

- [ ] **Step 2: Run the docs-refresh skill** scoped to this change. Verify against the shipped code:
  - `docs/data-model.md` — the `ApplicationStatus` enum reference line drops `draft` (keep the terminal/interim breakdown accurate).
  - `docs/modules/admissions.md` — §3 status-enum bullet drops `Draft (draft)`; the `OPEN_STATUSES` description ("Draft + the interim trio") becomes the interim trio; the `canTransitionTo` matrix note drops the `Draft → Submitted` row; scan §5 prose for any "Draft is the first status" claim and correct it.
  - `docs/index.md` — ADR count 23 → 24.

- [ ] **Step 3: Append the `plan/context.md` § entry** (next number after §28) recording: issue #82, branch, the removal + ADR-0024, gate result, docs touched, and the rejected save-as-draft alternative.

- [ ] **Step 4: Commit docs, push, open the PR**

```bash
git add -A
git commit -m "docs: ADR-0024 + admissions/data-model sync for the Draft removal (#82)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
git push -u origin chore/remove-draft-state
gh pr create --base main --title "refactor(admissions): remove the unreachable Draft application state (#82)" --body "Closes #82. <summary + gate results>

🤖 Generated with [Claude Code](https://claude.com/claude-code)"
```

Expected: PR opens; wait for all 4 CI checks (ci 8.4 / ci 8.5 / quality / browser) before merging (squash + delete branch), then fast-forward local `main`.

---

## Self-review notes (already applied)

- **Spec coverage:** every §3 footprint item maps to a Task 1 step (enum → S1, model → S2, migration → S3, SAO filter → S4, factory → S5, frontend map → S6, tests → S7) or Task 2 (ADR + docs). The full-suite-green gate (S8) is the behavior-preserving safety net.
- **`canTransitionTo` `$next`:** the signature is retained per the approved spec (minimal change, preserves the three call sites in `TriageApplicationAction` / `DecideApplicationAction` / `RestorePriorEnrollment`). The parameter is now unconstrained for interim sources — a reviewer may raise removing it as a follow-up simplification; that is the human's call, out of this minimal-deletion's scope.
- **Factory fallout is explicitly handled** (S8) rather than hand-waved: the default flips `Draft`→`Submitted`, and any test relying on the old bare-create behavior is fixed in place, not by re-introducing `Draft`.
- **No enum `label()` to update** — `ApplicationStatus` has only cases; the frontend map (S6) is the only label surface.
