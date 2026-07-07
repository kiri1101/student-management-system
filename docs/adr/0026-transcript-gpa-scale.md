# ADR-0026: Transcript GPA on a 4.0 scale

- **Status:** Accepted
- **Date:** 2026-07-06
- **Deciders:** SchuLyf maintainers

## Context
Course results are stored as 0–100 marks that resolve to letter grades A/B/C/D/F
(`CourseResult::grade`: weighted final `0.3·ca + 0.7·exam`; A ≥ 80, B ≥ 70, C ≥ 60, D ≥ 50, else F).
An academic transcript (#71) must summarise a student's standing across many courses in one legible
figure. The options were: report raw weighted averages (0–100), or aggregate onto a grade-point
average. A GPA is the widely recognised, credit-weighted standard for transcripts and is what
scholarship/transfer bodies expect; a raw percentage average ignores credit weighting and does not
read as an official standing.

## Decision
Transcripts aggregate on a **4.0 GPA scale**. The grade-point map lives in
`TranscriptService::GRADE_POINTS`:

| A | B | C | D | F |
|---|---|---|---|---|
| 4.0 | 3.0 | 2.0 | 1.0 | 0.0 |

Whole points only — the grading scheme has no +/− tiers, so no 3.7/3.3 gradations. Per-course quality
points = grade points × course credits. **Semester GPA** and **cumulative CGPA** = Σ(quality points)
÷ Σ(credits), rounded to 2 dp. **`credits_earned`** counts passed courses only (excludes F);
**`credits_attempted`** counts all included courses. Only `Published` results with both marks present
are aggregated.

## Consequences
- Stored marks and the letter-grade rule are unchanged — the GPA scale is a presentation layer over
  the existing `CourseResult::grade` accessor. The map is the single source of truth and is unit-
  tested (`TranscriptServiceTest`).
- The transcript shows both the per-course detail (score %, letter, points) and the GPA/CGPA + credit
  totals, so a reader can reconcile the aggregate against the source rows.
- Should the institution later adopt +/− tiers, only `GRADE_POINTS` and `CourseResult::grade` change;
  the aggregation math is agnostic to how many distinct point values exist.

## As-built vs. planned
Built as designed in [plan/transcripts/design.md](../../plan/transcripts/design.md) §2. No drift.
Delivered on `feat/student-transcripts` (#71).
