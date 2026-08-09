<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One material on a purchase, at the name, code, unit and price it was bought
 * under. Those are copies, not lookups: repricing or renaming a material later
 * must leave every purchase already recorded exactly as it happened.
 *
 * @property int $id
 * @property int $material_purchase_id
 * @property int $raw_material_id
 * @property string $material_name
 * @property string $material_code
 * @property string $unit
 * @property int $line_number
 * @property int $quantity
 * @property int $unit_cost
 * @property int $line_total
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MaterialPurchase $purchase
 * @property-read RawMaterial $material
 */
#[Fillable([
    'material_purchase_id',
    'raw_material_id',
    'material_name',
    'material_code',
    'unit',
    'line_number',
    'quantity',
    'unit_cost',
    'line_total',
])]
class MaterialPurchaseItem extends Model
{
    /**
     * @return BelongsTo<MaterialPurchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(MaterialPurchase::class, 'material_purchase_id');
    }

    /**
     * @return BelongsTo<RawMaterial, $this>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class, 'raw_material_id');
    }
}
