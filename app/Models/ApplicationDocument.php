<?php

namespace App\Models;

use App\Enums\ApplicationDocumentStatus;
use App\Models\Concerns\RecordsAudit;
use Database\Factories\ApplicationDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single uploaded credential attached to an {@see Application}, classified
 * by its {@see DocumentType}. Stores the stored file_path alongside the
 * original_filename, mime_type and size_bytes captured at upload time.
 */
#[Fillable([
    'application_id',
    'document_type_id',
    'file_path',
    'original_filename',
    'mime_type',
    'size_bytes',
    'uploaded_at',
    'status',
    'review_notes',
    'reviewed_by',
    'reviewed_at',
])]
class ApplicationDocument extends Model
{
    /** @use HasFactory<ApplicationDocumentFactory> */
    use HasFactory, RecordsAudit, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'uploaded_at' => 'datetime',
            'status' => ApplicationDocumentStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @return BelongsTo<DocumentType, $this>
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /**
     * The staff user who last reviewed this document (null while pending).
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
