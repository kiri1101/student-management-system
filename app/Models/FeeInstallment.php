<?php

namespace App\Models;

use Database\Factories\FeeInstallmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['fee_schedule_id', 'sequence', 'label', 'amount_xaf', 'due_date'])]
class FeeInstallment extends Model
{
    /** @use HasFactory<FeeInstallmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'amount_xaf' => 'integer',
            'due_date' => 'date',
        ];
    }

    public function feeSchedule(): BelongsTo
    {
        return $this->belongsTo(FeeSchedule::class);
    }
}
