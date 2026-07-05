# DocumentsRequested Response Flow (#80) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let applicants see exactly which submitted documents the SAO rejected, replace each one, and have the application automatically return to the SAO queue — closing the `DocumentsRequested` dead end (issue #80).

**Architecture:** Per-document review state (`pending`/`accepted`/`rejected`) on `application_documents`; two SAO endpoints (accept/reject per document) and one applicant endpoint (replace per rejected document) all funnel through action classes that share one auto-flip rule (`DocumentsRequested → Submitted` when no rejected documents remain). Triage into `DocumentsRequested` is guarded (≥1 rejected doc) and fires a queued email to the applicant. Spec: `plan/documents-requested/design.md` (approved 2026-07-03).

**Tech Stack:** Laravel 13 / PHP 8.4, Pest v4, Inertia v3 + Vue 3 + PrimeVue (Aura), Wayfinder, MySQL local (SQLite in tests).

## Global Constraints

- String columns + PHP enum casts — **never** `$table->enum(...)`.
- Local-only DB: **edit the original migration in place**, then `php artisan migrate:fresh --seed` (no alter migrations).
- After modifying any PHP file: `vendor/bin/pint --dirty --format agent`.
- Test runs: `php artisan test --compact --filter=<Name>` per task; full runs use `--testsuite=Unit,Feature` (the Browser suite hangs locally).
- PrimeVue components are imported **per page** (no global registration); icons via `lucide-vue-next` in `#icon` slots; before writing PrimeVue markup consult https://primevue.org/llms/llms-full.txt (standing rule).
- Wayfinder: after adding routes run `php artisan wayfinder:generate --no-interaction` so `@/routes/*` barrels exist for the frontend and `vue-tsc`.
- Commit per task on branch `feat/documents-requested-response`; end every commit message with `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`.
- Route URI family: document routes use the **plural** `applications/{application}/documents/{document}` prefix (matches existing download/view siblings; deliberate refinement of the spec's singular `application/...` — route *names* keep the `application.` prefix).

---

### Task 1: Review-state schema, enum, model, factory

**Files:**
- Modify: `database/migrations/2026_05_06_120001_create_application_documents_table.php`
- Create: `app/Enums/ApplicationDocumentStatus.php`
- Modify: `app/Models/ApplicationDocument.php`
- Modify: `database/factories/ApplicationDocumentFactory.php`
- Test: `tests/Feature/Applications/ApplicationDocumentReviewStateTest.php`

**Interfaces:**
- Consumes: existing `ApplicationDocument` model/factory.
- Produces: `ApplicationDocumentStatus` enum (`Pending='pending'`, `Accepted='accepted'`, `Rejected='rejected'`, `label(): string`); `ApplicationDocument` columns `status` (cast to the enum, default `pending`), `review_notes` (?string), `reviewed_by` (?int FK users), `reviewed_at` (?Carbon); relation `reviewedBy(): BelongsTo<User>`; factory states `accepted()` and `rejected(?string $notes = null)`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\ApplicationDocumentStatus;
use App\Models\ApplicationDocument;
use App\Models\User;

it('defaults a new document to pending with empty review metadata', function () {
    $document = ApplicationDocument::factory()->create();

    expect($document->status)->toBe(ApplicationDocumentStatus::Pending)
        ->and($document->review_notes)->toBeNull()
        ->and($document->reviewed_by)->toBeNull()
        ->and($document->reviewed_at)->toBeNull();
});

it('produces accepted and rejected documents through the factory states', function () {
    $accepted = ApplicationDocument::factory()->accepted()->create();
    $rejected = ApplicationDocument::factory()->rejected('Scan is blurry.')->create();

    expect($accepted->status)->toBe(ApplicationDocumentStatus::Accepted)
        ->and($accepted->reviewedBy)->toBeInstanceOf(User::class)
        ->and($accepted->reviewed_at)->not->toBeNull()
        ->and($rejected->status)->toBe(ApplicationDocumentStatus::Rejected)
        ->and($rejected->review_notes)->toBe('Scan is blurry.');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ApplicationDocumentReviewState`
Expected: FAIL — `status` attribute is null / unknown factory method `accepted`.

- [ ] **Step 3: Edit the migration in place**

In `database/migrations/2026_05_06_120001_create_application_documents_table.php`, after the `$table->timestamp('uploaded_at');` line add:

```php
            $table->string('status')->default('pending')->index();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
```

- [ ] **Step 4: Create the enum**

`app/Enums/ApplicationDocumentStatus.php`:

```php
<?php

namespace App\Enums;

enum ApplicationDocumentStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting review',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
        };
    }
}
```

- [ ] **Step 5: Update the model**

In `app/Models/ApplicationDocument.php`:
- extend the `#[Fillable]` list with `'status'`, `'review_notes'`, `'reviewed_by'`, `'reviewed_at'`;
- extend `casts()` with `'status' => ApplicationDocumentStatus::class` and `'reviewed_at' => 'datetime'` (import `App\Enums\ApplicationDocumentStatus`);
- add below `documentType()`:

```php
    /**
     * The staff user who last reviewed this document (null while pending).
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
```

- [ ] **Step 6: Add factory states**

In `database/factories/ApplicationDocumentFactory.php` (import `App\Enums\ApplicationDocumentStatus` and `App\Models\User`), add after `definition()`:

```php
    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'status' => ApplicationDocumentStatus::Accepted->value,
            'review_notes' => null,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(?string $notes = null): static
    {
        return $this->state(fn (): array => [
            'status' => ApplicationDocumentStatus::Rejected->value,
            'review_notes' => $notes ?? 'Document is illegible.',
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }
```

- [ ] **Step 7: Rebuild the local schema**

Run: `php artisan migrate:fresh --seed --no-interaction`
Expected: all migrations run, seeders complete.

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test --compact --filter=ApplicationDocumentReviewState`
Expected: PASS (2 tests).

- [ ] **Step 9: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat(admissions): per-document review state on application documents (#80)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 2: SAO accept/reject endpoints + auto-flip concern

**Files:**
- Modify: `app/Enums/AuditAction.php`
- Create: `app/Actions/Concerns/ResolvesDocumentsRequested.php`
- Create: `app/Actions/Sao/ReviewApplicationDocument.php`
- Create: `app/Http/Requests/Sao/RejectApplicationDocumentRequest.php`
- Modify: `app/Http/Controllers/Sao/ApplicationReviewController.php` (two new methods + document payload fields in `show()`)
- Modify: `routes/sao.php`
- Test: `tests/Feature/Sao/ReviewApplicationDocumentTest.php`

**Interfaces:**
- Consumes: Task 1's `ApplicationDocumentStatus`, factory states; existing `Application::isTerminal()`, `AuditLog::record()`.
- Produces: routes `sao.applications.documents.accept` / `sao.applications.documents.reject` (POST, scoped bindings); trait method `flipToSubmittedWhenResolved(Application $application, User $actor): void`; action `ReviewApplicationDocument::execute(ApplicationDocument $document, ApplicationDocumentStatus $decision, ?string $notes, User $reviewer): ApplicationDocument`; `AuditAction::DocumentAccepted|DocumentRejected|DocumentResubmitted`; SAO `show()` documents payload now carries `status` + `review_notes`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Sao/ReviewApplicationDocumentTest.php`:

```php
<?php

use App\Enums\ApplicationDocumentStatus;
use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\DocumentType;

/**
 * A document of the given type code attached to the application. Distinct
 * codes matter: (application_id, document_type_id) is unique.
 */
function reviewDocOfType(Application $application, string $code): ApplicationDocument
{
    $type = DocumentType::firstOrCreate(['code' => $code], ['name' => $code.' document']);

    return ApplicationDocument::factory()->create([
        'application_id' => $application->id,
        'document_type_id' => $type->id,
    ]);
}

it('accepts a document and records reviewer metadata and an audit row', function () {
    $application = Application::factory()->submitted()->create();
    $document = reviewDocOfType($application, 'NID');
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)
        ->post(route('sao.applications.documents.accept', [$application, $document]))
        ->assertRedirect();

    $fresh = $document->fresh();
    expect($fresh->status)->toBe(ApplicationDocumentStatus::Accepted)
        ->and($fresh->review_notes)->toBeNull()
        ->and($fresh->reviewed_by)->toBe($sao->id)
        ->and($fresh->reviewed_at)->not->toBeNull();

    AuditLog::query()
        ->where('subject_type', $document->getMorphClass())
        ->where('subject_id', $document->id)
        ->where('action', AuditAction::DocumentAccepted->value)
        ->sole();
});

it('rejects a document with a reason and an audit row', function () {
    $application = Application::factory()->submitted()->create();
    $document = reviewDocOfType($application, 'NID');
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)
        ->post(route('sao.applications.documents.reject', [$application, $document]), [
            'notes' => 'The scan is cropped — edges are missing.',
        ])
        ->assertRedirect();

    $fresh = $document->fresh();
    expect($fresh->status)->toBe(ApplicationDocumentStatus::Rejected)
        ->and($fresh->review_notes)->toBe('The scan is cropped — edges are missing.')
        ->and($fresh->reviewed_by)->toBe($sao->id);

    $log = AuditLog::query()
        ->where('subject_type', $document->getMorphClass())
        ->where('subject_id', $document->id)
        ->where('action', AuditAction::DocumentRejected->value)
        ->sole();
    expect($log->changes['notes'])->toBe('The scan is cropped — edges are missing.');
});

it('requires notes when rejecting', function () {
    $application = Application::factory()->submitted()->create();
    $document = reviewDocOfType($application, 'NID');
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)
        ->post(route('sao.applications.documents.reject', [$application, $document]))
        ->assertSessionHasErrors('notes');

    expect($document->fresh()->status)->toBe(ApplicationDocumentStatus::Pending);
});

it('forbids non-SAO roles from reviewing documents', function () {
    $application = Application::factory()->submitted()->create();
    $document = reviewDocOfType($application, 'NID');
    $lecturer = userWithRole(RoleName::Lecturer);

    $this->actingAs($lecturer)
        ->post(route('sao.applications.documents.accept', [$application, $document]))
        ->assertForbidden();
});

it('refuses review on a terminal application', function () {
    $application = Application::factory()->state([
        'status' => ApplicationStatus::Admitted,
        'submitted_at' => now()->subDay(),
        'decided_at' => now(),
    ])->create();
    $document = reviewDocOfType($application, 'NID');
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)
        ->post(route('sao.applications.documents.accept', [$application, $document]))
        ->assertSessionHasErrors('status');

    expect($document->fresh()->status)->toBe(ApplicationDocumentStatus::Pending);
});

it('scopes the document to its application', function () {
    $application = Application::factory()->submitted()->create();
    $foreign = reviewDocOfType(Application::factory()->submitted()->create(), 'NID');
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)
        ->post(route('sao.applications.documents.accept', [$application, $foreign]))
        ->assertNotFound();
});

it('flips a DocumentsRequested application to Submitted when accepting the last rejected document', function () {
    $application = Application::factory()->state([
        'status' => ApplicationStatus::DocumentsRequested,
        'submitted_at' => now()->subDay(),
    ])->create();
    $rejected = reviewDocOfType($application, 'NID');
    $rejected->update(['status' => ApplicationDocumentStatus::Rejected->value, 'review_notes' => 'Blurry.']);
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)
        ->post(route('sao.applications.documents.accept', [$application, $rejected]))
        ->assertRedirect();

    expect($application->fresh()->status)->toBe(ApplicationStatus::Submitted);

    $log = AuditLog::query()
        ->where('subject_type', $application->getMorphClass())
        ->where('subject_id', $application->id)
        ->where('action', AuditAction::StatusChanged->value)
        ->sole();
    expect($log->changes)->toBe(['before' => 'documents_requested', 'after' => 'submitted'])
        ->and($log->user_id)->toBe($sao->id);
});

it('does not flip while another rejected document remains', function () {
    $application = Application::factory()->state([
        'status' => ApplicationStatus::DocumentsRequested,
        'submitted_at' => now()->subDay(),
    ])->create();
    $first = reviewDocOfType($application, 'NID');
    $second = reviewDocOfType($application, 'BIRTH');
    $first->update(['status' => ApplicationDocumentStatus::Rejected->value]);
    $second->update(['status' => ApplicationDocumentStatus::Rejected->value]);
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)
        ->post(route('sao.applications.documents.accept', [$application, $first]))
        ->assertRedirect();

    expect($application->fresh()->status)->toBe(ApplicationStatus::DocumentsRequested);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=ReviewApplicationDocument`
Expected: FAIL — route `sao.applications.documents.accept` not defined.

- [ ] **Step 3: Add the audit actions**

In `app/Enums/AuditAction.php`, after `case ApplicationDecided = 'application_decided';` add:

```php
    case DocumentAccepted = 'document_accepted';
    case DocumentRejected = 'document_rejected';
    case DocumentResubmitted = 'document_resubmitted';
```

- [ ] **Step 4: Create the shared auto-flip concern**

`app/Actions/Concerns/ResolvesDocumentsRequested.php`:

```php
<?php

namespace App\Actions\Concerns;

use App\Enums\ApplicationDocumentStatus;
use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\User;

trait ResolvesDocumentsRequested
{
    /**
     * Flip a DocumentsRequested application back to Submitted once no rejected
     * document remains. Callers invoke this inside their transaction while the
     * application row is locked; it is a no-op in any other status or while a
     * rejected document is still outstanding.
     */
    protected function flipToSubmittedWhenResolved(Application $application, User $actor): void
    {
        if ($application->status !== ApplicationStatus::DocumentsRequested) {
            return;
        }

        $hasRejected = $application->documents()
            ->where('status', ApplicationDocumentStatus::Rejected->value)
            ->exists();

        if ($hasRejected) {
            return;
        }

        $application->fill(['status' => ApplicationStatus::Submitted])->saveQuietly();

        AuditLog::record(
            AuditAction::StatusChanged,
            $application,
            [
                'before' => ApplicationStatus::DocumentsRequested->value,
                'after' => ApplicationStatus::Submitted->value,
            ],
            userId: $actor->id,
        );
    }
}
```

- [ ] **Step 5: Create the review action**

`app/Actions/Sao/ReviewApplicationDocument.php`:

```php
<?php

namespace App\Actions\Sao;

use App\Actions\Concerns\ResolvesDocumentsRequested;
use App\Enums\ApplicationDocumentStatus;
use App\Enums\AuditAction;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ReviewApplicationDocument
{
    use ResolvesDocumentsRequested;

    /**
     * Accept or reject a single application document. Rejection carries the
     * reason shown to the applicant; acceptance clears any prior reason. When
     * an acceptance resolves the last rejected document of a DocumentsRequested
     * application, the application flips back to Submitted (shared concern).
     *
     * @throws ValidationException
     */
    public function execute(ApplicationDocument $document, ApplicationDocumentStatus $decision, ?string $notes, User $reviewer): ApplicationDocument
    {
        if ($decision === ApplicationDocumentStatus::Pending) {
            throw new InvalidArgumentException('A document review decision must be accepted or rejected.');
        }

        return DB::transaction(function () use ($document, $decision, $notes, $reviewer): ApplicationDocument {
            // Re-fetch both rows under lock so a concurrent decision or replace
            // can't slip past a stale status check (AUDIT.md AUD-001 pattern).
            $application = Application::query()
                ->whereKey($document->application_id)
                ->lockForUpdate()
                ->firstOrFail();

            $document = ApplicationDocument::query()
                ->whereKey($document->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($application->isTerminal()) {
                throw ValidationException::withMessages([
                    'status' => __('Documents on a decided application can no longer be reviewed.'),
                ]);
            }

            $document->fill([
                'status' => $decision,
                'review_notes' => $decision === ApplicationDocumentStatus::Rejected ? $notes : null,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ])->saveQuietly();

            AuditLog::record(
                $decision === ApplicationDocumentStatus::Accepted
                    ? AuditAction::DocumentAccepted
                    : AuditAction::DocumentRejected,
                $document,
                ['status' => $decision->value, 'notes' => $notes],
                userId: $reviewer->id,
            );

            if ($decision === ApplicationDocumentStatus::Accepted) {
                $this->flipToSubmittedWhenResolved($application, $reviewer);
            }

            return $document;
        });
    }
}
```

- [ ] **Step 6: Create the reject Form Request**

`app/Http/Requests/Sao/RejectApplicationDocumentRequest.php`:

```php
<?php

namespace App\Http\Requests\Sao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RejectApplicationDocumentRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'notes' => ['required', 'string', 'max:1000'],
        ];
    }
}
```

- [ ] **Step 7: Controller methods + show payload**

In `app/Http/Controllers/Sao/ApplicationReviewController.php` (import `App\Actions\Sao\ReviewApplicationDocument`, `App\Enums\ApplicationDocumentStatus`, `App\Http\Requests\Sao\RejectApplicationDocumentRequest`, `App\Models\ApplicationDocument` if not present, `Illuminate\Http\Request`), add after `triage()`:

```php
    public function acceptDocument(
        Request $request,
        Application $application,
        ApplicationDocument $document,
        ReviewApplicationDocument $action,
    ): RedirectResponse {
        $action->execute($document, ApplicationDocumentStatus::Accepted, null, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document accepted.')]);

        return back();
    }

    public function rejectDocument(
        RejectApplicationDocumentRequest $request,
        Application $application,
        ApplicationDocument $document,
        ReviewApplicationDocument $action,
    ): RedirectResponse {
        $action->execute(
            $document,
            ApplicationDocumentStatus::Rejected,
            $request->string('notes')->toString(),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document rejected.')]);

        return back();
    }
```

In the same controller's `show()`, extend the documents `map` payload (after `'uploaded_at' => ...`) with:

```php
                        'status' => $document->status->value,
                        'review_notes' => $document->review_notes,
```

- [ ] **Step 8: Routes**

In `routes/sao.php`, after the `applications/{application}/restore-prior` line add:

```php
        Route::post('applications/{application}/documents/{document}/accept', [ApplicationReviewController::class, 'acceptDocument'])
            ->scopeBindings()
            ->name('applications.documents.accept');
        Route::post('applications/{application}/documents/{document}/reject', [ApplicationReviewController::class, 'rejectDocument'])
            ->scopeBindings()
            ->name('applications.documents.reject');
```

- [ ] **Step 9: Run tests to verify they pass**

Run: `php artisan test --compact --filter=ReviewApplicationDocument`
Expected: PASS (8 tests).

- [ ] **Step 10: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat(sao): accept/reject application documents with shared auto-flip (#80)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 3: Triage guard (≥1 rejected doc) + optional notes

**Files:**
- Modify: `app/Actions/Sao/TriageApplicationAction.php`
- Modify: `app/Http/Requests/Sao/TriageApplicationRequest.php`
- Test: `tests/Feature/Sao/TriageApplicationTest.php` (update 2 existing cases, add 2)

**Interfaces:**
- Consumes: Task 1's `ApplicationDocumentStatus`; Task 2's factory usage patterns.
- Produces: `TriageApplicationAction::execute()` throws `ValidationException` (key `status`) when moving to `DocumentsRequested` with zero rejected documents; `notes` no longer required for that status.

- [ ] **Step 1: Update/write the tests**

In `tests/Feature/Sao/TriageApplicationTest.php`:

**Replace** the `it('requires notes when triaging into DocumentsRequested', ...)` case with:

```php
it('refuses DocumentsRequested when no document is rejected', function () {
    $application = Application::factory()->submitted()->create();
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->post(route('sao.applications.triage', $application), [
        'status' => 'documents_requested',
    ]);

    $response->assertSessionHasErrors('status');
    expect($application->fresh()->status)->toBe(ApplicationStatus::Submitted);
});
```

**Replace** the `it('persists notes alongside the status change', ...)` case with (note the added rejected-document fixture):

```php
it('persists notes alongside the status change', function () {
    $application = Application::factory()->submitted()->create();
    ApplicationDocument::factory()->rejected()->create(['application_id' => $application->id]);
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)->post(route('sao.applications.triage', $application), [
        'status' => 'documents_requested',
        'notes' => 'Please re-upload the GCE A/L scan.',
    ])->assertRedirect();

    $fresh = $application->fresh();
    expect($fresh->status)->toBe(ApplicationStatus::DocumentsRequested)
        ->and($fresh->decision_notes)->toBe('Please re-upload the GCE A/L scan.');
});
```

**Add** two new cases (and `use App\Models\ApplicationDocument;` to the imports):

```php
it('allows DocumentsRequested without notes once a document is rejected', function () {
    $application = Application::factory()->submitted()->create();
    ApplicationDocument::factory()->rejected()->create(['application_id' => $application->id]);
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)->post(route('sao.applications.triage', $application), [
        'status' => 'documents_requested',
    ])->assertRedirect()->assertSessionDoesntHaveErrors();

    expect($application->fresh()->status)->toBe(ApplicationStatus::DocumentsRequested);
});

it('still allows other interim transitions when no document is rejected', function () {
    $application = Application::factory()->submitted()->create();
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)->post(route('sao.applications.triage', $application), [
        'status' => 'under_review',
    ])->assertRedirect()->assertSessionDoesntHaveErrors();

    expect($application->fresh()->status)->toBe(ApplicationStatus::UnderReview);
});
```

- [ ] **Step 2: Run to verify the new/changed cases fail**

Run: `php artisan test --compact --filter=TriageApplication`
Expected: FAIL — "refuses DocumentsRequested when no document is rejected" gets a `notes` error instead of `status` (and the no-notes case 422s).

- [ ] **Step 3: Relax the Form Request**

In `app/Http/Requests/Sao/TriageApplicationRequest.php`, replace the `notes` rule array with:

```php
            'notes' => ['nullable', 'string', 'max:5000'],
```

Remove the now-unused `Rule::requiredIf` import usage if nothing else references it (`Rule::in` remains — keep the `Rule` import).

- [ ] **Step 4: Add the guard to the action**

In `app/Actions/Sao/TriageApplicationAction.php` (import `App\Enums\ApplicationDocumentStatus`), inside the transaction directly after the `canTransitionTo` check, add:

```php
            if ($next === ApplicationStatus::DocumentsRequested) {
                $hasRejected = $application->documents()
                    ->where('status', ApplicationDocumentStatus::Rejected->value)
                    ->exists();

                if (! $hasRejected) {
                    throw ValidationException::withMessages([
                        'status' => __('Reject at least one document before requesting documents.'),
                    ]);
                }
            }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=TriageApplication`
Expected: PASS (7 tests).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat(sao): triage to DocumentsRequested requires a rejected document (#80)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 4: Documents-requested email (event → queued listener → mail)

**Files:**
- Create: `app/Events/ApplicationDocumentsRequested.php`
- Create: `app/Listeners/SendDocumentsRequestedNotification.php`
- Create: `app/Mail/ApplicationDocumentsRequestedMail.php`
- Create: `resources/views/mail/application-documents-requested.blade.php`
- Modify: `app/Actions/Sao/TriageApplicationAction.php` (dispatch on entry)
- Test: `tests/Feature/Sao/TriageApplicationTest.php` (add 2 cases)

**Interfaces:**
- Consumes: Task 3's guarded triage; `Application.contact_email`; route `application.show`.
- Produces: event `ApplicationDocumentsRequested(public Application $application)`; mailable `ApplicationDocumentsRequestedMail(public readonly Application $application)`. Listener auto-discovered via its type-hinted `handle()` (same as `SendApplicationDecisionNotification` — no manual registration).

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Sao/TriageApplicationTest.php` (add imports `App\Mail\ApplicationDocumentsRequestedMail` and `Illuminate\Support\Facades\Mail`):

```php
it('emails the applicant when documents are requested — and again on re-entry', function () {
    Mail::fake();
    $application = Application::factory()->submitted()->create();
    ApplicationDocument::factory()->rejected('Scan is blurry.')->create(['application_id' => $application->id]);
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)->post(route('sao.applications.triage', $application), [
        'status' => 'documents_requested',
    ])->assertRedirect();

    Mail::assertSent(
        ApplicationDocumentsRequestedMail::class,
        fn (ApplicationDocumentsRequestedMail $mail): bool => $mail->hasTo($application->contact_email),
    );

    // Leave and re-enter the status: the mail must fire once per entry.
    $this->actingAs($sao)->post(route('sao.applications.triage', $application), ['status' => 'under_review']);
    $this->actingAs($sao)->post(route('sao.applications.triage', $application), ['status' => 'documents_requested']);

    Mail::assertSentCount(2);
});

it('does not email on other interim transitions', function () {
    Mail::fake();
    $application = Application::factory()->submitted()->create();
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)->post(route('sao.applications.triage', $application), [
        'status' => 'under_review',
    ])->assertRedirect();

    Mail::assertNothingSent();
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test --compact --filter=TriageApplication`
Expected: FAIL — class `App\Mail\ApplicationDocumentsRequestedMail` not found.

- [ ] **Step 3: Create the event**

`app/Events/ApplicationDocumentsRequested.php`:

```php
<?php

namespace App\Events;

use App\Models\Application;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplicationDocumentsRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Application $application,
    ) {}
}
```

- [ ] **Step 4: Create the listener**

`app/Listeners/SendDocumentsRequestedNotification.php`:

```php
<?php

namespace App\Listeners;

use App\Events\ApplicationDocumentsRequested;
use App\Mail\ApplicationDocumentsRequestedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendDocumentsRequestedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Mail the applicant the list of rejected documents (#80). The event fires
     * once per entry into DocumentsRequested — first triage or a re-entry after
     * a failed resubmission cycle — so each request round emails exactly once,
     * addressed to the contact email captured on the application form.
     */
    public function handle(ApplicationDocumentsRequested $event): void
    {
        Mail::to($event->application->contact_email)
            ->send(new ApplicationDocumentsRequestedMail($event->application));
    }
}
```

- [ ] **Step 5: Create the mailable**

`app/Mail/ApplicationDocumentsRequestedMail.php`:

```php
<?php

namespace App\Mail;

use App\Enums\ApplicationDocumentStatus;
use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationDocumentsRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Application $application,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: config('app.name').' — documents requested for your application',
        );
    }

    public function content(): Content
    {
        $rejectedDocuments = $this->application->documents()
            ->where('status', ApplicationDocumentStatus::Rejected->value)
            ->with('documentType:id,name,code')
            ->get();

        return new Content(
            markdown: 'mail.application-documents-requested',
            with: [
                'application' => $this->application,
                'rejectedDocuments' => $rejectedDocuments,
                'applicationUrl' => route('application.show', $this->application),
            ],
        );
    }
}
```

- [ ] **Step 6: Create the markdown view**

`resources/views/mail/application-documents-requested.blade.php`:

```blade
<x-mail::message>
# Documents requested for your application

Hi {{ $application->first_name }} {{ $application->last_name }},

The admission office has reviewed your application and needs you to replace the following
{{ $rejectedDocuments->count() === 1 ? 'document' : 'documents' }} before the review can continue:

@foreach ($rejectedDocuments as $document)
- **{{ $document->documentType->name }}** ({{ $document->documentType->code }})
@if ($document->review_notes)
  — {{ $document->review_notes }}
@endif
@endforeach

@if ($application->decision_notes)
Remarks from the admission office:

> {{ $application->decision_notes }}
@endif

Sign in and open your application to upload the replacement
{{ $rejectedDocuments->count() === 1 ? 'file' : 'files' }} — once every requested document has
been replaced, your application automatically returns to the review queue.

<x-mail::button :url="$applicationUrl">
View my application
</x-mail::button>

Thanks,<br>
The {{ config('app.name') }} team
</x-mail::message>
```

- [ ] **Step 7: Dispatch on entry**

In `app/Actions/Sao/TriageApplicationAction.php` (import `App\Events\ApplicationDocumentsRequested`), after the `AuditLog::record(...)` call and before `return $application;`, add:

```php
            if ($next === ApplicationStatus::DocumentsRequested) {
                DB::afterCommit(function () use ($application): void {
                    event(new ApplicationDocumentsRequested($application->fresh()));
                });
            }
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --compact --filter=TriageApplication`
Expected: PASS (9 tests).

- [ ] **Step 9: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat(admissions): email applicant when documents are requested (#80)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 5: Applicant replace endpoint + auto-resubmit

**Files:**
- Modify: `app/Http/Requests/Applications/StoreApplicationRequest.php` (constants → public)
- Create: `app/Http/Requests/Applications/ReplaceDocumentRequest.php`
- Create: `app/Actions/Applicant/ReplaceRejectedDocument.php`
- Modify: `app/Http/Controllers/Applications/ApplicationController.php` (new method + document payload fields in `show()`)
- Modify: `routes/web.php`
- Test: `tests/Feature/Applications/ReplaceRejectedDocumentTest.php`

**Interfaces:**
- Consumes: Task 2's `ResolvesDocumentsRequested::flipToSubmittedWhenResolved()`, `AuditAction::DocumentResubmitted`; Task 1's enum/states.
- Produces: route `application.documents.replace` — `POST applications/{application}/documents/{document}` (scoped bindings); action `ReplaceRejectedDocument::execute(Application $application, ApplicationDocument $document, UploadedFile $file, User $applicant): ApplicationDocument`; applicant `show()` documents payload now carries `status` + `review_notes`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Applications/ReplaceRejectedDocumentTest.php`:

```php
<?php

use App\Enums\ApplicationDocumentStatus;
use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake();
});

/** An application parked in DocumentsRequested. */
function docsRequestedApplication(): Application
{
    return Application::factory()->state([
        'status' => ApplicationStatus::DocumentsRequested,
        'submitted_at' => now()->subDay(),
    ])->create();
}

/**
 * A rejected document of the given type code on the application. Distinct
 * codes matter: (application_id, document_type_id) is unique.
 */
function rejectedDocOfType(Application $application, string $code): ApplicationDocument
{
    $type = DocumentType::firstOrCreate(['code' => $code], ['name' => $code.' document']);

    return ApplicationDocument::factory()->rejected('Please provide a readable scan.')->create([
        'application_id' => $application->id,
        'document_type_id' => $type->id,
    ]);
}

it('forbids replacing a document on another user\'s application', function () {
    $application = docsRequestedApplication();
    $document = rejectedDocOfType($application, 'NID');
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->post(route('application.documents.replace', [$application, $document]), [
            'document' => UploadedFile::fake()->create('scan.pdf', 512, 'application/pdf'),
        ])
        ->assertForbidden();
});

it('refuses when the application is not in DocumentsRequested', function () {
    $application = Application::factory()->submitted()->create();
    $document = rejectedDocOfType($application, 'NID');

    $this->actingAs($application->applicant)
        ->post(route('application.documents.replace', [$application, $document]), [
            'document' => UploadedFile::fake()->create('scan.pdf', 512, 'application/pdf'),
        ])
        ->assertSessionHasErrors('document');

    expect($document->fresh()->status)->toBe(ApplicationDocumentStatus::Rejected);
});

it('refuses when the document is not rejected', function () {
    $application = docsRequestedApplication();
    rejectedDocOfType($application, 'BIRTH');
    $pendingType = DocumentType::firstOrCreate(['code' => 'NID'], ['name' => 'National Identity']);
    $pending = ApplicationDocument::factory()->create([
        'application_id' => $application->id,
        'document_type_id' => $pendingType->id,
    ]);

    $this->actingAs($application->applicant)
        ->post(route('application.documents.replace', [$application, $pending]), [
            'document' => UploadedFile::fake()->create('scan.pdf', 512, 'application/pdf'),
        ])
        ->assertSessionHasErrors('document');
});

it('validates the upload mime and size', function () {
    $application = docsRequestedApplication();
    $document = rejectedDocOfType($application, 'NID');

    $this->actingAs($application->applicant)
        ->post(route('application.documents.replace', [$application, $document]), [
            'document' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])
        ->assertSessionHasErrors('document');

    $this->actingAs($application->applicant)
        ->post(route('application.documents.replace', [$application, $document]), [
            'document' => UploadedFile::fake()->create('huge.pdf', 9000, 'application/pdf'),
        ])
        ->assertSessionHasErrors('document');
});

it('replaces the file in place and resets the review state', function () {
    $application = docsRequestedApplication();
    rejectedDocOfType($application, 'BIRTH'); // second outstanding doc — no flip yet
    $document = rejectedDocOfType($application, 'NID');
    $oldPath = $document->file_path;
    Storage::put($oldPath, 'old-content');

    $this->actingAs($application->applicant)
        ->post(route('application.documents.replace', [$application, $document]), [
            'document' => UploadedFile::fake()->create('nid-rescan.pdf', 512, 'application/pdf'),
        ])
        ->assertRedirect();

    $fresh = $document->fresh();
    expect($fresh->status)->toBe(ApplicationDocumentStatus::Pending)
        ->and($fresh->original_filename)->toBe('nid-rescan.pdf')
        ->and($fresh->review_notes)->toBeNull()
        ->and($fresh->reviewed_by)->toBeNull()
        ->and($fresh->reviewed_at)->toBeNull()
        ->and($fresh->file_path)->not->toBe($oldPath);

    Storage::assertMissing($oldPath);
    Storage::assertExists($fresh->file_path);

    AuditLog::query()
        ->where('subject_type', $document->getMorphClass())
        ->where('subject_id', $document->id)
        ->where('action', AuditAction::DocumentResubmitted->value)
        ->sole();

    // One rejected document remains — the application must not flip yet.
    expect($application->fresh()->status)->toBe(ApplicationStatus::DocumentsRequested);
});

it('flips to Submitted when the last rejected document is replaced', function () {
    $application = docsRequestedApplication();
    $originalSubmittedAt = $application->submitted_at;
    $document = rejectedDocOfType($application, 'NID');

    $this->actingAs($application->applicant)
        ->post(route('application.documents.replace', [$application, $document]), [
            'document' => UploadedFile::fake()->create('nid-rescan.pdf', 512, 'application/pdf'),
        ])
        ->assertRedirect();

    $fresh = $application->fresh();
    expect($fresh->status)->toBe(ApplicationStatus::Submitted)
        ->and($fresh->submitted_at->equalTo($originalSubmittedAt))->toBeTrue();

    $log = AuditLog::query()
        ->where('subject_type', $application->getMorphClass())
        ->where('subject_id', $application->id)
        ->where('action', AuditAction::StatusChanged->value)
        ->sole();
    expect($log->changes)->toBe(['before' => 'documents_requested', 'after' => 'submitted'])
        ->and($log->user_id)->toBe($application->user_id);
});

it('scopes the document to its application', function () {
    $application = docsRequestedApplication();
    $foreign = rejectedDocOfType(docsRequestedApplication(), 'NID');

    $this->actingAs($application->applicant)
        ->post(route('application.documents.replace', [$application, $foreign]), [
            'document' => UploadedFile::fake()->create('scan.pdf', 512, 'application/pdf'),
        ])
        ->assertNotFound();
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test --compact --filter=ReplaceRejectedDocument`
Expected: FAIL — route `application.documents.replace` not defined.

- [ ] **Step 3: Make the upload constants shared**

In `app/Http/Requests/Applications/StoreApplicationRequest.php` change:

```php
    private const ALLOWED_MIMES = ['pdf', 'jpg', 'jpeg', 'png'];

    private const MAX_FILE_KB = 8192;
```

to:

```php
    public const ALLOWED_MIMES = ['pdf', 'jpg', 'jpeg', 'png'];

    public const MAX_FILE_KB = 8192;
```

- [ ] **Step 4: Create the replace Form Request**

`app/Http/Requests/Applications/ReplaceDocumentRequest.php`:

```php
<?php

namespace App\Http\Requests\Applications;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReplaceDocumentRequest extends FormRequest
{
    /**
     * Same file constraints as the original submission — one replacement file
     * for a single rejected document.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'document' => [
                'required', 'file',
                'mimes:'.implode(',', StoreApplicationRequest::ALLOWED_MIMES),
                'max:'.StoreApplicationRequest::MAX_FILE_KB,
            ],
        ];
    }
}
```

- [ ] **Step 5: Create the replace action**

`app/Actions/Applicant/ReplaceRejectedDocument.php`:

```php
<?php

namespace App\Actions\Applicant;

use App\Actions\Concerns\ResolvesDocumentsRequested;
use App\Enums\ApplicationDocumentStatus;
use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReplaceRejectedDocument
{
    use ResolvesDocumentsRequested;

    /**
     * Replace a rejected document's file in place. The row keeps its identity —
     * (application_id, document_type_id) is unique including trashed rows, so
     * delete-and-recreate is not an option — and returns to pending review with
     * its review metadata cleared (history stays in the audit log). Resolving
     * the last rejected document flips the application back to Submitted.
     *
     * The new file is stored before the transaction opens and removed again on
     * any failure; the replaced file is deleted only after commit (AUD-009).
     *
     * @throws ValidationException
     */
    public function execute(Application $application, ApplicationDocument $document, UploadedFile $file, User $applicant): ApplicationDocument
    {
        $newPath = $file->store('applications');
        $oldPath = null;

        try {
            $document = DB::transaction(function () use ($application, $document, $file, $applicant, $newPath, &$oldPath): ApplicationDocument {
                $application = Application::query()
                    ->whereKey($application->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $document = ApplicationDocument::query()
                    ->whereKey($document->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($application->status !== ApplicationStatus::DocumentsRequested) {
                    throw ValidationException::withMessages([
                        'document' => __('Documents can only be replaced while the application is awaiting your documents.'),
                    ]);
                }

                if ($document->status !== ApplicationDocumentStatus::Rejected) {
                    throw ValidationException::withMessages([
                        'document' => __('Only rejected documents can be replaced.'),
                    ]);
                }

                $oldPath = $document->file_path;
                $previousFilename = $document->original_filename;

                $document->fill([
                    'file_path' => $newPath,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size_bytes' => $file->getSize(),
                    'uploaded_at' => now(),
                    'status' => ApplicationDocumentStatus::Pending,
                    'review_notes' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ])->saveQuietly();

                AuditLog::record(
                    AuditAction::DocumentResubmitted,
                    $document,
                    ['before' => $previousFilename, 'after' => $document->original_filename],
                    userId: $applicant->id,
                );

                $this->flipToSubmittedWhenResolved($application, $applicant);

                return $document;
            });
        } catch (Throwable $exception) {
            Storage::delete($newPath);

            throw $exception;
        }

        if ($oldPath !== null) {
            Storage::delete($oldPath);
        }

        return $document;
    }
}
```

- [ ] **Step 6: Controller method + show payload**

In `app/Http/Controllers/Applications/ApplicationController.php` (import `App\Actions\Applicant\ReplaceRejectedDocument` and `App\Http\Requests\Applications\ReplaceDocumentRequest`), add after `show()`:

```php
    /**
     * Replace one rejected document on the caller's own DocumentsRequested
     * application. Status/ownership guards live in ReplaceRejectedDocument;
     * replacing the last rejected document auto-resubmits the application.
     */
    public function replaceDocument(
        ReplaceDocumentRequest $request,
        Application $application,
        ApplicationDocument $document,
        ReplaceRejectedDocument $action,
    ): RedirectResponse {
        abort_if($application->user_id !== $request->user()->id, 403);

        $action->execute($application, $document, $request->file('document'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document uploaded — it will be re-checked by the admission office.')]);

        return back();
    }
```

In the same controller's `show()`, extend the documents `map` payload (after `'uploaded_at' => ...`) with:

```php
                        'status' => $document->status->value,
                        'review_notes' => $document->review_notes,
```

- [ ] **Step 7: Route**

In `routes/web.php`, after the `application.documents.view` route registration add:

```php
    // Applicant response to DocumentsRequested (#80): replace one rejected
    // document; the action flips the application back to Submitted when the
    // last rejected document is resolved.
    Route::post('applications/{application}/documents/{document}', [ApplicationController::class, 'replaceDocument'])
        ->scopeBindings()
        ->name('application.documents.replace');
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --compact --filter=ReplaceRejectedDocument`
Expected: PASS (7 tests).

- [ ] **Step 9: Backend regression + pint + commit**

```bash
php artisan test --compact --testsuite=Unit,Feature
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat(applicant): replace rejected documents with auto-resubmit (#80)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

Expected: full Unit+Feature suite green (~720 tests). If any unrelated test asserts on the old `notes`-required triage behaviour or document payload shape, fix it here before committing.

---

### Task 6: SAO Review page — document review UI

**Files:**
- Modify: `resources/js/lib/statusDisplay.ts`
- Modify: `resources/js/pages/sao/applications/Review.vue`

**Interfaces:**
- Consumes: Task 2's routes (Wayfinder: `sao.applications.documents.accept/reject`), `show()` payload fields `status` / `review_notes`; PrimeVue `Dialog`, `Textarea`, `Tag` already imported in the page.
- Produces: `applicationDocumentStatusLabel(status: string): string` and `applicationDocumentStatusSeverity(status: string): TagSeverity` in `statusDisplay.ts`.

- [ ] **Step 1: Regenerate Wayfinder barrels**

Run: `php artisan wayfinder:generate --no-interaction`
Expected: `resources/js/routes/sao/applications/documents/` now exists.

- [ ] **Step 2: statusDisplay map**

In `resources/js/lib/statusDisplay.ts`, add below the `APPLICATION_STATUS` block:

```ts
/** `App\Enums\ApplicationDocumentStatus` */
const APPLICATION_DOCUMENT_STATUS: Record<
    string,
    { label: string; severity: TagSeverity }
> = {
    pending: { label: 'Awaiting review', severity: 'warn' },
    accepted: { label: 'Accepted', severity: 'success' },
    rejected: { label: 'Rejected', severity: 'danger' },
};
```

and next to the other exported helpers:

```ts
export function applicationDocumentStatusLabel(status: string): string {
    return APPLICATION_DOCUMENT_STATUS[status]?.label ?? status;
}

export function applicationDocumentStatusSeverity(status: string): TagSeverity {
    return APPLICATION_DOCUMENT_STATUS[status]?.severity ?? 'secondary';
}
```

- [ ] **Step 3: Review.vue — script changes**

(Consult https://primevue.org/llms/llms-full.txt for `Dialog`/`Textarea` props before writing markup — standing rule.)

In `resources/js/pages/sao/applications/Review.vue`:

1. Extend `DocumentRow`:

```ts
type DocumentRow = {
    id: number;
    original_filename: string;
    mime_type: string;
    size_bytes: number;
    uploaded_at: string | null;
    status: string;
    review_notes: string | null;
    document_type: DocumentType;
};
```

2. Add imports: `Check, X` to the `lucide-vue-next` import list; `router` to the `@inertiajs/vue3` import; `applicationDocumentStatusLabel, applicationDocumentStatusSeverity` to the `@/lib/statusDisplay` import.

3. **Delete** the `triageNotesRequired` computed (notes are optional now) and remove its usage in the template (the triage notes label/asterisk — relabel the field "Notes (optional)").

4. Add below the `openDocument` function:

```ts
const rejectDialogVisible = ref(false);
const rejectTarget = ref<DocumentRow | null>(null);
const rejectForm = useForm({ notes: '' });

function acceptDocument(doc: DocumentRow): void {
    router.post(
        sao.applications.documents.accept({
            application: props.application.id,
            document: doc.id,
        }).url,
        {},
        { preserveScroll: true },
    );
}

function openRejectDialog(doc: DocumentRow): void {
    rejectTarget.value = doc;
    rejectForm.reset();
    rejectForm.clearErrors();
    rejectDialogVisible.value = true;
}

function submitReject(): void {
    if (!rejectTarget.value) {
        return;
    }

    rejectForm.post(
        sao.applications.documents.reject({
            application: props.application.id,
            document: rejectTarget.value.id,
        }).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                rejectDialogVisible.value = false;
                rejectTarget.value = null;
            },
        },
    );
}
```

- [ ] **Step 4: Review.vue — template changes**

In the Documents `DataTable`:

1. After the `original_filename` column, add a status column:

```html
                    <Column header="Review" style="width: 11rem">
                        <template #body="{ data }">
                            <div class="flex flex-col gap-1">
                                <Tag
                                    :value="
                                        applicationDocumentStatusLabel(
                                            data.status,
                                        )
                                    "
                                    :severity="
                                        applicationDocumentStatusSeverity(
                                            data.status,
                                        )
                                    "
                                />
                                <span
                                    v-if="data.review_notes"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ data.review_notes }}
                                </span>
                            </div>
                        </template>
                    </Column>
```

2. Inside the existing actions column's `flex` div (next to View/Download), add — only for non-terminal applications:

```html
                                <template v-if="!application.is_terminal">
                                    <Button
                                        v-if="data.status !== 'accepted'"
                                        label="Accept"
                                        severity="success"
                                        text
                                        size="small"
                                        @click="acceptDocument(data)"
                                    >
                                        <template #icon>
                                            <Check class="size-4" />
                                        </template>
                                    </Button>
                                    <Button
                                        v-if="data.status !== 'rejected'"
                                        label="Reject"
                                        severity="danger"
                                        text
                                        size="small"
                                        @click="openRejectDialog(data)"
                                    >
                                        <template #icon>
                                            <X class="size-4" />
                                        </template>
                                    </Button>
                                </template>
```

3. At the end of the root template element (next to the existing `FileViewerDialog`), add the reject dialog:

```html
        <Dialog
            v-model:visible="rejectDialogVisible"
            modal
            header="Reject document"
            :style="{ width: '28rem' }"
        >
            <div class="space-y-3">
                <p class="text-sm text-muted-foreground">
                    Tell the applicant what is wrong with
                    <strong>{{
                        rejectTarget?.document_type.name ?? 'this document'
                    }}</strong>
                    — they will see this reason and upload a replacement.
                </p>
                <Textarea
                    v-model="rejectForm.notes"
                    class="w-full"
                    rows="4"
                    :invalid="Boolean(rejectForm.errors.notes)"
                    placeholder="e.g. The scan is cropped — edges are missing."
                />
                <p
                    v-if="rejectForm.errors.notes"
                    class="text-sm text-red-600 dark:text-red-400"
                >
                    {{ rejectForm.errors.notes }}
                </p>
            </div>
            <template #footer>
                <Button
                    label="Cancel"
                    severity="secondary"
                    text
                    @click="rejectDialogVisible = false"
                />
                <Button
                    label="Reject document"
                    severity="danger"
                    :loading="rejectForm.processing"
                    @click="submitReject"
                />
            </template>
        </Dialog>
```

Note: `Dialog` is not yet imported in this page — add `import Dialog from 'primevue/dialog';` with the other PrimeVue imports.

- [ ] **Step 5: Verify frontend gate**

Run: `npm run build && npm run types:check && npm run lint:check`
Expected: all pass, no new chunk-size warnings.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat(sao): document review UI on the application review page (#80)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 7: Applicant Show page — rejected-document response UI

**Files:**
- Modify: `resources/js/pages/applicant/applications/Show.vue`

**Interfaces:**
- Consumes: Task 5's route (Wayfinder: `application_routes.documents.replace`), `show()` payload fields `status` / `review_notes`; Task 6's `applicationDocumentStatusLabel/Severity`.
- Produces: nothing downstream — leaf UI.

- [ ] **Step 1: Script changes**

(Consult https://primevue.org/llms/llms-full.txt for `FileUpload` basic/custom-upload usage — standing rule.)

In `resources/js/pages/applicant/applications/Show.vue`:

1. Extend the `ApplicationDocument` type:

```ts
type ApplicationDocument = {
    id: number;
    original_filename: string;
    mime_type: string;
    size_bytes: number;
    uploaded_at: string | null;
    status: string;
    review_notes: string | null;
    document_type: DocumentTypeRef;
};
```

2. Add imports:

```ts
import { Head, router } from '@inertiajs/vue3';
import FileUpload, { type FileUploadUploaderEvent } from 'primevue/fileupload';
import Message from 'primevue/message';
import { computed } from 'vue';
import {
    applicationDocumentStatusLabel,
    applicationDocumentStatusSeverity,
    degreeLabel,
    statusLabel,
    statusSeverity,
} from '@/lib/statusDisplay';
import application_routes from '@/routes/application';
```

(merge with the existing import lines rather than duplicating them).

3. Add below `formatBytes`:

```ts
const rejectedCount = computed(
    () =>
        props.application.documents.filter((d) => d.status === 'rejected')
            .length,
);

const awaitingResponse = computed(
    () =>
        props.application.status === 'documents_requested' &&
        rejectedCount.value > 0,
);

function canReplace(doc: ApplicationDocument): boolean {
    return (
        props.application.status === 'documents_requested' &&
        doc.status === 'rejected'
    );
}

function uploadReplacement(
    doc: ApplicationDocument,
    event: FileUploadUploaderEvent,
): void {
    const file = Array.isArray(event.files) ? event.files[0] : event.files;

    if (!file) {
        return;
    }

    router.post(
        application_routes.documents.replace({
            application: props.application.id,
            document: doc.id,
        }).url,
        { document: file },
        { forceFormData: true, preserveScroll: true },
    );
}
```

- [ ] **Step 2: Template changes**

1. Directly under the header `Card` (before the Submitted documents card), add the banner:

```html
        <Message v-if="awaitingResponse" severity="warn" :closable="false">
            The admission office needs you to replace
            {{ rejectedCount === 1 ? 'one document' : `${rejectedCount} documents` }}
            before your application can continue. Each rejected document below
            shows the reason and an upload button — once every requested
            document is replaced, your application automatically returns to the
            review queue.
        </Message>
```

2. In the Submitted documents `DataTable`, after the `original_filename` column add:

```html
                    <Column header="Status" style="width: 16rem">
                        <template #body="{ data }">
                            <div class="flex flex-col gap-1">
                                <Tag
                                    :value="
                                        applicationDocumentStatusLabel(
                                            data.status,
                                        )
                                    "
                                    :severity="
                                        applicationDocumentStatusSeverity(
                                            data.status,
                                        )
                                    "
                                />
                                <span
                                    v-if="
                                        data.status === 'rejected' &&
                                        data.review_notes
                                    "
                                    class="text-xs text-red-600 dark:text-red-400"
                                >
                                    {{ data.review_notes }}
                                </span>
                            </div>
                        </template>
                    </Column>
                    <Column header="" style="width: 10rem">
                        <template #body="{ data }">
                            <FileUpload
                                v-if="canReplace(data)"
                                mode="basic"
                                accept=".pdf,.jpg,.jpeg,.png"
                                :max-file-size="8 * 1024 * 1024"
                                choose-label="Replace"
                                custom-upload
                                auto
                                @uploader="
                                    (event: FileUploadUploaderEvent) =>
                                        uploadReplacement(data, event)
                                "
                            />
                        </template>
                    </Column>
```

- [ ] **Step 3: Verify frontend gate**

Run: `npm run build && npm run types:check && npm run lint:check`
Expected: all pass.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat(applicant): rejected-document response UI on the application page (#80)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 8: Full quality gate

**Files:** none new — verification only.

- [ ] **Step 1: Run the complete per-feature gate**

```bash
vendor/bin/pint --format agent
php artisan test --compact --testsuite=Unit,Feature
npm run build
npm run types:check
npm run lint:check
php artisan migrate:fresh --seed --no-interaction
```

Expected: everything green; suite ≈ 720+ tests; no chunk-size warning from the build. Fix and commit anything that fails before proceeding.

---

### Task 9: Docs, context log, PR

**Files:**
- Modify: `docs/modules/admissions.md` (roles/abilities, routes & screens, flows, audit actions, tests, file map — per the shipped code)
- Modify: `docs/routes.md` (count 122 → 125; new rows in the SAO and shared sections)
- Modify: `docs/guides/applicant.md` (what "Documents requested" now means + how to respond)
- Modify: `docs/guides/sao.md` (document accept/reject + the ≥1-rejected triage rule)
- Create: `docs/adr/0023-structured-application-document-review.md`
- Modify: `plan/context.md` (new § entry)

- [ ] **Step 1: Run the docs-refresh skill** scoped to this feature (routing table: route/controller → `docs/routes.md` + `docs/modules/admissions.md`; new mail → owning module; user-visible flow → both guides; decision → new ADR). Verify every claim against the shipped code, not this plan.

- [ ] **Step 2: Write ADR-0023** (via the write-adr skill): context = free-text `DocumentsRequested` dead end (#80); decision = per-document three-state review + ≥1-rejected triage guard + auto-flip + entry email; consequences = `decision_notes` no longer required for triage, per-document audit trail, no extra-document-type requests (explicitly out of scope).

- [ ] **Step 3: Append the `plan/context.md` § entry** (next number after §24) recording: issue #80, branch, per-task commits, gate results, docs touched, and the out-of-scope list from the spec.

- [ ] **Step 4: Commit docs, push, open the PR**

```bash
git add -A
git commit -m "docs: sync admissions module, routes, guides + ADR-0023 for #80

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
git push -u origin feat/documents-requested-response
gh pr create --base main --title "feat(admissions): applicant response flow for DocumentsRequested (#80)" --body "Closes #80. <summary of shipped behaviour + gate results>

🤖 Generated with [Claude Code](https://claude.com/claude-code)"
```

Expected: PR opens; wait for all 4 CI checks (ci 8.4 / ci 8.5 / quality / browser) before merging (squash + delete branch), then fast-forward local `main`.

---

## Self-review notes (already applied)

- **Spec coverage:** every spec section maps to a task — schema/enum (T1), SAO review + concern + payload (T2), triage guard + notes relaxation (T3), mail (T4), applicant replace + payload (T5), SAO UI (T6), applicant UI (T7), gate (T8), docs/ADR/PR (T9). Out-of-scope items need no task.
- **Route URI:** plural `applications/...` prefix is a deliberate refinement of the spec's singular form (matches the download/view siblings); route names unchanged from spec.
- **Type consistency:** `flipToSubmittedWhenResolved(Application, User): void` is consumed with that exact signature in T2 and T5; `ApplicationDocumentStatus` case values (`pending`/`accepted`/`rejected`) match the statusDisplay keys in T6 and the string literals in T7's `canReplace`.
- **Mail assertion style:** the mailable is `Queueable` but sent via `Mail::send()` from a `ShouldQueue` listener (exactly like `ApplicationDecisionMail`), so tests use `Mail::assertSent`, not `assertQueued`.
