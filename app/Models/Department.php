<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'code', 'description'])]
class Department extends Model
{
    use SoftDeletes;

    public function programOfferings(): HasMany
    {
        return $this->hasMany(ProgramOffering::class);
    }
}
