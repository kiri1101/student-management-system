<?php

namespace App\Models;

use App\Enums\SessionStatus;
use App\Models\Concerns\RecordsAudit;
use Database\Factories\CourseSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single scheduled lecture for a course. Status (SessionStatus) is one of
 * Scheduled → Held or Scheduled → Cancelled; cancelling or rescheduling a future
 * session notifies the implicit cohort (#12). Attendance is captured per session
 * via attendanceRecords once the session is held.
 */
#[Fillable([
    'course_id',
    'scheduled_for',
    'topic',
    'duration_minutes',
    'status',
    'cancellation_reason',
])]
class CourseSession extends Model
{
    /** @use HasFactory<CourseSessionFactory> */
    use HasFactory, RecordsAudit, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'duration_minutes' => 'integer',
            'status' => SessionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasMany<AttendanceRecord, $this>
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
