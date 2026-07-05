# Design — Results & dispute notifications (#81)

- **Issue:** #81 — Notify students when results are published and when a dispute is resolved
- **Date:** 2026-07-05
- **Size:** S (mirror of the shipped #12 notification pattern)
- **Status:** Approved (owner), pending implementation plan

## 1. Problem

The results lifecycle is silent. When an SAO publishes a course's results
(`App\Actions\Sao\PublishCourseResults`) or resolves a student's result dispute
(`App\Actions\ReviewResultDispute`), the affected student is never told — they must keep
reopening **My results** to notice a change. This was a conscious C4 deferral
("optional queued mail on publish/dispute-resolve — include if cheap") that was omitted at
build time and never tracked, surfaced by the 2026-07-03 omissions audit (`plan/context.md`
§24, finding 2).

## 2. Goals / non-goals

**Goals**
- Notify a student when *their* course results become available.
- Notify a student when *their* dispute reaches a terminal outcome (Resolved or Rejected).
- Reuse the existing #12 notification machinery (mail + in-app database, queued) and the
  existing student notifications feed + read/read-all endpoints.

**Non-goals (out of scope)**
- No scores or grades in any message — nudge-only (privacy + no staleness if a later dispute
  changes a mark).
- No notification on the interim `UnderReview` dispute status.
- No lecturer- or SAO-facing notifications.
- No changes to how results or disputes are computed, and no new routes, schema, or
  `AuditAction` cases (the existing `ResultsPublished` / `DisputeResolved` audit rows remain
  the forensic record; these notifications are the user-facing echo, per the notifications
  module's audit-vs-notify split).
- No new ADR — this falls under the existing ADR-0019 notification-channel strategy.

## 3. Design overview

Two queued `Notification` classes, each dispatched through the established chain (identical
to #12's `CourseSessionChangedNotification`):

```
Action (in transaction)
  └─ DB::afterCommit → event(...)
        └─ queued Listener (ShouldQueue)
              └─ Notification::send($recipients, new XNotification(...))
                    └─ Notification (ShouldQueue) via ['mail','database']
```

Wiring uses Laravel's **automatic event–listener discovery** (verified: the app registers
only auth events explicitly in `AppServiceProvider`; #12/#80 domain listeners are
auto-discovered by their `handle()` type-hint — no `EventServiceProvider` map).

### 3.1 Results published

- **Modify `App\Actions\Sao\PublishCourseResults`** — it already loads the `$results` it flips
  Draft→Published. Capture their `student_profile_id`s; after the transaction commits,
  `DB::afterCommit(fn () => event(new CourseResultsPublished($course, $studentProfileIds)))`.
  - *Why carry the ids:* by the time the queued listener runs the rows are already
    `Published`, so it cannot re-derive "just this batch." Capturing recipients at publish
    time makes **Option A** (only just-published students) precise, and a later re-publish for
    late scores then notifies only the newly-published students, with no double-notification —
    for free, because the action only ever selects `Draft` rows.
- **`App\Events\CourseResultsPublished`** — `public readonly Course $course`,
  `public readonly array $studentProfileIds` (`int[]`). `SerializesModels`.
- **`App\Listeners\SendCourseResultsPublishedNotification` (`implements ShouldQueue`)** —
  resolves recipients and fans out:
  ```php
  $users = StudentProfile::query()
      ->whereIn('id', $event->studentProfileIds)
      ->with('user:id,name,email')
      ->get()->pluck('user')->filter();
  if ($users->isEmpty()) { return; }
  Notification::send($users, new CourseResultsPublishedNotification($event->course));
  ```
- **`App\Notifications\CourseResultsPublishedNotification` (`ShouldQueue`, `via` mail+database)**
  - `toMail`: subject `"{app} — {code} results published"`; body: "Your results for
    {code} — {title} are now available. View them in My results." → button to
    `route('student.results.index')`.
  - `toArray`: `{ type: 'course_results_published', course_id, course_code, course_title }`.

### 3.2 Dispute reviewed

- **Modify `App\Actions\ReviewResultDispute`** — inside the existing `if ($status->isTerminal())`
  branch (which already writes the `DisputeResolved` audit), after commit
  `event(new ResultDisputeReviewed($dispute))`. **Fires only on Resolved/Rejected**, never on
  `UnderReview`.
- **`App\Events\ResultDisputeReviewed`** — `public readonly ResultDispute $dispute`
  (`student_profile_id`, `status`, `resolution_notes`, and `courseResult.course` for the
  code/title). `SerializesModels`.
- **`App\Listeners\SendResultDisputeReviewedNotification` (`implements ShouldQueue`)** —
  resolves the single disputing student's `User`
  (`$event->dispute->studentProfile->user`) and, if present, notifies them.
- **`App\Notifications\ResultDisputeReviewedNotification` (`ShouldQueue`, `via` mail+database)**
  - `toMail`: subject `"{app} — dispute reviewed"`; body: "Your dispute for {code} — {title}
    was **{resolved|rejected}**." + the resolution notes (if any) + "View your results in My
    results." → button to `route('student.results.index')`. Outcome + notes, **no score**.
  - `toArray`: `{ type: 'result_dispute_reviewed', course_id, course_code, course_title,
    dispute_id, status, resolution_notes }`.

### 3.3 Frontend — render the two new types in the student feed

The student notifications feed lives in `resources/js/pages/dashboards/Student.vue` and is
**type-switched** on `item.data.type` (`notificationTitle()`, the icon, and the body line
currently handle only `cancelled`/`rescheduled`). The two new `type` values therefore need
render cases:

- Widen the notification-item `data` type (the `AppNotification` shape used in this file) to a
  discriminated union covering the new payloads, so `types:check` stays green.
- `notificationTitle()`: add cases → "{code} results published" and "{code} dispute
  {resolved|rejected}".
- Icon: a sensible lucide icon per new type (e.g. `ClipboardCheck` for results, `Scale` /
  `Gavel` for dispute) alongside the existing `CalendarX`/`CalendarClock`.
- Body line: results → none (title suffices) or "View in My results"; dispute → the
  `resolution_notes` when present.
- Keep the existing **click = mark read** behavior (rows don't navigate today); the "view in
  My results" call to action lives in the email and the dashboard's Results quick-link. No new
  navigation is added, for consistency with the shipped session-change rows.

No change to `Student\NotificationController`, the `notifications/{id}/read` /
`read-all` routes, or the `notifications` table — the feed already loads and renders whatever
`toArray` rows exist.

## 4. Error handling / correctness

- `DB::afterCommit` on both paths → a rolled-back publish or resolution never notifies.
- Listeners guard an empty recipient set (`->filter()` + early return), mirroring #12.
- Sending is queued and **best-effort**: a mail/queue failure does not unwind the committed
  publish or resolution; the audit row is the durable record.
- Both listeners and both notifications are `ShouldQueue`, so recipient resolution and per-user
  sends run off the request thread (same fan-out as #12).

## 5. Tests

Mirror `tests/Feature/Courses/CourseSessionNotificationTest.php`, using `Notification::fake()`
to assert channels + recipients, plus a synchronous path to assert the in-app row persists.

- **Publish** (`CourseResultsPublishedNotificationTest`):
  - notifies exactly the just-published students on both `mail` and `database`;
  - does **not** notify cohort students who had no fully-scored (Draft, both-scores) result;
  - a re-publish for newly-scored students notifies only those, not the already-published;
  - the `ResultsPublished` audit row is still written;
  - (the "nothing to publish" path already throws before any dispatch — assert no notification).
- **Dispute** (`ResultDisputeReviewedNotificationTest`):
  - notifies the disputing student on **Resolved** and on **Rejected** (both channels);
  - sends **nothing** on **UnderReview**;
  - the in-app payload carries `status` + `resolution_notes`;
  - no other student is notified.

## 6. File map

**New**
- `app/Events/CourseResultsPublished.php`
- `app/Events/ResultDisputeReviewed.php`
- `app/Listeners/SendCourseResultsPublishedNotification.php`
- `app/Listeners/SendResultDisputeReviewedNotification.php`
- `app/Notifications/CourseResultsPublishedNotification.php`
- `app/Notifications/ResultDisputeReviewedNotification.php`
- `resources/views/mail/course-results-published.blade.php`
- `resources/views/mail/result-dispute-reviewed.blade.php`
- `tests/Feature/Courses/CourseResultsPublishedNotificationTest.php`
- `tests/Feature/Courses/ResultDisputeReviewedNotificationTest.php`

**Modify**
- `app/Actions/Sao/PublishCourseResults.php` — capture published `student_profile_id`s +
  `afterCommit` event dispatch.
- `app/Actions/ReviewResultDispute.php` — `afterCommit` event dispatch inside the terminal
  branch.
- `resources/js/pages/dashboards/Student.vue` — render cases + widened item-`data` type for the
  two new notification types.

## 7. Docs (when it ships)

- `docs/modules/notifications.md` — add both notifications to the trigger matrix (§6), the
  notification family (§5.1), and the tests table (§8).
- `docs/modules/course-management.md` — publish/dispute-resolve now notify the student.
- `docs/guides/student.md` — students now receive these notifications.
- No routes/data-model/ADR changes.
