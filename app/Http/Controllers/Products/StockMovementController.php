<?php

namespace App\Http\Controllers\Products;

use App\Actions\Products\RecordStockMovement;
use App\Concerns\ResolvesCurrentTeam;
use App\Enums\StockMovementReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Products\RecordStockRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class StockMovementController extends Controller
{
    use ResolvesCurrentTeam;

    /**
     * Add production to a product, or take stock off it.
     *
     * See InvoiceController::show() for why `$current_team` must be declared.
     */
    public function store(
        RecordStockRequest $request,
        string $current_team,
        Product $product,
        RecordStockMovement $recordStockMovement,
    ): RedirectResponse {
        $team = $this->currentTeam($request);

        abort_unless($product->team_id === $team->id, 404);

        $validated = $request->validated();
        $quantity = (int) $validated['quantity'];
        $adding = $validated['direction'] === 'add';

        $recordStockMovement->handle(
            product: $product,
            quantity: $adding ? $quantity : -$quantity,
            reason: StockMovementReason::from($validated['reason']),
            occurredOn: Carbon::parse($validated['occurred_on']),
            remarks: $validated['remarks'] ?? null,
            actor: $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $adding
                ? __('Stock added to :product.', ['product' => $product->name])
                : __('Stock reduced for :product.', ['product' => $product->name]),
        ]);

        return to_route('products.index', ['current_team' => $team->slug]);
    }
}
