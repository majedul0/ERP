<?php

namespace App\Http\Controllers\RawMaterials;

use App\Concerns\ResolvesCurrentTeam;
use App\Http\Controllers\Controller;
use App\Models\RawMaterial;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockLevelController extends Controller
{
    use ResolvesCurrentTeam;

    /**
     * What is in the store, worst first.
     *
     * The same rows as the All Materials list, ordered and summarised for the
     * one question this screen answers: what needs buying. Low materials sort
     * to the top so the answer is the first thing on the page.
     */
    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);

        $materials = $team->rawMaterials()->orderBy('name')->get();

        $levels = $materials
            ->map(fn (RawMaterial $material) => [
                'id' => $material->id,
                'name' => $material->name,
                'code' => $material->code,
                'unitShort' => $material->unit->short(),
                'stockQuantity' => $material->stock_quantity,
                'reorderLevel' => $material->reorder_level,
                'unitCost' => $material->unit_cost,
                'stockValue' => $material->stockValue(),
                'isLow' => $material->isLow(),
                // How much to buy to get back above the reorder level. Zero
                // when the material is not low, so the column stays empty for
                // everything that is fine.
                'shortfall' => $material->isLow()
                    ? $material->reorder_level - $material->stock_quantity
                    : 0,
            ])
            // Low first, then the deepest shortfall, then by name — so the
            // screen opens on what to do something about.
            ->sortBy([
                fn (array $a, array $b) => ($b['isLow'] <=> $a['isLow']),
                fn (array $a, array $b) => ($b['shortfall'] <=> $a['shortfall']),
                fn (array $a, array $b) => strcmp($a['name'], $b['name']),
            ])
            ->values()
            ->all();

        return Inertia::render('company/raw-materials/stock-levels', [
            'levels' => $levels,
            'summary' => [
                'materialCount' => $materials->count(),
                'lowCount' => $materials->filter->isLow()->count(),
                'outOfStockCount' => $materials->where('stock_quantity', '<=', 0)->count(),
                'totalValue' => $materials->sum(fn (RawMaterial $material) => $material->stockValue()),
            ],
        ]);
    }
}
