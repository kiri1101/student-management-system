<?php

namespace App\Models;

use App\Enums\DegreeProgram;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['department_id', 'degree_program', 'min_level', 'max_level'])]
class ProgramOffering extends Model
{
    use SoftDeletes;

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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function levelCredentialRequirements(): HasMany
    {
        return $this->hasMany(LevelCredentialRequirement::class);
    }
}
