# Course Management (#11 / B3) — Implementation Plan

**Branch:** `feat/course-management` (off `main` @ `6d3cbd6`). One PR closing #11.
**Built by parallel specialized sub-agents** (`laravel-backend-drafter`, `vue-inertia-drafter`, `pest-test-drafter`) per phase; orchestrator defines the contract, cherry-picks drafts, runs the quality gate, commits. See memories `feedback-parallelism-subagents` + `feedback-parallel-subagent-integration`.

## Locked design decisions
1. **Implicit cohort membership.** A student is "in" a course when their `program_offering_id` + `level` (+ `academic_year`) match the course. No enrollment table, no enrollment flow. Cohort = `StudentProfile::where(program_offering_id, level, academic_year)->where('status', Active)`.
2. **Full vertical**, phases C0→C4, one PR.
3. **Assignments include file upload + grade** (reuses payments' private-disk + owner-gated download pattern).
4. **Native-browser inline file viewer, no new dependency** — `response()->file()` serves inline; a shared PrimeVue `FileViewerDialog.vue` renders PDFs via `<iframe>` and images via `<img>`. Built as C0, retrofitted onto existing file modules.

## Roles (existing)
- **Lecturer** — teaches; drafts course plans; creates sessions; marks attendance; creates/grades assignments; records CA + exam scores.
- **SAO** — academic oversight: approves/rejects course plans (`approve-course-plan`), publishes results (`publish-results`), resolves disputes. Also may create courses + assign lecturers (with Admin).
- **Student** — views own courses/attendance/assignments/results; submits assignments; raises result disputes.
- Gates `approve-course-plan`, `mark-attendance`, `publish-results` already declared in `AppServiceProvider::configureGates()` — wire them to real authorization here.

---

## Phase C0 — Shared inline file viewer (foundation + retrofit)
**Backend:** no new model. For each file-serving controller add an **inline view** action reusing the SAME gate as its download:
- `response()->file($absolutePath, ['Content-Disposition' => 'inline; filename="..."'])` (note: `response()->file()` is inline by default; `Storage::download()` forces attachment — keep both).
- Retrofit targets: `PaymentSlipDownloadController` (owner|accountant|admin) → add `payments.slip.view`; admission `ApplicationDocument` download → add an inline view route reusing its gate.
- Only `application/pdf` + images are viewable; other mime → download-only (frontend decides via mime prop).

**Frontend:** `resources/js/components/FileViewerDialog.vue` — PrimeVue `Dialog` (maximizable), props `{ viewUrl: string; downloadUrl: string; filename: string; mime: string }`. PDF → `<iframe :src="viewUrl">`; image → `<img>`; else → message + Download button. Header has "Open in new tab" + "Download". Wire it into `accountant/payments/Review.vue` (slip), the student receipt/slip view, and the admission document-review page.

**Tests:** inline route returns 200 + `Content-Disposition: inline` for the authorized actor; 403 for others (mirrors existing download-auth tests).

---

## Phase C1 — Course catalog + lecturer assignment + SAO plan approval
**Model `Course`** (`RecordsAudit`, `SoftDeletes`, `#[Fillable]`): `program_offering_id`, `level` (int, within offering range), `academic_year` (string), `code`, `title`, `credits` (int), `semester` (int 1|2), `description` (nullable text), `lecturer_profile_id` (nullable FK), `plan_status` (CoursePlanStatus cast), `plan_submitted_at`, `plan_reviewed_at`, `plan_reviewed_by` (FK users), `plan_review_notes` (nullable). Unique: (`program_offering_id`, `level`, `academic_year`, `code`). Relations: `programOffering()` (withTrashed), `lecturer()` → LecturerProfile, `planReviewer()` → User. Scope `cohort()` → matching active StudentProfiles. Factory + states (`draft`, `submitted`, `approved`, `assigned`).

**Enum `CoursePlanStatus`** (string-backed, TitleCase): `Draft`, `Submitted`, `Approved`, `Rejected` + `isTerminal()` (Approved). `label()`.

**Flow & routes:**
- SAO/Admin: create course + assign lecturer — `sao.courses.*` (index/create/store/edit/update/assignLecturer). `manage-courses` gate (SAO+Admin).
- Lecturer: edit own course plan + submit for approval — `lecturer.courses.index/edit/update/submit`. New `routes/lecturer.php` (role:lecturer), required from `web.php`.
- SAO review: approve/reject — `sao.courses.approve` / `sao.courses.reject` via **`App\Actions\Sao\ReviewCoursePlanApproval`** (lockForUpdate re-guard on `plan_status`, audit, no mail required). `approve-course-plan` gate.

**Audit actions:** `CourseCreated`, `LecturerAssigned`, `CoursePlanSubmitted`, `CoursePlanApproved`, `CoursePlanRejected`.

**Frontend:** `sao/courses/{Index,Form}.vue`, `lecturer/courses/{Index,Plan}.vue`, SAO approval queue. Sidebar: "Courses" for SAO + Lecturer. `statusDisplay.ts` += `CoursePlanStatus` map.

**Tests:** course creation + uniqueness; lecturer assignment; plan submit (only by assigned lecturer); SAO approve/reject + re-guard + audit; authorization per role.

---

## Phase C2 — Sessions + attendance
**Model `CourseSession`** (RecordsAudit, SoftDeletes): `course_id`, `scheduled_for` (datetime), `topic`, `duration_minutes` (int), `status` (SessionStatus: `Scheduled`/`Held`/`Cancelled`). Created by assigned lecturer, only on **Approved** courses.
**Model `AttendanceRecord`**: `course_session_id`, `student_profile_id`, `status` (AttendanceStatus: `Present`/`Absent`/`Late`/`Excused`), `marked_by` (FK users), `marked_at`. Unique (`course_session_id`, `student_profile_id`).

**Flow:** lecturer creates sessions (`lecturer.courses.sessions.*`), marks attendance for the cohort in one form (`mark-attendance` gate) via **`App\Actions\Lecturer\MarkAttendance`** (upsert per student in a transaction + audit `AttendanceMarked`). Student views own attendance — `student.attendance.index`.

**Frontend:** `lecturer/courses/Sessions.vue` + attendance-marking grid (PrimeVue DataTable + per-row status select); `student/Attendance.vue`. statusDisplay += SessionStatus + AttendanceStatus.

**Tests:** session create gated on Approved course + assigned lecturer; attendance upsert idempotent; only cohort students markable; student sees only own.

---

## Phase C3 — Assignments (file upload + grade)
**Model `Assignment`** (RecordsAudit, SoftDeletes): `course_id`, `title`, `instructions` (text), `due_at` (datetime), `max_score` (int), `created_by` (FK users). Only on Approved courses.
**Model `AssignmentSubmission`**: `assignment_id`, `student_profile_id`, `file_path` (private disk), `original_filename`, `mime_type`, `size_bytes`, `submitted_at`, `score` (nullable int), `feedback` (nullable text), `graded_by` (FK users, nullable), `graded_at` (nullable), `status` (AssignmentSubmissionStatus: `Submitted`/`Graded`). Unique (`assignment_id`, `student_profile_id`) — one submission per student (resubmit replaces file, out-of-transaction file cleanup per AUD-009).

**Flow:** lecturer creates assignment (`lecturer.courses.assignments.*`); student uploads submission (`student.assignments.submit`, cohort-only, before/after due handled — late flag); lecturer downloads/**views inline (C0)** + grades via **`App\Actions\Lecturer\GradeSubmission`** (score ≤ max_score, audit `AssignmentGraded`). Inline view + download routes reuse C0, owner|lecturer-of-course|admin gated.

**Audit:** `AssignmentCreated`, `AssignmentSubmitted`, `AssignmentGraded`.
**Frontend:** `lecturer/courses/Assignments.vue` + grading view (with `FileViewerDialog`), `student/assignments/{Index,Submit}.vue` (PrimeVue FileUpload). statusDisplay += AssignmentSubmissionStatus.
**Tests:** upload validation (mime/size), cohort-only, one-per-student replace + old-file cleanup, grade ≤ max, inline-view/download auth.

---

## Phase C4 — CA + exam results, SAO publish, disputes
**Model `CourseResult`** (RecordsAudit, SoftDeletes): `course_id`, `student_profile_id`, `ca_score` (nullable int), `exam_score` (nullable int), `status` (ResultStatus: `Draft`/`Published`), `published_at`, `published_by` (FK users). Unique (`course_id`, `student_profile_id`). Computed accessors `finalScore` (weighted CA+exam) + `grade` (letter). Lecturer enters scores; SAO publishes the whole course's results in one action.
**Model `ResultDispute`** (RecordsAudit, SoftDeletes): `course_result_id`, `student_profile_id`, `reason` (text), `status` (DisputeStatus: `Open`/`UnderReview`/`Resolved`/`Rejected`), `resolution_notes` (nullable), `reviewed_by` (nullable FK users), `reviewed_at`. One open dispute per result.

**Flow:** lecturer records scores (`lecturer.courses.results.*`); SAO publishes — `sao.courses.publishResults` via **`App\Actions\Sao\PublishCourseResults`** (`publish-results` gate, transaction, audit `ResultsPublished`); student views **published** results only (`student.results.index`) + raises dispute (`student.results.dispute`) on a published result; lecturer/SAO resolves via **`App\Actions\ReviewResultDispute`** (audit `DisputeResolved`). Optional queued mail on publish/dispute-resolve (mirror deferral mail) — include if cheap.

**Audit:** `ResultRecorded`, `ResultsPublished`, `DisputeRaised`, `DisputeResolved`.
**Frontend:** `lecturer/courses/Results.vue` (score grid), SAO publish action, `student/results/Index.vue` (published only + dispute dialog), dispute resolution queue. statusDisplay += ResultStatus + DisputeStatus.
**Tests:** score record + bounds; publish gating (SAO only, only fully-scored); student sees published only; dispute one-open-per-result; resolution + audit.

---

## Per-phase quality gate (orchestrator runs)
`vendor/bin/pint --dirty --format agent` → `php artisan test --compact` → `npm run build` (no chunk-size regression) → `npm run types:check` → `npm run lint:check` → `php artisan migrate:fresh --seed`. Commit `feat(course): … (Cn, #11)` per phase.
