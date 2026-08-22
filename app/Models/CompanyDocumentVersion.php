<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A file this document used to be.
 *
 * Renewing a trade licence does not make last year's copy worthless — it is
 * what proves the company was licensed last year. Replacing a file moves the
 * old one here rather than deleting it, and it stays downloadable.
 *
 * @property int $id
 * @property int $company_document_id
 * @property int|null $uploaded_by
 * @property int $version
 * @property string $file_path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property Carbon|null $superseded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CompanyDocument $document
 */
#[Fillable([
    'company_document_id',
    'uploaded_by',
    'version',
    'file_path',
    'original_name',
    'mime_type',
    'size_bytes',
    'superseded_at',
])]
class CompanyDocumentVersion extends Model
{
    /**
     * @return BelongsTo<CompanyDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(CompanyDocument::class, 'company_document_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'superseded_at' => 'datetime',
        ];
    }
}
