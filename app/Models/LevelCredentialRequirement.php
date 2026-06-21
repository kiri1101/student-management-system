<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The leaf of the admissions reference-data hierarchy: declares that a given
 * {@see DocumentType} is expected (required or optional) for one level of a
 * {@see ProgramOffering}. Unique per (program_offering, level, document_type);
 * drives the per-level required-document set on the application form.
 */
#[Fillable(['program_offering_id', 'level', 'document_type_id', 'required', 'notes'])]
class LevelCredentialRequirement extends Model
{
    use RecordsAudit, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'required' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ProgramOffering, $this>
     */
    public function programOffering(): BelongsTo
    {
        return $this->belongsTo(ProgramOffering::class);
    }

    /**
     * @return BelongsTo<DocumentType, $this>
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }
}
