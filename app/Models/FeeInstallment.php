<?php

namespace App\Models;

use Database\Factories\FeeInstallmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One dated milestone of a FeeSchedule: `amount_xaf` becomes part of a student's
 * `required_so_far` once `due_date` has passed (see PaymentStandingService).
 * Ordered within its schedule by `sequence`. Money is integer XAF.
 */
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

    /**
     * @return BelongsTo<FeeSchedule, $this>
     */
    public function feeSchedule(): BelongsTo
    {
        return $this->belongsTo(FeeSchedule::class);
    }
}
