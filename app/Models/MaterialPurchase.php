<?php

namespace App\Models;

use Database\Factories\MaterialPurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A delivery of raw materials from a supplier.
 *
 * @property int $id
 * @property int $team_id
 * @property int $created_by
 * @property string $supplier_name
 * @property string|null $reference
 * @property Carbon $purchased_at
 * @property int $total_amount
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read User $creator
 * @property-read Collection<int, MaterialPurchaseItem> $items
 */
#[Fillable([
    'team_id',
    'created_by',
    'supplier_name',
    'reference',
    'purchased_at',
    'total_amount',
    'note',
])]
class MaterialPurchase extends Model
{
    /** @use HasFactory<MaterialPurchaseFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchased_at' => 'date',
        ];
    }

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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<MaterialPurchaseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MaterialPurchaseItem::class)->orderBy('line_number');
    }
}
