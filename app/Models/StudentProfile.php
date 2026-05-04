<?php

namespace App\Models;

use App\Enums\StudentStatus;
use App\Models\Concerns\RecordsAudit;
use Database\Factories\StudentProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'matricule',
    'program_offering_id',
    'level',
    'academic_year',
    'enrolled_at',
    'status',
])]
class StudentProfile extends Model
{
    /** @use HasFactory<StudentProfileFactory> */
    use HasFactory, RecordsAudit, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'enrolled_at' => 'date',
            'status' => StudentStatus::class,
        ];
    }

    /**
     * Canonicalize matricules to lowercase so SQLite tests match the case Fortify
     * lowercases the login identifier to (`CanonicalizeUsername` action).
     */
    protected function matricule(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : Str::lower($value),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function programOffering(): BelongsTo
    {
        return $this->belongsTo(ProgramOffering::class);
    }
}
