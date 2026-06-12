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

    /**
     * Codes hardwired into the application form and its server validation
     * (National Identity + Birth Certificate, `plan/context.md` §6.4). They
     * are required by code-string rather than by a `level_credential_
     * requirements` row, so deleting or renaming them would break every new
     * application — both are refused (AUDIT.md AUD-014). The single source
     * of truth for `StoreApplicationRequest`'s always-required set.
     *
     * @var list<string>
     */
    public const PROTECTED_CODES = ['NID', 'BIRTH'];

    public function isProtected(): bool
    {
        return in_array($this->code, self::PROTECTED_CODES, strict: true);
    }

    public function levelCredentialRequirements(): HasMany
    {
        return $this->hasMany(LevelCredentialRequirement::class);
    }
}
