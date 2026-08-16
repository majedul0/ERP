<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One product on a sales return, priced as it was credited.
 *
 * @property int $id
 * @property int $sales_return_id
 * @property int|null $product_id
 * @property string $product_name
 * @property string|null $product_sku
 * @property int $line_number
 * @property int $quantity
 * @property int $unit_price
 * @property int $amount
 * @property string|null $remarks
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SalesReturn $salesReturn
 * @property-read Product|null $product
 */
#[Fillable([
    'sales_return_id',
    'product_id',
    'product_name',
    'product_sku',
    'line_number',
    'quantity',
    'unit_price',
    'amount',
    'remarks',
])]
class SalesReturnItem extends Model
{
    /**
     * @return BelongsTo<SalesReturn, $this>
     */
    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
