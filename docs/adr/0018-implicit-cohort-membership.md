# ADR-0018: No enrollment table — cohort membership is implicit

- **Status:** Accepted
- **Date:** 2026-06-18
- **Deciders:** SchuLyf maintainers

## Context
Courses, exam-gating, and notifications all need to answer "which students belong to this cohort?". A
classic answer is an `enrollments` join table linking students to courses/cohorts. But in this domain a
student's cohort is fully determined by their program offering, level, and academic year — an explicit
enrollment row would be redundant state to keep in sync, and a source of drift.

## Decision
There is **no `Enrollment` model and no enrollment table.** Cohort membership is **derived from the
tuple `(program_offering_id, level, academic_year)`** (plus `status = Active`). It is computed in one
place — `Course::cohortStudents()` (`app/Models/Course.php:103`):

```php
StudentProfile::query()
    ->where('program_offering_id', $this->program_offering_id)
    ->where('level', $this->level)
    ->where('academic_year', $this->academic_year)
    ->where('status', StudentStatus::Active);
```

## Consequences
- No enrollment state to maintain — a student's cohort follows automatically from their profile.
- The same tuple is the single definition consumed by `SendCourseSessionChangedNotification`,
  `MarkAttendance`, `RecordCourseResults`, and the lecturer roster controllers; exam-gating's
  `PaymentStandingService::for()` **re-derives the same tuple independently** to find the `FeeSchedule`.
- `DecideApplicationAction::promoteToStudent()` builds the `StudentProfile` from the application's
  `program_offering_id` + `level` (admit-year `academic_year`) — the bridge from the application
  snapshot ([0011](0011-application-data-snapshot.md)) into a cohort.
- Moving a student between cohorts means changing their profile tuple, not editing join rows. There is
  no way to enroll a student in a course outside their tuple — by design.

See [`../modules/course-management.md`](../modules/course-management.md) and
[`../modules/exam-gating.md`](../modules/exam-gating.md).
