<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'code', 'description'])]
class DocumentType extends Model
{
    use RecordsAudit, SoftDeletes;

    public function levelCredentialRequirements(): HasMany
    {
        return $this->hasMany(LevelCredentialRequirement::class);
    }
}
