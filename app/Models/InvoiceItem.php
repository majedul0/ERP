<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $invoice_id
 * @property int|null $product_id
 * @property string $product_name
 * @property string|null $product_sku
 * @property int $line_number
 * @property int $carton_quantity
 * @property int $total_quantity
 * @property int $unit_price
 * @property int $amount
 * @property int $discount
 * @property string|null $remarks
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Invoice $invoice
 * @property-read Product|null $product
 */
#[Fillable([
    'invoice_id',
    'product_id',
    'product_name',
    'product_sku',
    'line_number',
    'carton_quantity',
    'total_quantity',
    'unit_price',
    'amount',
    'discount',
    'remarks',
])]
class InvoiceItem extends Model
{
    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
