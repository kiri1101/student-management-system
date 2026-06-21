<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Top of the admissions reference-data hierarchy: a Department fans out into
 * {@see ProgramOffering} rows (one per degree program it runs), each of which
 * in turn carries its own per-level credential requirements.
 */
#[Fillable(['name', 'code', 'description'])]
class Department extends Model
{
    use RecordsAudit, SoftDeletes;

    /**
     * @return HasMany<ProgramOffering, $this>
     */
    public function programOfferings(): HasMany
    {
        return $this->hasMany(ProgramOffering::class);
    }
}
