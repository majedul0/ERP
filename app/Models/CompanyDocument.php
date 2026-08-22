<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use App\Enums\DocumentStatus;
use Carbon\CarbonInterface;
use Database\Factories\CompanyDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A paper the company needs to be able to produce.
 *
 * The file lives on the private disk and is only ever reached through
 * DocumentController::download, which checks the tenant and the permission
 * before streaming a byte. Nothing here exposes a URL.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $uploaded_by
 * @property string $title
 * @property DocumentCategory $category
 * @property string|null $reference
 * @property Carbon|null $issued_on
 * @property Carbon|null $expires_on
 * @property string|null $note
 * @property string $file_path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read User|null $uploader
 * @property-read Collection<int, CompanyDocumentVersion> $versions
 */
#[Fillable([
    'team_id',
    'uploaded_by',
    'title',
    'category',
    'reference',
    'issued_on',
    'expires_on',
    'note',
    'file_path',
    'original_name',
    'mime_type',
    'size_bytes',
    'version',
])]
class CompanyDocument extends Model
{
    /** @use HasFactory<CompanyDocumentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Types that may be shown in the browser rather than downloaded.
     *
     * An allowlist, and a short one: `Content-Disposition: inline` on an
     * uploaded SVG or HTML file runs whatever script it contains in the app's
     * own origin. PDFs and raster images are safe to render and are what
     * people actually want to glance at, so everything else is sent as an
     * attachment regardless of what it claims to be.
     */
    public const INLINE_TYPES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/webp',
    ];

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Superseded files, newest first.
     *
     * @return HasMany<CompanyDocumentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(CompanyDocumentVersion::class)->orderByDesc('version');
    }

    /**
     * Where this document stands against its renewal date, as of a given day.
     *
     * Derived on every read rather than stored: a status column would be stale
     * by the next morning and would need a job to keep it true.
     */
    public function status(?CarbonInterface $asOf = null): DocumentStatus
    {
        if ($this->expires_on === null) {
            return DocumentStatus::Permanent;
        }

        $today = ($asOf ?? Carbon::now())->startOfDay();
        $expiry = $this->expires_on->startOfDay();

        if ($expiry->lt($today)) {
            return DocumentStatus::Expired;
        }

        return $expiry->lte($today->copy()->addDays(DocumentStatus::WARNING_DAYS))
            ? DocumentStatus::Expiring
            : DocumentStatus::Valid;
    }

    /**
     * Days until it lapses — negative once it has, null when it never does.
     */
    public function daysUntilExpiry(?CarbonInterface $asOf = null): ?int
    {
        if ($this->expires_on === null) {
            return null;
        }

        return (int) ($asOf ?? Carbon::now())
            ->startOfDay()
            ->diffInDays($this->expires_on->startOfDay(), false);
    }

    /**
     * Whether this file may be rendered in the browser. See INLINE_TYPES.
     */
    public function isInlineViewable(): bool
    {
        return in_array($this->mime_type, self::INLINE_TYPES, true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'expires_on' => 'date',
            'category' => DocumentCategory::class,
        ];
    }
}
