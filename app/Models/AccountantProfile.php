<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Database\Factories\AccountantProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'bank_desk',
    'cashier_window',
])]
class AccountantProfile extends Model
{
    /** @use HasFactory<AccountantProfileFactory> */
    use HasFactory, RecordsAudit, SoftDeletes;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
