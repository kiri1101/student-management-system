<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Database\Factories\SaoProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'scope',
])]
class SaoProfile extends Model
{
    /** @use HasFactory<SaoProfileFactory> */
    use HasFactory, RecordsAudit, SoftDeletes;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
