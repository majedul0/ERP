<?php

namespace App\Http\Controllers\RawMaterials;

use App\Actions\RawMaterials\RecordMaterialPurchase;
use App\Concerns\ResolvesCurrentTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\RawMaterials\StoreMaterialPurchaseRequest;
use App\Models\MaterialPurchase;
use App\Models\MaterialPurchaseItem;
use App\Models\RawMaterial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MaterialPurchaseController extends Controller
{
    use ResolvesCurrentTeam;

    /**
     * List the company's material purchases, newest first.
     */
    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);

        $purchases = $team->materialPurchases()
            ->withCount('items')
            ->orderByDesc('purchased_at')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('company/raw-materials/purchases/index', [
            'purchases' => $purchases->map(fn (MaterialPurchase $purchase) => [
                'id' => $purchase->id,
                'supplierName' => $purchase->supplier_name,
                'reference' => $purchase->reference,
                'purchasedAt' => $purchase->purchased_at->toDateString(),
                'totalAmount' => $purchase->total_amount,
                'itemCount' => $purchase->items_count,
            ])->all(),
        ]);
    }

    /**
     * Show the purchase entry form.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('company/raw-materials/purchases/create', [
            'materials' => $this->materialOptions($request),
        ]);
    }

    /**
     * Record the purchase and take the stock in.
     */
    public function store(
        StoreMaterialPurchaseRequest $request,
        RecordMaterialPurchase $recordPurchase,
    ): RedirectResponse {
        $team = $this->currentTeam($request);
        $user = $request->user();

        abort_if($user === null, 403);

        $purchase = $recordPurchase->handle($team, $user, $request->purchaseData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Purchase recorded and stock updated.')]);

        return to_route('purchases.show', [
            'current_team' => $team->slug,
            'purchase' => $purchase->id,
        ]);
    }

    /**
     * Show one purchase with its lines.
     *
     * See InvoiceController::show() for why `$current_team` must be declared.
     */
    public function show(Request $request, string $current_team, MaterialPurchase $purchase): Response
    {
        $team = $this->currentTeam($request);

        abort_unless($purchase->team_id === $team->id, 404);

        $purchase->load('items', 'creator');

        return Inertia::render('company/raw-materials/purchases/show', [
            'purchase' => [
                'id' => $purchase->id,
                'supplierName' => $purchase->supplier_name,
                'reference' => $purchase->reference,
                'purchasedAt' => $purchase->purchased_at->toDateString(),
                'totalAmount' => $purchase->total_amount,
                'note' => $purchase->note,
                'recordedBy' => $purchase->creator->name,
                'items' => $purchase->items->map(fn (MaterialPurchaseItem $item) => [
                    'id' => $item->id,
                    'materialName' => $item->material_name,
                    'materialCode' => $item->material_code,
                    'unit' => $item->unit,
                    'quantity' => $item->quantity,
                    'unitCost' => $item->unit_cost,
                    'lineTotal' => $item->line_total,
                ])->all(),
            ],
        ]);
    }

    /**
     * The materials a purchase line may choose from.
     *
     * @return array<int, array<string, mixed>>
     */
    private function materialOptions(Request $request): array
    {
        return $this->currentTeam($request)
            ->rawMaterials()
            ->orderBy('name')
            ->get()
            ->map(fn (RawMaterial $material) => [
                'id' => $material->id,
                'name' => $material->name,
                'code' => $material->code,
                'unitShort' => $material->unit->short(),
                'stockQuantity' => $material->stock_quantity,
                'unitCost' => $material->unit_cost,
            ])
            ->all();
    }
}
