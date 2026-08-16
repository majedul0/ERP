<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Goods a distributor sent back.
 *
 * A credit against their running account, not a payment: no money changed
 * hands, but they owe less. It lands on the statement beside the invoices and
 * the payments, and `App\Support\DistributorLedger` is the one place that
 * decides what it is worth.
 *
 * @property int $id
 * @property int $team_id
 * @property int $distributor_id
 * @property int|null $created_by
 * @property string $return_number
 * @property int $sequence_number
 * @property Carbon $returned_on
 * @property string|null $comment
 * @property bool $restock
 * @property int $return_total
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read Distributor $distributor
 * @property-read User|null $creator
 * @property-read Collection<int, SalesReturnItem> $items
 */
#[Fillable([
    'team_id',
    'distributor_id',
    'created_by',
    'return_number',
    'sequence_number',
    'returned_on',
    'comment',
    'restock',
    'return_total',
])]
class SalesReturn extends Model
{
    use SoftDeletes;

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Distributor, $this>
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<SalesReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class)->orderBy('line_number');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'returned_on' => 'date',
            'restock' => 'boolean',
        ];
    }
}
