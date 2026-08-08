<?php

namespace App\Models;

use App\Support\TenantFileStore;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string $sku
 * @property int $carton_size
 * @property int $distributor_price
 * @property int $trade_price
 * @property int $mrp
 * @property int $stock_quantity
 * @property string|null $photo_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 */
#[Fillable([
    'team_id',
    'name',
    'sku',
    'carton_size',
    'distributor_price',
    'trade_price',
    'mrp',
    'stock_quantity',
    'photo_path',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * The public URL of the product photo, or null when none is uploaded.
     */
    public function photoUrl(): ?string
    {
        return TenantFileStore::url('product_photos', $this->photo_path);
    }
}
