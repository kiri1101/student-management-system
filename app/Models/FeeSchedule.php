<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Database\Factories\FeeScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['program_offering_id', 'level', 'academic_year', 'total_xaf'])]
class FeeSchedule extends Model
{
    /** @use HasFactory<FeeScheduleFactory> */
    use HasFactory, RecordsAudit, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'total_xaf' => 'integer',
        ];
    }

    public function programOffering(): BelongsTo
    {
        return $this->belongsTo(ProgramOffering::class);
    }

    /**
     * Ordered payment milestones, lowest sequence first.
     */
    public function installments(): HasMany
    {
        return $this->hasMany(FeeInstallment::class)->orderBy('sequence');
    }
}
