<?php

namespace App\Actions\RawMaterials;

use App\Models\MaterialPurchase;
use App\Models\RawMaterial;
use App\Models\Team;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordMaterialPurchase
{
    /**
     * Record a delivery of raw materials and take the stock in — all or nothing.
     *
     * The totals are recomputed here from the quantities and the costs typed on
     * the form. Unlike a sale, where the product row holds the authoritative
     * price, the price on a purchase *is* what the supplier charged, so it is
     * taken from the form — but the arithmetic is still the server's. The
     * browser's totals are for the person filling the form and are never read
     * back.
     *
     * Materials are locked FOR UPDATE in ascending id order, the same order
     * CreateInvoice uses for products, so a purchase and a recount touching the
     * same rows queue instead of deadlocking.
     *
     * @param  array{
     *     supplier_name: string,
     *     reference?: string|null,
     *     purchased_at: string,
     *     note?: string|null,
     *     items: list<array{raw_material_id: int, quantity: int, unit_cost: int}>
     * }  $data
     */
    public function handle(Team $team, User $user, array $data): MaterialPurchase
    {
        return DB::transaction(function () use ($team, $user, $data): MaterialPurchase {
            $materials = $this->lockMaterials($team, $data['items']);
            $lines = $this->buildLines($data['items'], $materials);

            $purchase = MaterialPurchase::create([
                'team_id' => $team->id,
                'created_by' => $user->id,
                'supplier_name' => $data['supplier_name'],
                'reference' => $data['reference'] ?? null,
                'purchased_at' => Carbon::parse($data['purchased_at']),
                'total_amount' => array_sum(array_column($lines, 'line_total')),
                'note' => $data['note'] ?? null,
            ]);

            $purchase->items()->createMany($lines);

            $this->takeStockIn($lines, $materials);

            return $purchase->refresh();
        });
    }

    /**
     * Lock every material the purchase touches, in ascending id order.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, RawMaterial> Keyed by material id.
     */
    private function lockMaterials(Team $team, array $items): array
    {
        $ids = collect($items)->pluck('raw_material_id')->map(intval(...))->unique()->sort()->values();

        $materials = RawMaterial::query()
            ->where('team_id', $team->id)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $missing = $ids->reject(fn (int $id) => $materials->has($id));

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => __('One of the selected materials is no longer available.'),
            ]);
        }

        /** @var array<int, RawMaterial> */
        return $materials->all();
    }

    /**
     * Turn submitted rows into purchase item attributes.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, RawMaterial>  $materials
     * @return array<int, array<string, mixed>>
     */
    private function buildLines(array $items, array $materials): array
    {
        $lines = [];

        foreach (array_values($items) as $index => $item) {
            $material = $materials[(int) $item['raw_material_id']];

            $quantity = (int) $item['quantity'];
            $unitCost = Money::fromInput($item['unit_cost']);

            $lines[] = [
                'raw_material_id' => $material->id,
                'material_name' => $material->name,
                'material_code' => $material->code,
                'unit' => $material->unit->value,
                'line_number' => $index + 1,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'line_total' => Money::multiply($unitCost, $quantity),
            ];
        }

        return $lines;
    }

    /**
     * Add the delivered quantities to stock and remember what was paid.
     *
     * Quantities are summed per material first: the same material may appear on
     * two lines at two prices, and each has to land on the counter once.
     *
     * The last cost wins for `unit_cost` — it is what the next stock valuation
     * and the next purchase form should start from. Purchase lines keep the
     * price each was actually bought at, so nothing historical moves.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<int, RawMaterial>  $materials
     */
    private function takeStockIn(array $lines, array $materials): void
    {
        $received = [];
        $lastCost = [];

        foreach ($lines as $line) {
            $id = $line['raw_material_id'];
            $received[$id] = ($received[$id] ?? 0) + $line['quantity'];
            $lastCost[$id] = $line['unit_cost'];
        }

        foreach ($received as $materialId => $quantity) {
            $material = $materials[$materialId];

            /*
             * An explicit update rather than increment(), matching
             * CreateInvoice: increment syncs the model's original attributes,
             * which hides the change from any `saved` listener. Safe because
             * the row is locked FOR UPDATE above.
             */
            $material->update([
                'stock_quantity' => $material->stock_quantity + $quantity,
                'unit_cost' => $lastCost[$materialId],
            ]);
        }
    }
}
