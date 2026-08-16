<?php

namespace App\Http\Controllers\SalesReturns;

use App\Actions\SalesReturns\SaveSalesReturn;
use App\Concerns\ResolvesCurrentTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\SalesReturns\SaveSalesReturnRequest;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Team;
use App\Support\InvoicePresenter;
use App\Support\ReturnNumbers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Goods a distributor sent back.
 *
 * A return credits their account — see `App\Support\DistributorLedger` — and,
 * unless the goods were damaged, puts the stock back on the shelf.
 */
class SalesReturnController extends Controller
{
    use ResolvesCurrentTeam;

    /**
     * Every return the company has taken, newest first.
     */
    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);

        $returns = $team->salesReturns()
            ->with('distributor')
            ->latest('returned_on')
            ->latest('id')
            ->limit(200)
            ->get();

        return Inertia::render('company/sales-returns/index', [
            'returns' => $returns->map(fn (SalesReturn $return) => $this->summary($return, $team))->all(),
            'total' => (int) $team->salesReturns()->sum('return_total'),
        ]);
    }

    public function create(Request $request): Response
    {
        $team = $this->currentTeam($request);

        return Inertia::render('company/sales-returns/create', [
            'distributors' => $this->distributorOptions($team),
            'products' => $this->productOptions($team),
            'nextReturnNumber' => ReturnNumbers::preview($team),
        ]);
    }

    public function store(SaveSalesReturnRequest $request, SaveSalesReturn $saveReturn): RedirectResponse
    {
        $team = $this->currentTeam($request);
        $user = $request->user();

        abort_if($user === null, 403);

        $return = $saveReturn->handle($team, $user, $request->returnData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Return :number recorded.', ['number' => $return->return_number]),
        ]);

        return to_route('returns.show', [
            'current_team' => $team->slug,
            'return' => $return->id,
        ]);
    }

    /**
     * One return, in full.
     *
     * See InvoiceController::show() for why `$current_team` must be declared.
     */
    public function show(Request $request, string $current_team, SalesReturn $return): Response
    {
        $team = $this->currentTeam($request);

        abort_unless($return->team_id === $team->id, 404);

        $return->load(['distributor', 'creator']);

        return Inertia::render('company/sales-returns/show', [
            'return' => $this->detail($return, $team),
        ]);
    }

    public function edit(Request $request, string $current_team, SalesReturn $return): Response
    {
        $team = $this->currentTeam($request);

        abort_unless($return->team_id === $team->id, 404);

        return Inertia::render('company/sales-returns/edit', [
            'return' => $this->detail($return, $team),
            'distributors' => $this->distributorOptions($team),
            'products' => $this->productOptions($team),
        ]);
    }

    public function update(
        SaveSalesReturnRequest $request,
        string $current_team,
        SalesReturn $return,
        SaveSalesReturn $saveReturn,
    ): RedirectResponse {
        $team = $this->currentTeam($request);
        $user = $request->user();

        abort_unless($return->team_id === $team->id, 404);
        abort_if($user === null, 403);

        $return = $saveReturn->handle($team, $user, $request->returnData(), $return);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Return updated.')]);

        return to_route('returns.show', [
            'current_team' => $team->slug,
            'return' => $return->id,
        ]);
    }

    public function destroy(
        Request $request,
        string $current_team,
        SalesReturn $return,
        SaveSalesReturn $saveReturn,
    ): RedirectResponse {
        $team = $this->currentTeam($request);

        abort_unless($return->team_id === $team->id, 404);

        $saveReturn->delete($return);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Return deleted.')]);

        return to_route('returns.index', ['current_team' => $team->slug]);
    }

    /**
     * A row on the list.
     *
     * @return array<string, mixed>
     */
    private function summary(SalesReturn $return, Team $team): array
    {
        return [
            'id' => $return->id,
            'returnNumber' => $return->return_number,
            'returnedOn' => $return->returned_on->toDateString(),
            'distributorName' => $return->distributor->name,
            'proprietorName' => $return->distributor->proprietor_name,
            'distributorUrl' => route('distributors.show', [
                'current_team' => $team->slug,
                'distributor' => $return->distributor_id,
            ], absolute: false),
            'amount' => $return->return_total,
            'restock' => $return->restock,
            'comment' => $return->comment,
        ];
    }

    /**
     * A return with its lines, for the detail and edit screens.
     *
     * @return array<string, mixed>
     */
    private function detail(SalesReturn $return, Team $team): array
    {
        return array_merge($this->summary($return, $team), [
            'distributorId' => $return->distributor_id,
            'recordedAt' => $return->created_at?->toIso8601String(),

            // The printed document needs the address and phone the invoice
            // prints, and who to put on the signature line.
            'distributor' => InvoicePresenter::distributorContact($return->distributor),
            'createdBy' => $return->creator?->name,
            'items' => $return->items()
                ->get()
                ->map(fn (SalesReturnItem $item) => [
                    'id' => $item->id,
                    'productId' => $item->product_id,
                    'productName' => $item->product_name,
                    'productSku' => $item->product_sku,
                    'quantity' => $item->quantity,
                    'unitPrice' => $item->unit_price,
                    'amount' => $item->amount,
                    'remarks' => $item->remarks,
                ])
                ->all(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function distributorOptions(Team $team): array
    {
        return $team->distributors()
            ->orderBy('name')
            ->get()
            ->map(fn (Distributor $distributor) => InvoicePresenter::distributor($distributor))
            ->all();
    }

    /**
     * What the return form needs to price a line.
     *
     * Stock travels along so the form can show what is on the shelf, but it is
     * only a display: nothing about a return is refused for want of stock —
     * goods coming back can only add to it.
     *
     * @return array<int, array<string, mixed>>
     */
    private function productOptions(Team $team): array
    {
        return $team->products()
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'distributorPrice' => $product->distributor_price,
                'stockQuantity' => $product->stock_quantity,
            ])
            ->all();
    }
}
