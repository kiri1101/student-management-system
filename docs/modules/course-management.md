# Course management

How SchuLyf runs the academic side of a course from catalogue to grade: SAO publishes a course and
assigns a lecturer, the lecturer drafts a plan for SAO approval, then — on an approved plan — schedules
class sessions, marks attendance, sets and grades assignments, and records CA + exam marks. SAO
publishes the marks; students view their published results and may dispute them; SAO/Admin resolve the
disputes. It is the largest module in the system (GitHub #11), built across phases C0–C4.

> Cross-references: [architecture.md](../architecture.md) (request lifecycle, the shared inline-viewer
> foundation), [data-model.md](../data-model.md) (column detail), [routes.md](../routes.md) (the route
> split), [security.md](../security.md) (gates, audit log, file-viewer hardening),
> [testing.md](../testing.md) (suite layout), [modules/notifications.md](notifications.md)
> (session-change notifications), [modules/admissions.md](admissions.md) (a cohort = a program offering
> + level + academic year).

---

## 1. The implicit-cohort rule (read this first)

**There is no enrollment table and no enrollment flow.** Cohort membership is *derived*: a student
belongs to a course when their `StudentProfile` matches the course on **three columns** and is active.
`Course::cohortStudents()` is the single source of truth:

```
StudentProfile::query()
    ->where('program_offering_id', $this->program_offering_id)
    ->where('level', $this->level)
    ->where('academic_year', $this->academic_year)
    ->where('status', StudentStatus::Active);
```

Every cohort-scoped surface re-derives this same tuple — the lecturer attendance grid, results sheet,
and the four student read screens (`CourseController`, `AttendanceController`, `AssignmentController`,
`CourseResultController`) inline the exact `program_offering_id` + `level` + `academic_year` filter and
additionally require `plan_status = approved`. The write actions (`MarkAttendance`,
`RecordCourseResults`) defence-in-depth by skipping any `student_profile_id` not returned by
`cohortStudents()`. A student who changes level or academic year silently leaves one cohort and joins
another; nothing is migrated.

> A program offering + level (+ academic year) *is* the cohort — see
> [modules/admissions.md](admissions.md) for how a student acquires those values at admission.

---

## 2. Roles & abilities

| Action | Role | Guard (named) |
|---|---|---|
| Create course, edit, assign lecturer | SAO, Admin | `routes/sao.php` group `role:sao,admin` |
| Approve / reject a course plan | SAO, Admin | `Gate::authorize('approve-course-plan')` |
| Publish a course's results | SAO, Admin | `Gate::authorize('publish-results')` |
| Resolve a result dispute | SAO, Admin | `routes/sao.php` group `role:sao,admin` (no per-action gate) |
| Edit own course plan, submit for approval | Lecturer | `role:lecturer` + per-course ownership (`lecturer_profile_id`) |
| Schedule/update/cancel sessions; create/grade assignments; record marks | Lecturer | `role:lecturer` + ownership |
| Mark attendance | Lecturer, Admin | `Gate::authorize('mark-attendance')` + ownership |
| View own courses / attendance / assignments / published results; submit assignment; raise dispute | Student | `role:student,admin` + cohort match |
| View any submission file inline / download | submitting student, course lecturer, Admin | per-resource check in the view/download controllers |

The three gates (`approve-course-plan`, `mark-attendance`, `publish-results`) are defined from the
single `ABILITIES` map in `AppServiceProvider::configureGates()`; Admin is on each by design. See
[security.md](../security.md) §2 for the gate machinery and the role middleware.

**Ownership** is not a gate: lecturer controllers call a private `authorizeOwnership()` that aborts
`403` unless `course.lecturer_profile_id === $request->user()->lecturerProfile->id`. Dispute
*resolution* has **no lecturer surface** — the only resolve route lives in `routes/sao.php` behind
`role:sao,admin`.

---

## 3. Data model

Seven owned models, all `use RecordsAudit, SoftDeletes`. Column detail lives in
[data-model.md](../data-model.md); the relations a contributor must know:

| Model | Key columns | Key relations / notes |
|---|---|---|
| `Course` | `program_offering_id`, `level`, `academic_year`, `code`, `title`, `credits`, `semester`, `lecturer_profile_id?`, `plan_status`, `plan_submitted_at?`, `plan_reviewed_at?`, `plan_reviewed_by?`, `plan_review_notes?` | `programOffering()` (`withTrashed`, AUD-013), `lecturer()`→`LecturerProfile`, `planReviewer()`→`User`, `sessions()`, `assignments()`, `results()`, **`cohortStudents()`** (§1). Unique `(program_offering_id, level, academic_year, code)`. |
| `CourseSession` | `course_id`, `scheduled_for`, `topic`, `duration_minutes`, `status`, `cancellation_reason?` | `course()`, `attendanceRecords()`. Never hard-deleted — cancel flips `status`. |
| `AttendanceRecord` | `course_session_id`, `student_profile_id`, `status`, `marked_by`, `marked_at` | `session()`, `studentProfile()`, `markedBy()`. Unique `(course_session_id, student_profile_id)`. |
| `Assignment` | `course_id`, `title`, `instructions`, `due_at`, `max_score`, `created_by` | `course()`, `creator()`, `submissions()`. |
| `AssignmentSubmission` | `assignment_id`, `student_profile_id`, `file_path`, `original_filename`, `mime_type`, `size_bytes`, `submitted_at`, `is_late`, `score?`, `feedback?`, `graded_by?`, `graded_at?`, `status` | `assignment()`, `studentProfile()`, `gradedBy()`. Unique `(assignment_id, student_profile_id)` — one row per student, replaced on resubmit. File on the **default (private) disk**. |
| `CourseResult` | `course_id`, `student_profile_id`, `ca_score?`, `exam_score?`, `status`, `published_at?`, `published_by?` | `course()`, `studentProfile()`, `publisher()`, `disputes()`. Unique `(course_id, student_profile_id)`. Computed `final_score` + `grade` accessors — see §3.1. |
| `ResultDispute` | `course_result_id`, `student_profile_id`, `reason`, `status`, `resolution_notes?`, `reviewed_by?`, `reviewed_at?` | `courseResult()`, `studentProfile()`, `reviewer()`. |

### 3.1 Final score & grade (computed, no accessor recursion)

`CourseResult` exposes `final_score` and `grade` as read accessors. Both delegate to one **private**
`computedFinalScore()` so neither accessor calls the other (no recursion):

```
final_score = (int) round(ca_score * CA_WEIGHT + exam_score * EXAM_WEIGHT)   // CA_WEIGHT 0.3, EXAM_WEIGHT 0.7
```

Both are `null` until **both** marks are present. The letter grade:
`A` ≥ 80, `B` ≥ 70, `C` ≥ 60, `D` ≥ 50, otherwise `F`. The weights are public constants
`CourseResult::CA_WEIGHT` / `EXAM_WEIGHT` — change them in one place.

### 3.2 Status enums — lowercase `->value`

Every status enum is string-backed with **TitleCase keys but lowercase values**, and the Vue pages
compare against the **lowercase** value (e.g. `plan_status === 'approved'`, `data.status ===
'published'`). Keep new comparisons lowercase.

| Enum | Cases (value) | Terminal |
|---|---|---|
| `CoursePlanStatus` | `draft`, `submitted`, `approved`, `rejected` | `approved` only (`isTerminal()`) — rejected returns to lecturer |
| `SessionStatus` | `scheduled`, `held`, `cancelled` | — |
| `AttendanceStatus` | `present`, `absent`, `late`, `excused` | — |
| `AssignmentSubmissionStatus` | `submitted`, `graded` | — |
| `ResultStatus` | `draft`, `published` | — |
| `DisputeStatus` | `open`, `under_review`, `resolved`, `rejected` | `resolved` + `rejected` (`isTerminal()`) |

---

## 4. Routes & screens

Grouped by role. SAO routes are in `routes/sao.php` (`role:sao,admin`); lecturer in `routes/lecturer.php`
(`role:lecturer`, sessions/assignments/results sub-routes use `scopeBindings()` so a `{session}` /
`{assignment}` / `{submission}` is only reachable through its owning `{course}`); student in
`routes/student.php` (`role:student,admin`); the file routes are in `routes/web.php`.

### SAO / Admin — `sao.courses.*`, `sao.disputes.*`
| Method · URI | Name | Renders / does |
|---|---|---|
| GET `sao/courses` | `sao.courses.index` | `sao/courses/Index.vue` — catalogue (filter by year + plan status) |
| GET `sao/courses/create` · POST `sao/courses` | `sao.courses.create` / `.store` | `sao/courses/Form.vue` — create |
| GET `sao/courses/{course}/edit` · PATCH `sao/courses/{course}` | `sao.courses.edit` / `.update` | `sao/courses/Form.vue` — edit |
| POST `sao/courses/{course}/assign-lecturer` | `sao.courses.assignLecturer` | assign a `LecturerProfile` |
| POST `sao/courses/{course}/approve` · `.../reject` | `sao.courses.approve` / `.reject` | plan review (§5.1) |
| POST `sao/courses/{course}/publish-results` | `sao.courses.publishResults` | publish (§5.4) |
| GET `sao/disputes` · POST `sao/disputes/{dispute}/review` | `sao.disputes.index` / `.review` | `sao/disputes/Index.vue` — dispute queue + resolve (§5.5) |

### Lecturer — `lecturer.courses.*`
| Method · URI | Name | Renders / does |
|---|---|---|
| GET `lecturer/courses` | `lecturer.courses.index` | `lecturer/courses/Index.vue` — own assigned courses |
| GET `lecturer/courses/{course}/edit` · PATCH `.../plan` · POST `.../submit` | `.edit` / `.update` / `.submit` | `lecturer/courses/Plan.vue` — edit the course plan (the `description` field, labelled "Course plan"), submit for approval |
| GET/POST `.../sessions`, PATCH/DELETE `.../sessions/{session}` | `.sessions.index/store/update/destroy` | `lecturer/courses/Sessions.vue` |
| GET/POST `.../sessions/{session}/attendance` | `.sessions.attendance` / `.markAttendance` | `lecturer/courses/Attendance.vue` — cohort grid |
| GET/POST `.../assignments`, PATCH/DELETE `.../assignments/{assignment}` | `.assignments.index/store/update/destroy` | `lecturer/courses/Assignments.vue` |
| GET `.../assignments/{assignment}/submissions` · POST `.../{submission}/grade` | `.assignments.submissions` / `.grade` | `lecturer/courses/Submissions.vue` — grade (with file viewer) |
| GET/POST `.../results` | `.results.index` / `.store` | `lecturer/courses/Results.vue` — marks sheet |

### Student — `student.*`
| Method · URI | Name | Renders / does |
|---|---|---|
| GET `student/courses` | `student.courses.index` | `student/courses/Index.vue` — approved cohort courses grouped per semester (credits, lecturer, session/assignment counts) |
| GET `student/attendance` | `student.attendance.index` | `student/Attendance.vue` — own marks only |
| GET `student/assignments` · POST `student/assignments/{assignment}/submit` | `student.assignments.index` / `.submit` | `student/assignments/Index.vue` |
| GET `student/results` · POST `student/results/{result}/dispute` | `student.results.index` / `.dispute` | `student/results/Index.vue` — **published only** + dispute dialog |

### File routes — `routes/web.php` (`auth`, `verified`, `throttle:lookups`)
| Method · URI | Name | Controller |
|---|---|---|
| GET `assignment-submissions/{submission}` | `assignments.submission.download` | `SubmissionDownloadController` (attachment) |
| GET `assignment-submissions/{submission}/view` | `assignments.submission.view` | `SubmissionViewController` (inline, §6) |

---

## 5. Flows

### 5.1 Course plan lifecycle

```mermaid
stateDiagram-v2
    [*] --> draft: SAO creates course
    draft --> submitted: lecturer submits (CourseController::submit)
    rejected --> submitted: lecturer resubmits
    submitted --> approved: ReviewCoursePlanApproval::approve
    submitted --> rejected: ReviewCoursePlanApproval::reject (with notes)
    approved --> [*]: terminal — locked
```

- **Submit** (`Lecturer\CourseController::submit`) — ownership-guarded; only a `draft` or `rejected`
  plan is submittable (`SUBMITTABLE` const), otherwise a toast error and no write. Clears
  `plan_review_notes`, stamps `plan_submitted_at`, audits `CoursePlanSubmitted`.
- **Approve / reject** (`App\Actions\Sao\ReviewCoursePlanApproval`) — runs inside `DB::transaction`;
  `lockAndGuard()` re-fetches the course `lockForUpdate()` and throws `ValidationException` unless
  `plan_status === submitted` (the terminal/concurrency re-guard). `saveQuietly()` + audit
  `CoursePlanApproved` / `CoursePlanRejected`. Rejection persists `notes` and returns the plan to the
  lecturer. **Rejected courses sit at the bottom of the cycle** and can be resubmitted; an approved
  plan is terminal and never re-reviewed.
- **Rejected path (concurrent double-decide):** a second approver hits `lockAndGuard`, finds
  `plan_status` already `approved`/`rejected`, and is refused with *"This course plan is not awaiting
  review."*

**Approved is the unlock for everything downstream.** Session/assignment/result controllers each call a
private `guardApproved()` that aborts `403` unless `plan_status === approved`; the student read screens
filter on `plan_status = approved`.

### 5.2 Sessions & attendance

```mermaid
sequenceDiagram
    actor L as Lecturer
    participant SC as CourseSessionController
    participant MA as MarkAttendance
    L->>SC: POST sessions (approved course)
    SC-->>L: session (status=scheduled), audit CourseSessionScheduled
    L->>SC: POST .../attendance {statuses}
    SC->>SC: authorizeOwnership + Gate::authorize('mark-attendance')
    SC->>MA: mark(session, statuses, marker)
    MA->>MA: lockForUpdate session; re-guard course approved
    MA->>MA: cohortStudents filter — skip non-cohort ids
    MA-->>L: upsert AttendanceRecord per cohort student, audit AttendanceMarked {count}
```

- **Create / update / cancel** session — `CourseSessionController`; ownership + `guardApproved` on
  create/update. Cancel (`destroy`) **soft-cancels** by flipping `status` to `cancelled` (sessions are
  never hard-deleted) and stores `cancellation_reason`. Audits `CourseSessionScheduled` /
  `CourseSessionRescheduled` / `CourseSessionCancelled`.
- **Reschedule / cancel notification gate:** only a *genuine time move* on a still-`scheduled`,
  *future* session counts as a reschedule worth notifying; cancelling only notifies a future scheduled
  session. Topic/duration-only edits, and changes to past or already-cancelled sessions, stay silent.
  When it does fire, the controller `CourseSessionChanged::dispatch(...)` — see §7 and
  [modules/notifications.md](notifications.md).
- **Mark attendance** (`App\Actions\Lecturer\MarkAttendance`) — transaction; re-fetch session
  `lockForUpdate`; re-guard `course.plan_status === approved`; **only cohort `student_profile_id`s are
  applied, others in the payload are silently skipped**; `updateOrCreate` per student (idempotent
  upsert keyed on `(course_session_id, student_profile_id)`); one audit `AttendanceMarked` with the
  count written.

### 5.3 Assignments (upload + grade)

```mermaid
stateDiagram-v2
    [*] --> submitted: SubmitAssignment::submit (student, cohort, before/after due → is_late)
    submitted --> submitted: resubmit (replaces file + resets grade)
    submitted --> graded: GradeSubmission::grade (lecturer, score ≤ max_score)
```

- **Submit** (`App\Actions\Student\SubmitAssignment`) — the controller first aborts `403` unless the
  course is approved **and** the student's cohort tuple matches the course (no peer can submit). The
  action: computes `is_late` (`now() > due_at`); writes the new file to the private disk **before** any
  DB write (AUD-009); `updateOrCreate` on `(assignment_id, student_profile_id)` resetting all grading
  metadata to null/`submitted`; on success deletes the old file **out of transaction**; on failure
  deletes the just-written file and rethrows. Audits `AssignmentSubmitted` with `is_late`. Upload is
  validated by `StoreAssignmentSubmissionRequest` — `mimes:pdf,jpg,jpeg,png`, `max:8192` KB.
- **Grade** (`App\Actions\Lecturer\GradeSubmission`) — ownership-guarded; controller aborts `404` if
  the submission doesn't belong to the route's assignment. Score bound is enforced by
  `GradeSubmissionRequest` (`max:` the assignment's `max_score`). Action re-fetches the submission
  `lockForUpdate`, stamps score/feedback/grader/`graded_at`, sets `status=graded`, audits
  `AssignmentGraded` with the score.

### 5.4 Results: record → publish

```mermaid
sequenceDiagram
    actor L as Lecturer
    actor S as SAO/Admin
    participant RR as RecordCourseResults
    participant PR as PublishCourseResults
    L->>RR: POST results {rows}
    RR->>RR: per row — skip non-cohort; skip if already Published
    RR-->>L: upsert draft CourseResult, audit ResultRecorded {count} (if count>0)
    S->>PR: POST publish-results (Gate publish-results)
    PR->>PR: select status=draft AND ca_score NOT NULL AND exam_score NOT NULL, lockForUpdate
    PR-->>S: mark each Published, audit ResultsPublished {count}
```

- **Record** (`App\Actions\Lecturer\RecordCourseResults`) — transaction; for each row, skip students
  not in `cohortStudents()`, and skip any row whose existing `CourseResult` is already `published`
  (published marks are locked). `updateOrCreate` keyed on `(course_id, student_profile_id)`, status
  forced to `draft`. Audits `ResultRecorded` **only if** ≥1 row written.
- **Publish** (`App\Actions\Sao\PublishCourseResults`) — `publish-results` gate; transaction. **Selects
  only fully-scored drafts** (`status=draft AND ca_score IS NOT NULL AND exam_score IS NOT NULL`) under
  `lockForUpdate`; throws `ValidationException` *"There are no fully-scored draft results…"* if none.
  Partial drafts are left untouched. Stamps each Published with publisher + timestamp; audits
  `ResultsPublished` with the count. The lecturer's marks sheet surfaces a `publishable_count` so SAO
  knows what will publish.

### 5.5 Disputes

```mermaid
stateDiagram-v2
    [*] --> open: student raises (on a published result they own)
    open --> under_review: ReviewResultDispute::review (UnderReview)
    open --> resolved: review (Resolved, terminal)
    open --> rejected: review (Rejected, terminal)
    under_review --> resolved: review
    under_review --> rejected: review
    resolved --> [*]
    rejected --> [*]
```

- **Raise** (`Student\CourseResultController::dispute`) — aborts `403` unless the result belongs to the
  caller's profile **and** `status === published`. Refuses with a validation error if an `open` **or**
  `under_review` dispute already exists for that result (one live dispute per result). Creates
  `status=open`, audits `DisputeRaised`.
- **Resolve** (`App\Actions\ReviewResultDispute`, namespace `App\Actions` — **SAO/Admin only**, no
  lecturer surface) — `ReviewResultDisputeRequest` accepts only `under_review` / `resolved` /
  `rejected`. Transaction; re-fetch `lockForUpdate`; throws if the dispute is already terminal
  (`isTerminal()` = resolved|rejected). A terminal outcome stamps `reviewer` + `reviewed_at` + notes
  and audits `DisputeResolved`; moving to `under_review` only records the (optional) notes and writes
  **no** audit row.

---

## 6. The shared inline file viewer (C0)

Assignment submission files reuse the shared inline-viewer foundation (the same pattern as application
documents and payment slips — see [security.md](../security.md) §5). Two single-action controllers serve
the **same** private-disk file: `SubmissionDownloadController` (attachment) and `SubmissionViewController`
(inline). `SubmissionViewController` enforces, in order:

1. **Authorization** — owner (submitting student) **or** the course's lecturer **or** Admin, else `403`.
2. **Stored-MIME allowlist** — `INLINE_SAFE_MIMES = ['application/pdf', 'image/png', 'image/jpeg']`;
   anything else is refused **`415`** (mirrors the upload allowlist), so an unexpectedly-stored type can
   never execute as script/markup against the viewer's session.
3. **Forced `Content-Type`** — the stored, validated MIME sent verbatim (no sniffing).
4. **`X-Content-Type-Options: nosniff`**.
5. **Sandbox CSP** — `default-src 'none'; sandbox; img-src 'self'; object-src 'self'`.
6. **Header-safe filename** — `"`, `\r`, `\n` stripped before `Content-Disposition: inline`.

The Vue side is `resources/js/components/FileViewerDialog.vue` (PrimeVue `Dialog`, maximizable): PDFs
render in an `<iframe>`, images in `<img>`, anything else shows a Download fallback; the footer offers
"Open in new tab" + "Download". The lecturer grading screen (`Submissions.vue`) passes the controller's
`view_url` / `download_url` into it.

---

## 7. Side effects

### 7.1 Audit (`AuditAction`, via `AuditLog::record` or the `RecordsAudit` trait)

| When | Action | Recorded by |
|---|---|---|
| Course created | `CourseCreated` | `Sao\CourseController::store` |
| Lecturer assigned | `LecturerAssigned` | `Sao\CourseController::assignLecturer` |
| Plan submitted | `CoursePlanSubmitted` | `Lecturer\CourseController::submit` |
| Plan approved / rejected | `CoursePlanApproved` / `CoursePlanRejected` | `ReviewCoursePlanApproval` |
| Session scheduled / cancelled / rescheduled | `CourseSessionScheduled` / `CourseSessionCancelled` / `CourseSessionRescheduled` | `CourseSessionController` |
| Attendance marked | `AttendanceMarked` (`{count}`) | `MarkAttendance` |
| Assignment created | `AssignmentCreated` | `Lecturer\AssignmentController::store` |
| Submission uploaded | `AssignmentSubmitted` (`{is_late}`) | `SubmitAssignment` |
| Submission graded | `AssignmentGraded` (`{score}`) | `GradeSubmission` |
| Marks recorded | `ResultRecorded` (`{count}`, only if >0) | `RecordCourseResults` |
| Results published | `ResultsPublished` (`{count}`) | `PublishCourseResults` |
| Dispute raised | `DisputeRaised` | `Student\CourseResultController::dispute` |
| Dispute resolved/rejected | `DisputeResolved` (`{status}`) | `ReviewResultDispute` (terminal only) |

In addition, `RecordsAudit` auto-logs `Created`/`Updated`/`Deleted` lifecycle rows on every model in
this module (e.g. soft-deleting an assignment writes a `Deleted` row by itself).

### 7.2 Events & notifications

`CourseSessionController` dispatches **`App\Events\CourseSessionChanged`** on a notifiable cancel or
reschedule (see §5.2 for the exact gate). The queued listener
`App\Listeners\SendCourseSessionChangedNotification` fans the change out to the course cohort (email +
in-app). The change type is `App\Enums\SessionChangeType`. Full mechanics — channels, the notification
class, the in-app store — are documented in [modules/notifications.md](notifications.md). **No other
flow in this module sends mail or notifications** (publish/dispute-resolve mail was scoped as optional
in the plan and was not shipped — see §9).

---

## 8. Tests

All under `tests/Feature/Courses/` unless noted ([testing.md](../testing.md) covers conventions):

| File | Covers |
|---|---|
| `CourseManagementTest.php` | SAO create + uniqueness; lecturer assignment; authorization per role |
| `CoursePlanApprovalTest.php` | submit by assigned lecturer only; SAO approve/reject + `submitted` re-guard + audit |
| `CourseSessionTest.php` | session create gated on approved + ownership; cancel soft-flip |
| `CourseSessionNotificationTest.php` | reschedule/cancel notification gate (future-scheduled only) |
| `MarkAttendanceTest.php` | upsert idempotency; cohort-only application; `mark-attendance` gate |
| `StudentAttendanceTest.php` | student sees only own marks, only approved courses |
| `StudentCoursesTest.php` | semester-ordered cohort list with lecturer + counts; unapproved/out-of-cohort excluded; profile-less empty state; role gate |
| `AssignmentManagementTest.php` | assignment CRUD gated on approved + ownership |
| `AssignmentSubmissionTest.php` | upload mime/size validation; cohort-only; one-per-student replace + old-file cleanup; `is_late` |
| `GradeSubmissionTest.php` | grade ≤ `max_score`; cross-assignment `404`; audit |
| `CourseResultRecordingTest.php` | draft upsert; cohort-only; published rows skipped |
| `PublishCourseResultsTest.php` | `publish-results` gate (SAO only); fully-scored-drafts-only; partials untouched |
| `StudentResultViewTest.php` | published-only exposure; draft never leaks |
| `RaiseResultDisputeTest.php` | own + published result only; one live dispute per result |
| `ReviewResultDisputeTest.php` | SAO/Admin resolve; terminal re-guard; audit |
| `tests/Feature/Files/InlineFileViewTest.php` | inline 200 + `inline` disposition for authorized actor; `403`/`415` otherwise |

---

## 9. File map

| File | Role |
|---|---|
| `app/Models/{Course,CourseSession,AttendanceRecord,Assignment,AssignmentSubmission,CourseResult,ResultDispute}.php` | The seven owned models; `Course::cohortStudents()` is the implicit-cohort rule; `CourseResult` holds the score accessors + weights |
| `app/Enums/{CoursePlanStatus,SessionStatus,AttendanceStatus,AssignmentSubmissionStatus,ResultStatus,DisputeStatus}.php` | Status enums (lowercase values; `isTerminal()` on plan + dispute) |
| `app/Enums/{SessionChangeType,AuditAction}.php` | Session-change type; the course/session/assignment/result/dispute audit cases |
| `app/Actions/Sao/ReviewCoursePlanApproval.php` | Approve/reject a plan (lock + `submitted` re-guard) |
| `app/Actions/Sao/PublishCourseResults.php` | Publish fully-scored drafts (gate + lock) |
| `app/Actions/Lecturer/MarkAttendance.php` | Cohort attendance upsert (lock + approved re-guard) |
| `app/Actions/Lecturer/GradeSubmission.php` | Grade a submission (lock; bound enforced by request) |
| `app/Actions/Lecturer/RecordCourseResults.php` | Draft marks upsert (cohort + published-lock skip) |
| `app/Actions/Student/SubmitAssignment.php` | Submit/resubmit (file-before-DB, out-of-tx cleanup) |
| `app/Actions/ReviewResultDispute.php` | Resolve a dispute (SAO/Admin; lock + terminal re-guard) |
| `app/Http/Controllers/Sao/{CourseController,ResultDisputeController}.php` | SAO catalogue, plan review, publish, dispute queue |
| `app/Http/Controllers/Lecturer/{CourseController,CourseSessionController,AssignmentController,CourseResultController}.php` | Lecturer plan, sessions/attendance, assignments/grading, marks |
| `app/Http/Controllers/Student/{CourseController,AttendanceController,AssignmentController,CourseResultController}.php` | Student read screens + submit + dispute |
| `app/Http/Controllers/Assignments/{SubmissionViewController,SubmissionDownloadController}.php` | Inline-viewer + download for submission files |
| `app/Http/Requests/Student/StoreAssignmentSubmissionRequest.php` | Upload allowlist (`pdf,jpg,jpeg,png`, 8 MB) |
| `app/Http/Requests/Lecturer/{StoreAssignmentRequest,UpdateAssignmentRequest,GradeSubmissionRequest,RecordCourseResultsRequest,StoreCourseSessionRequest,UpdateCourseSessionRequest,CancelCourseSessionRequest,MarkAttendanceRequest,UpdateCoursePlanRequest}.php` | Lecturer validation (grade bound = `max_score`) |
| `app/Http/Requests/Sao/{StoreCourseRequest,UpdateCourseRequest,AssignLecturerRequest,RejectCoursePlanRequest}.php`, `app/Http/Requests/{ReviewResultDisputeRequest}.php`, `app/Http/Requests/Student/StoreResultDisputeRequest.php` | SAO + dispute validation |
| `app/Events/CourseSessionChanged.php`, `app/Listeners/SendCourseSessionChangedNotification.php` | Session-change fan-out (see notifications module) |
| `routes/{sao,lecturer,student}.php`, `routes/web.php` (file routes) | Route definitions |
| `resources/js/pages/sao/courses/{Index,Form}.vue`, `sao/disputes/Index.vue` | SAO screens |
| `resources/js/pages/lecturer/courses/{Index,Plan,Sessions,Attendance,Assignments,Submissions,Results}.vue` | Lecturer screens |
| `resources/js/pages/student/{courses/Index.vue,Attendance.vue,assignments/Index.vue,results/Index.vue}` | Student screens |
| `resources/js/components/FileViewerDialog.vue` | Shared inline file-viewer dialog |
| `database/factories/{Course,CourseSession,AttendanceRecord,Assignment,AssignmentSubmission,CourseResult,ResultDispute}Factory.php` | Test factories |

---

*Sources verified against code: the seven models above; the six status enums + `SessionChangeType` +
`AuditAction`; `ReviewCoursePlanApproval`, `PublishCourseResults`, `MarkAttendance`, `GradeSubmission`,
`RecordCourseResults`, `SubmitAssignment`, `ReviewResultDispute`; the SAO/Lecturer/Student controllers
listed; `SubmissionViewController`; `AppServiceProvider::configureGates()`; `routes/{sao,lecturer,student}.php`
+ `routes/web.php`; `StoreAssignmentSubmissionRequest`, `GradeSubmissionRequest`,
`ReviewResultDisputeRequest`; `FileViewerDialog.vue`; the lecturer Vue pages' lowercase status
comparisons; and `plan/course-management/plan.md`.*
