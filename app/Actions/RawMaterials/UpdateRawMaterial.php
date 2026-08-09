<?php

namespace App\Actions\RawMaterials;

use App\Enums\MaterialUnit;
use App\Models\RawMaterial;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class UpdateRawMaterial
{
    /**
     * Change a material's details, cost, reorder level or stock.
     *
     * Stock typed here is an absolute count, not a delta — it is the store
     * being recounted, exactly like UpdateProduct. The row is locked first so
     * a recount and a purchase landing at the same moment cannot lose one of
     * the two: read-then-write on an unlocked row is how stock goes missing.
     *
     * Changing the unit cost does not reach backwards into purchases already
     * recorded; each purchase line keeps the price actually paid.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(RawMaterial $material, array $data): RawMaterial
    {
        return DB::transaction(function () use ($material, $data): RawMaterial {
            $material = RawMaterial::whereKey($material->id)->lockForUpdate()->firstOrFail();

            $material->update([
                'name' => $data['name'],
                'code' => $data['code'],
                'unit' => MaterialUnit::from($data['unit']),
                'stock_quantity' => (int) $data['stock_quantity'],
                'reorder_level' => (int) $data['reorder_level'],
                'unit_cost' => Money::fromInput($data['unit_cost']),
                'note' => $data['note'] ?? null,
            ]);

            return $material;
        });
    }
}
