<?php

namespace App\Models;

use App\Enums\MaterialUnit;
use Database\Factories\RawMaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Something the company buys in to make what it sells.
 *
 * Deliberately separate from Product: a product has a selling price, a carton
 * size and a place on an invoice; a material has none of those and is only
 * ever bought, held and consumed.
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string $code
 * @property MaterialUnit $unit
 * @property int $stock_quantity
 * @property int $reorder_level
 * @property int $unit_cost
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 */
#[Fillable([
    'team_id',
    'name',
    'code',
    'unit',
    'stock_quantity',
    'reorder_level',
    'unit_cost',
    'note',
])]
class RawMaterial extends Model
{
    /** @use HasFactory<RawMaterialFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit' => MaterialUnit::class,
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
     * Whether stock has fallen to the point of reordering.
     *
     * A reorder level of 0 means the company has not asked to be warned about
     * this material, so it is never low — otherwise every material would go
     * red the moment it ran out, including ones bought to order.
     */
    public function isLow(): bool
    {
        return $this->reorder_level > 0
            && $this->stock_quantity <= $this->reorder_level;
    }

    /**
     * What the stock on hand is worth at the last price paid for it.
     */
    public function stockValue(): int
    {
        return $this->stock_quantity * $this->unit_cost;
    }
}
