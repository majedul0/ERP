<?php

namespace App\Models;

use App\Enums\StockMovementReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A dated change to a product's stock that was not a sale or a return.
 *
 * Sales and returns are not copied in here — they are dated records already,
 * and `App\Support\ProductStockReport` reads them where they live. This table
 * holds the movements that would otherwise have no date at all: production,
 * recounts, and goods written off.
 *
 * @property int $id
 * @property int $team_id
 * @property int $product_id
 * @property int|null $created_by
 * @property Carbon $occurred_on
 * @property int $quantity
 * @property StockMovementReason $reason
 * @property string|null $remarks
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Product $product
 * @property-read User|null $creator
 */
#[Fillable([
    'team_id',
    'product_id',
    'created_by',
    'occurred_on',
    'quantity',
    'reason',
    'remarks',
])]
class StockMovement extends Model
{
    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'quantity' => 'integer',
            'reason' => StockMovementReason::class,
        ];
    }
}
