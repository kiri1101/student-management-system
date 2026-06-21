<?php

namespace App\Models;

use App\Enums\CoursePlanStatus;
use App\Enums\StudentStatus;
use App\Models\Concerns\RecordsAudit;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A course offered to a single implicit cohort, identified by the tuple
 * (program_offering_id + level + academic_year). There is no enrollment table:
 * cohort membership is derived on demand from active student profiles matching
 * that tuple (see cohortStudents()). The course plan moves through
 * Draft → Submitted → Approved/Rejected (CoursePlanStatus) under SAO review.
 */
#[Fillable([
    'program_offering_id',
    'level',
    'academic_year',
    'code',
    'title',
    'credits',
    'semester',
    'description',
    'lecturer_profile_id',
    'plan_status',
    'plan_submitted_at',
    'plan_reviewed_at',
    'plan_reviewed_by',
    'plan_review_notes',
])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory, RecordsAudit, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'credits' => 'integer',
            'semester' => 'integer',
            'plan_status' => CoursePlanStatus::class,
            'plan_submitted_at' => 'datetime',
            'plan_reviewed_at' => 'datetime',
        ];
    }

    /**
     * Resolved withTrashed so a course keeps rendering even if its offering is
     * soft-deleted out from under it (mirrors StudentProfile, AUDIT.md AUD-013).
     *
     * @return BelongsTo<ProgramOffering, $this>
     */
    public function programOffering(): BelongsTo
    {
        return $this->belongsTo(ProgramOffering::class)->withTrashed();
    }

    /**
     * @return BelongsTo<LecturerProfile, $this>
     */
    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(LecturerProfile::class, 'lecturer_profile_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function planReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'plan_reviewed_by');
    }

    /**
     * @return HasMany<CourseSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(CourseSession::class);
    }

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * @return HasMany<CourseResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(CourseResult::class);
    }

    /**
     * The implicit cohort: active student profiles whose enrollment tuple
     * (offering + level + academic_year) matches this course. There is no
     * enrollment table — membership is derived (plan/course-management/plan.md).
     *
     * @return Builder<StudentProfile>
     */
    public function cohortStudents(): Builder
    {
        return StudentProfile::query()
            ->where('program_offering_id', $this->program_offering_id)
            ->where('level', $this->level)
            ->where('academic_year', $this->academic_year)
            ->where('status', StudentStatus::Active);
    }
}
