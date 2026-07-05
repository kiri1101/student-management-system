# Results & Dispute Notifications Implementation Plan (#81)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Notify a student (email + in-app) when their course results are published and when their result dispute reaches a terminal outcome.

**Architecture:** Two queued `Notification` classes on the shipped #12 chain — Action → `DB::afterCommit` Event → queued Listener → `Notification::send` → Notification (`via` mail+database). Content is nudge-only (no scores). Recipients: publish → only the students whose results were just published (ids captured at publish time); dispute → the single disputing student, terminal outcomes only.

**Tech Stack:** Laravel 13 / PHP 8.4, Laravel Notifications (mail + database channels), Pest v4, Inertia v3 + Vue 3 (student dashboard feed), MySQL local / SQLite tests.

## Global Constraints

- **Mirror the #12 pattern exactly** (`CourseSessionChangedNotification` + `SendCourseSessionChangedNotification` + `mail.course-session-changed`). Both new notifications and listeners are `implements ShouldQueue`; both notifications `via() => ['mail','database']` with a `toMail()` markdown and a stable `toArray()` in-app payload.
- **Wiring is Laravel automatic event–listener discovery** — no `EventServiceProvider`/`$listen` entry (verified: only auth events are registered explicitly).
- **Nudge-only content:** no numeric scores/grades in any mail or `toArray` payload. The dispute message includes the qualitative outcome + resolution notes only.
- **Recipients:** publish notifies only students whose Draft→Published results flipped in that call (captured `student_profile_id`s); dispute notifies the single disputing student, and only on a **terminal** status (`Resolved`/`Rejected`), never `UnderReview`.
- **No new routes, no schema change, no new `AuditAction`, no ADR.** The existing `ResultsPublished` / `DisputeResolved` audit rows remain the forensic record.
- Run `vendor/bin/pint --dirty --format agent` after PHP changes. Tests: `php artisan test --compact --filter=<Name>`; full runs `--testsuite=Unit,Feature` only. Frontend gate: `npm run build && npm run types:check && npm run lint:check` (no chunk-size warning). PrimeVue per-page imports, lucide icons.
- Commit per task on branch `feat/results-notifications`, message ending with:
  `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`

---

### Task 1: Results-published notification (end to end)

**Files:**
- Create: `app/Events/CourseResultsPublished.php`
- Create: `app/Notifications/CourseResultsPublishedNotification.php`
- Create: `resources/views/mail/course-results-published.blade.php`
- Create: `app/Listeners/SendCourseResultsPublishedNotification.php`
- Modify: `app/Actions/Sao/PublishCourseResults.php`
- Test: `tests/Feature/Courses/CourseResultsPublishedNotificationTest.php`

**Interfaces:**
- Consumes: `PublishCourseResults::publish(Course $course, User $publisher): int` (existing); `Course::cohortStudents()`; `StudentProfile::user`.
- Produces: `App\Events\CourseResultsPublished(Course $course, array $studentProfileIds)`; `App\Notifications\CourseResultsPublishedNotification(Course $course)` with `via()`→`['mail','database']` and `toArray()` `type` = `'course_results_published'`.

- [ ] **Step 1: Write the failing feature test**

Create `tests/Feature/Courses/CourseResultsPublishedNotificationTest.php`:

```php
<?php

use App\Enums\AuditAction;
use App\Enums\ResultStatus;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseResult;
use App\Models\StudentProfile;
use App\Notifications\CourseResultsPublishedNotification;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    Notification::fake();
});

/**
 * A draft result for the given student on the given course, with both marks
 * present (so it qualifies for publication).
 */
function resultsNotifScoredDraft(Course $course, StudentProfile $student): CourseResult
{
    return CourseResult::factory()->create([
        'course_id' => $course->id,
        'student_profile_id' => $student->id,
        'ca_score' => 55,
        'exam_score' => 65,
        'status' => ResultStatus::Draft->value,
    ]);
}

it('notifies only the students whose results were published, on mail and database', function () {
    $sao = userWithRole(RoleName::Sao);
    $course = Course::factory()->approved()->create();

    $studentA = StudentProfile::factory()->create();
    $studentB = StudentProfile::factory()->create();
    resultsNotifScoredDraft($course, $studentA);
    resultsNotifScoredDraft($course, $studentB);

    // In the cohort but with no marks yet — nothing is published for them.
    $studentC = StudentProfile::factory()->create();
    CourseResult::factory()->unscored()->create([
        'course_id' => $course->id,
        'student_profile_id' => $studentC->id,
    ]);

    $this->actingAs($sao)
        ->post(route('sao.courses.publishResults', $course))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    foreach ([$studentA, $studentB] as $student) {
        Notification::assertSentTo(
            $student->user,
            CourseResultsPublishedNotification::class,
            function (CourseResultsPublishedNotification $notification, array $channels) use ($course): bool {
                return in_array('mail', $channels, true)
                    && in_array('database', $channels, true)
                    && $notification->course->is($course);
            },
        );
    }

    Notification::assertNotSentTo($studentC->user, CourseResultsPublishedNotification::class);

    expect(AuditLog::query()
        ->where('subject_type', $course->getMorphClass())
        ->where('subject_id', $course->id)
        ->where('action', AuditAction::ResultsPublished->value)
        ->exists())->toBeTrue();
});

it('re-publishing notifies only the newly published students', function () {
    $sao = userWithRole(RoleName::Sao);
    $course = Course::factory()->approved()->create();

    // Already published in an earlier round.
    $studentA = StudentProfile::factory()->create();
    CourseResult::factory()->published()->create([
        'course_id' => $course->id,
        'student_profile_id' => $studentA->id,
        'ca_score' => 55,
        'exam_score' => 65,
    ]);

    // Newly scored draft.
    $studentB = StudentProfile::factory()->create();
    resultsNotifScoredDraft($course, $studentB);

    $this->actingAs($sao)
        ->post(route('sao.courses.publishResults', $course))
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($studentB->user, CourseResultsPublishedNotification::class);
    Notification::assertNotSentTo($studentA->user, CourseResultsPublishedNotification::class);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=CourseResultsPublishedNotificationTest`
Expected: FAIL — `Class "App\Notifications\CourseResultsPublishedNotification" not found`.

- [ ] **Step 3: Create the event**

`app/Events/CourseResultsPublished.php`:

```php
<?php

namespace App\Events;

use App\Models\Course;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CourseResultsPublished
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, int>  $studentProfileIds
     */
    public function __construct(
        public Course $course,
        public array $studentProfileIds,
    ) {}
}
```

- [ ] **Step 4: Create the notification**

`app/Notifications/CourseResultsPublishedNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CourseResultsPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Course $course,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(config('app.name').' — '.$this->course->code.' results published')
            ->markdown('mail.course-results-published', [
                'course' => $this->course,
                'url' => route('student.results.index'),
            ]);
    }

    /**
     * The in-app payload. The student notification feed depends on this exact
     * shape — keep keys and value formats stable.
     *
     * @return array{type: string, course_id: int, course_code: string, course_title: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'course_results_published',
            'course_id' => $this->course->id,
            'course_code' => $this->course->code,
            'course_title' => $this->course->title,
        ];
    }
}
```

- [ ] **Step 5: Create the mail view**

`resources/views/mail/course-results-published.blade.php`:

```blade
<x-mail::message>
# {{ $course->code }} results published

Hi {{ $notifiable->name ?? 'there' }},

Your results for **{{ $course->code }} — {{ $course->title }}** are now available.

<x-mail::button :url="$url">
View my results
</x-mail::button>

Thanks,<br>
The {{ config('app.name') }} team
</x-mail::message>
```

- [ ] **Step 6: Create the listener**

`app/Listeners/SendCourseResultsPublishedNotification.php`:

```php
<?php

namespace App\Listeners;

use App\Events\CourseResultsPublished;
use App\Models\StudentProfile;
use App\Notifications\CourseResultsPublishedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class SendCourseResultsPublishedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Notify each student whose result was just published, on mail + in-app.
     * Recipients are the exact set captured at publish time, so a re-publish
     * for late marks notifies only the newly-published students.
     */
    public function handle(CourseResultsPublished $event): void
    {
        $users = StudentProfile::query()
            ->whereIn('id', $event->studentProfileIds)
            ->with('user:id,name,email')
            ->get()
            ->pluck('user')
            ->filter();

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new CourseResultsPublishedNotification($event->course));
    }
}
```

- [ ] **Step 7: Dispatch the event from the publish action**

Modify `app/Actions/Sao/PublishCourseResults.php`. Add the import and, inside the transaction after the audit write, capture the recipient ids and dispatch after commit:

```php
use App\Events\CourseResultsPublished;
```

```php
            $count = $results->count();
            $studentProfileIds = $results->pluck('student_profile_id')->all();

            AuditLog::record(
                AuditAction::ResultsPublished,
                $course,
                ['count' => $count],
                userId: $publisher->id,
            );

            DB::afterCommit(function () use ($course, $studentProfileIds): void {
                event(new CourseResultsPublished($course, $studentProfileIds));
            });

            return $count;
```

- [ ] **Step 8: Run the test to verify it passes**

Run: `php artisan test --compact --filter=CourseResultsPublishedNotificationTest`
Expected: PASS (2 tests).

- [ ] **Step 9: Format**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 10: Commit**

```bash
git add app/Events/CourseResultsPublished.php app/Notifications/CourseResultsPublishedNotification.php resources/views/mail/course-results-published.blade.php app/Listeners/SendCourseResultsPublishedNotification.php app/Actions/Sao/PublishCourseResults.php tests/Feature/Courses/CourseResultsPublishedNotificationTest.php
git commit -m "feat(results): notify students when their course results are published (#81)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 2: Dispute-reviewed notification (end to end)

**Files:**
- Create: `app/Events/ResultDisputeReviewed.php`
- Create: `app/Notifications/ResultDisputeReviewedNotification.php`
- Create: `resources/views/mail/result-dispute-reviewed.blade.php`
- Create: `app/Listeners/SendResultDisputeReviewedNotification.php`
- Modify: `app/Actions/ReviewResultDispute.php`
- Test: `tests/Feature/Courses/ResultDisputeReviewedNotificationTest.php`

**Interfaces:**
- Consumes: `ReviewResultDispute::review(ResultDispute $dispute, DisputeStatus $status, ?string $notes, User $reviewer): ResultDispute` (existing); `ResultDispute::studentProfile`, `ResultDispute::courseResult` → `CourseResult::course`; `DisputeStatus::isTerminal()`.
- Produces: `App\Events\ResultDisputeReviewed(ResultDispute $dispute)`; `App\Notifications\ResultDisputeReviewedNotification(ResultDispute $dispute)` with `via()`→`['mail','database']` and `toArray()` `type` = `'result_dispute_reviewed'`.

- [ ] **Step 1: Write the failing feature test**

Create `tests/Feature/Courses/ResultDisputeReviewedNotificationTest.php`:

```php
<?php

use App\Enums\DisputeStatus;
use App\Enums\RoleName;
use App\Models\CourseResult;
use App\Models\ResultDispute;
use App\Models\StudentProfile;
use App\Notifications\ResultDisputeReviewedNotification;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    Notification::fake();
});

/**
 * An Open dispute by the given student on a published result of theirs.
 */
function disputeNotifOpenDispute(StudentProfile $student): ResultDispute
{
    $result = CourseResult::factory()->published()->create([
        'student_profile_id' => $student->id,
    ]);

    return ResultDispute::factory()->create([
        'course_result_id' => $result->id,
        'student_profile_id' => $student->id,
        'status' => DisputeStatus::Open->value,
    ]);
}

it('notifies the disputing student when a dispute is resolved, on mail and database', function () {
    $sao = userWithRole(RoleName::Sao);
    $student = StudentProfile::factory()->create();
    $dispute = disputeNotifOpenDispute($student);

    $this->actingAs($sao)
        ->post(route('sao.disputes.review', $dispute), [
            'status' => DisputeStatus::Resolved->value,
            'resolution_notes' => 'Exam mark corrected after recheck.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Notification::assertSentTo(
        $student->user,
        ResultDisputeReviewedNotification::class,
        function (ResultDisputeReviewedNotification $notification, array $channels): bool {
            return in_array('mail', $channels, true)
                && in_array('database', $channels, true)
                && $notification->dispute->status === DisputeStatus::Resolved;
        },
    );
});

it('notifies the disputing student when a dispute is rejected', function () {
    $sao = userWithRole(RoleName::Sao);
    $student = StudentProfile::factory()->create();
    $dispute = disputeNotifOpenDispute($student);

    $this->actingAs($sao)
        ->post(route('sao.disputes.review', $dispute), [
            'status' => DisputeStatus::Rejected->value,
            'resolution_notes' => 'Original mark stands.',
        ])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($student->user, ResultDisputeReviewedNotification::class);
});

it('sends nothing when a dispute is only moved to under review', function () {
    $sao = userWithRole(RoleName::Sao);
    $student = StudentProfile::factory()->create();
    $dispute = disputeNotifOpenDispute($student);

    $this->actingAs($sao)
        ->post(route('sao.disputes.review', $dispute), [
            'status' => DisputeStatus::UnderReview->value,
            'resolution_notes' => null,
        ])
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=ResultDisputeReviewedNotificationTest`
Expected: FAIL — `Class "App\Notifications\ResultDisputeReviewedNotification" not found`.

- [ ] **Step 3: Create the event**

`app/Events/ResultDisputeReviewed.php`:

```php
<?php

namespace App\Events;

use App\Models\ResultDispute;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResultDisputeReviewed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ResultDispute $dispute,
    ) {}
}
```

- [ ] **Step 4: Create the notification**

`app/Notifications/ResultDisputeReviewedNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Models\ResultDispute;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResultDisputeReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ResultDispute $dispute,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $course = $this->dispute->courseResult->course;

        return (new MailMessage)
            ->subject(config('app.name').' — dispute reviewed')
            ->markdown('mail.result-dispute-reviewed', [
                'course' => $course,
                'status' => $this->dispute->status->value,
                'resolutionNotes' => $this->dispute->resolution_notes,
                'url' => route('student.results.index'),
            ]);
    }

    /**
     * The in-app payload. The student notification feed depends on this exact
     * shape — keep keys and value formats stable.
     *
     * @return array{type: string, course_id: int, course_code: string, course_title: string, dispute_id: int, status: string, resolution_notes: string|null}
     */
    public function toArray(object $notifiable): array
    {
        $course = $this->dispute->courseResult->course;

        return [
            'type' => 'result_dispute_reviewed',
            'course_id' => $course->id,
            'course_code' => $course->code,
            'course_title' => $course->title,
            'dispute_id' => $this->dispute->id,
            'status' => $this->dispute->status->value,
            'resolution_notes' => $this->dispute->resolution_notes,
        ];
    }
}
```

- [ ] **Step 5: Create the mail view**

`resources/views/mail/result-dispute-reviewed.blade.php`:

```blade
<x-mail::message>
# Dispute reviewed

Hi {{ $notifiable->name ?? 'there' }},

Your dispute for **{{ $course->code }} — {{ $course->title }}** has been **{{ $status }}**.

@if ($resolutionNotes)
Notes from the reviewer:

> {{ $resolutionNotes }}
@endif

<x-mail::button :url="$url">
View my results
</x-mail::button>

Thanks,<br>
The {{ config('app.name') }} team
</x-mail::message>
```

- [ ] **Step 6: Create the listener**

`app/Listeners/SendResultDisputeReviewedNotification.php`:

```php
<?php

namespace App\Listeners;

use App\Events\ResultDisputeReviewed;
use App\Notifications\ResultDisputeReviewedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendResultDisputeReviewedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Notify the disputing student their dispute reached a terminal outcome.
     * Fires once per terminal review; the interim UnderReview move emits no event.
     */
    public function handle(ResultDisputeReviewed $event): void
    {
        $user = $event->dispute->studentProfile?->user;

        if ($user === null) {
            return;
        }

        $user->notify(new ResultDisputeReviewedNotification($event->dispute));
    }
}
```

- [ ] **Step 7: Dispatch the event from the review action**

Modify `app/Actions/ReviewResultDispute.php`. Add the import and dispatch after commit **inside the existing terminal branch** that records the audit:

```php
use App\Events\ResultDisputeReviewed;
```

```php
            if ($status->isTerminal()) {
                AuditLog::record(
                    AuditAction::DisputeResolved,
                    $dispute,
                    ['status' => $status->value],
                    userId: $reviewer->id,
                );

                DB::afterCommit(function () use ($dispute): void {
                    event(new ResultDisputeReviewed($dispute));
                });
            }

            return $dispute;
```

- [ ] **Step 8: Run the test to verify it passes**

Run: `php artisan test --compact --filter=ResultDisputeReviewedNotificationTest`
Expected: PASS (3 tests).

- [ ] **Step 9: Format**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 10: Commit**

```bash
git add app/Events/ResultDisputeReviewed.php app/Notifications/ResultDisputeReviewedNotification.php resources/views/mail/result-dispute-reviewed.blade.php app/Listeners/SendResultDisputeReviewedNotification.php app/Actions/ReviewResultDispute.php tests/Feature/Courses/ResultDisputeReviewedNotificationTest.php
git commit -m "feat(results): notify students when their dispute is resolved (#81)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 3: Render the new notification types in the student feed

**Files:**
- Modify: `resources/js/pages/dashboards/Student.vue`

**Interfaces:**
- Consumes: the two new `toArray` payloads — `{ type: 'course_results_published', course_id, course_code, course_title }` and `{ type: 'result_dispute_reviewed', course_id, course_code, course_title, dispute_id, status, resolution_notes }`.

The feed currently type-switches inline on `item.data.type` for `'cancelled'`/`'rescheduled'`. Widen the payload type to a discriminated union and move the per-type display logic into three script helpers (`notificationTitle`, `notificationIcon`, `notificationDetail`) so the template stays type-safe across the union.

- [ ] **Step 1: Widen the notification data type**

In `resources/js/pages/dashboards/Student.vue`, replace the `NotificationData` type (currently the flat session shape) with a discriminated union:

```ts
type SessionNotificationData = {
    type: 'cancelled' | 'rescheduled';
    course_id: number;
    course_code: string;
    course_title: string;
    session_id: number;
    scheduled_for: string;
    reason: string | null;
    previous_scheduled_for: string | null;
};

type ResultsPublishedNotificationData = {
    type: 'course_results_published';
    course_id: number;
    course_code: string;
    course_title: string;
};

type DisputeReviewedNotificationData = {
    type: 'result_dispute_reviewed';
    course_id: number;
    course_code: string;
    course_title: string;
    dispute_id: number;
    status: string;
    resolution_notes: string | null;
};

type NotificationData =
    | SessionNotificationData
    | ResultsPublishedNotificationData
    | DisputeReviewedNotificationData;
```

- [ ] **Step 2: Add the two icons to the lucide import**

In the `lucide-vue-next` import block, add `ClipboardCheck` and `Scale` (keep alphabetical ordering):

```ts
import {
    Bell,
    BookOpen,
    CalendarCheck,
    CalendarClock,
    CalendarX,
    Check,
    ChevronRight,
    ClipboardCheck,
    ClipboardList,
    FileText,
    GraduationCap,
    Scale,
    Wallet,
} from 'lucide-vue-next';
```

Also add a `Component` type import from Vue (extend the existing `vue` import):

```ts
import { computed, type Component } from 'vue';
```

- [ ] **Step 3: Replace `notificationTitle` and add `notificationIcon` + `notificationDetail`**

Replace the existing `notificationTitle` function with these three helpers:

```ts
function notificationTitle(item: AppNotification): string {
    const data = item.data;

    switch (data.type) {
        case 'cancelled':
            return `${data.course_code} session cancelled`;
        case 'rescheduled':
            return `${data.course_code} session rescheduled`;
        case 'course_results_published':
            return `${data.course_code} results published`;
        case 'result_dispute_reviewed':
            return `${data.course_code} dispute ${data.status}`;
    }
}

function notificationIcon(item: AppNotification): Component {
    switch (item.data.type) {
        case 'cancelled':
            return CalendarX;
        case 'rescheduled':
            return CalendarClock;
        case 'course_results_published':
            return ClipboardCheck;
        case 'result_dispute_reviewed':
            return Scale;
    }
}

function notificationDetail(item: AppNotification): string | null {
    const data = item.data;

    switch (data.type) {
        case 'cancelled':
            return data.reason
                ? `${formatDateTime(data.scheduled_for)} · ${data.reason}`
                : formatDateTime(data.scheduled_for);
        case 'rescheduled':
            return `Moved from ${formatDateTime(data.previous_scheduled_for)} to ${formatDateTime(data.scheduled_for)}`;
        case 'course_results_published':
            return 'View them in My results.';
        case 'result_dispute_reviewed':
            return data.resolution_notes;
    }
}
```

- [ ] **Step 4: Simplify the notification `<li>` to use the helpers**

Replace the icon `<span>` and the two type-switched `<p>` detail blocks in the notification list item with the helper-driven version. The `<li>` body becomes:

```html
<span class="mt-0.5 shrink-0 text-muted-foreground">
    <component :is="notificationIcon(item)" class="size-5" />
</span>
<div class="min-w-0 flex-1 space-y-0.5">
    <div class="flex items-center gap-2">
        <span
            v-if="!item.read_at"
            class="size-2 shrink-0 rounded-full bg-primary"
            aria-hidden="true"
        />
        <p class="text-sm font-medium">
            {{ notificationTitle(item) }}
        </p>
    </div>
    <p
        v-if="notificationDetail(item)"
        class="text-xs text-muted-foreground"
    >
        {{ notificationDetail(item) }}
    </p>
    <p class="text-xs text-muted-foreground">
        {{ formatDateTime(item.created_at) }}
    </p>
</div>
```

Leave the surrounding `<li>` (the `@click="markRead(item)"`, the `:class` read/unread styling) and the trailing "Mark read" `<Button>` unchanged.

- [ ] **Step 5: Run the frontend gate**

Run: `npm run build && npm run types:check && npm run lint:check`
Expected: all pass; no chunk-size warning.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/dashboards/Student.vue
git commit -m "feat(student): render results-published and dispute-reviewed notifications (#81)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 4: Full quality gate

**Files:** none — verification only.

- [ ] **Step 1: Run the complete gate**

```bash
vendor/bin/pint --format agent
php artisan test --compact --testsuite=Unit,Feature
npm run build
npm run types:check
npm run lint:check
```

Expected: everything green; suite ≈ 728+ tests (the prior 723 plus the 5 new notification tests); no chunk-size warning. Fix and commit anything that fails before proceeding.

---

### Task 5: Docs, context log, PR

**Files:**
- Modify: `docs/modules/notifications.md` (add both notifications to §5.1 family, §6 trigger matrix, §8 tests)
- Modify: `docs/modules/course-management.md` (publish + dispute-resolve now notify the student)
- Modify: `docs/guides/student.md` (students are now notified on results-publish + dispute resolution)
- Modify: `plan/context.md` (new § entry)

- [ ] **Step 1: Run the docs-refresh skill** scoped to this feature. Verify every claim against the shipped code, not this plan. Key facts to record:
  - Two new queued Notifications (`CourseResultsPublishedNotification`, `ResultDisputeReviewedNotification`), each on the #12 Event→queued-Listener→`Notification::send` chain, `via` mail+database.
  - Triggers: `PublishCourseResults` (afterCommit, notifies only just-published students) and `ReviewResultDispute` (afterCommit, terminal outcomes only).
  - No new routes, schema, `AuditAction`, or ADR.
  - `docs/modules/notifications.md`: these are **in-app + email** Notification classes (the #12 family, §5.1 / §7), **not** the §5.2 email-only Mailables — do not add them to the "transactional Mailables" table/count.

- [ ] **Step 2: Append the `plan/context.md` § entry** (next number after §27) recording: issue #81, branch, per-task commits, gate result, docs touched, and the out-of-scope list from the spec.

- [ ] **Step 3: Commit docs, push, open the PR**

```bash
git add -A
git commit -m "docs: sync notifications + course-management + student guide for #81

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
git push -u origin feat/results-notifications
gh pr create --base main --title "feat(results): notify students on results-publish and dispute-resolve (#81)" --body "Closes #81. <summary of shipped behaviour + gate results>

🤖 Generated with [Claude Code](https://claude.com/claude-code)"
```

Expected: PR opens; wait for all 4 CI checks (ci 8.4 / ci 8.5 / quality / browser) before merging (squash + delete branch), then fast-forward local `main`.

---

## Self-review notes (already applied)

- **Spec coverage:** every spec section maps to a task — publish vertical (T1), dispute vertical (T2), frontend render (T3, the §3.3 workstream), gate (T4), docs/context/PR (T5). No new routes/schema/ADR, so no task for those.
- **Recipients precision:** T1 captures `student_profile_id`s at publish time (Option A) — the re-publish test proves already-published students aren't re-notified. T2 fires only inside the terminal `if` branch — the under-review test proves no email on interim moves.
- **Type consistency:** the `toArray` `type` strings (`'course_results_published'`, `'result_dispute_reviewed'`) match the frontend union members in T3 exactly; the notification constructors (`CourseResultsPublishedNotification(Course)`, `ResultDisputeReviewedNotification(ResultDispute)`) match their listener call sites.
- **Pattern fidelity:** both notifications/listeners `implements ShouldQueue`, `via` mail+database, auto-discovered — identical to #12; tests use `Notification::fake()` + `assertSentTo`/`assertNothingSent`, mirroring `CourseSessionNotificationTest`.
- **Nudge-only:** no `ca_score`/`exam_score`/`final_score`/`grade` in any `toMail`, `toArray`, or blade.
