<?php

namespace App\Models;

use App\Enums\DegreeProgram;
use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A degree program ({@see DegreeProgram}: HND, Bachelors or Masters) offered
 * by a {@see Department} — the middle tier of the admissions reference-data
 * hierarchy. Unique per (department, degree_program); spans min_level..max_level
 * and owns the per-level credential requirements applicants are admitted against.
 */
#[Fillable(['department_id', 'degree_program', 'min_level', 'max_level'])]
class ProgramOffering extends Model
{
    use RecordsAudit, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'degree_program' => DegreeProgram::class,
            'min_level' => 'integer',
            'max_level' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<LevelCredentialRequirement, $this>
     */
    public function levelCredentialRequirements(): HasMany
    {
        return $this->hasMany(LevelCredentialRequirement::class);
    }

    /**
     * @return HasMany<Application, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
