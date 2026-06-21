<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Database\Factories\LecturerProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Role-specific data for a user holding the Lecturer role; staff are
 * single-role (ADR-0002), so a user has at most one of these.
 */
#[Fillable([
    'user_id',
    'department_id',
    'specialization',
    'hired_at',
])]
class LecturerProfile extends Model
{
    /** @use HasFactory<LecturerProfileFactory> */
    use HasFactory, RecordsAudit, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hired_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
