<?php

namespace App\Http\Controllers\Invoices;

use App\Actions\Invoices\CreateInvoice;
use App\Actions\Invoices\UpdateDeliveryStatus;
use App\Concerns\ResolvesCurrentTeam;
use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\CreateInvoiceRequest;
use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Product;
use App\Support\InvoiceNumbers;
use App\Support\InvoicePresenter;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    use ResolvesCurrentTeam;

    /**
     * List the company's invoices, newest first.
     */
    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);

        return Inertia::render('company/invoices/index', [
            'invoices' => $team->invoices()
                ->with('distributor')
                ->latest('sold_at')
                ->limit(200)
                ->get()
                ->map(fn (Invoice $invoice) => InvoicePresenter::summary($invoice, $team->slug))
                ->all(),
        ]);
    }

    /**
     * Show the create screen.
     */
    public function create(Request $request): Response
    {
        $team = $this->currentTeam($request);

        return Inertia::render('company/invoices/create', [
            'distributors' => $team->distributors()
                ->orderBy('name')
                ->get()
                ->map(fn (Distributor $distributor) => InvoicePresenter::distributor($distributor))
                ->all(),

            /*
             * Stock travels to the browser so the form can warn early, but it
             * is a snapshot: another user may sell the last carton while this
             * form is open. CreateInvoice re-checks under a row lock, and that
             * check is the one that decides.
             */
            'products' => $team->products()
                ->orderBy('name')
                ->get()
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'cartonSize' => $product->carton_size,
                    'distributorPrice' => Money::toDecimal($product->distributor_price),
                    'stockQuantity' => $product->stock_quantity,
                ])
                ->all(),

            'nextInvoiceNumber' => InvoiceNumbers::preview($team),
        ]);
    }

    /**
     * Write the invoice.
     */
    public function store(CreateInvoiceRequest $request, CreateInvoice $createInvoice): RedirectResponse
    {
        $team = $this->currentTeam($request);

        $invoice = $createInvoice->handle($team, $request->user(), $request->invoiceData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Invoice :number created.', ['number' => $invoice->invoice_number]),
        ]);

        return to_route('invoices.show', [
            'current_team' => $team->slug,
            'invoice' => $invoice->id,
        ]);
    }

    /**
     * Show one invoice.
     *
     * `$current_team` is unused but must be declared. These routes sit under a
     * `{current_team}` prefix, and Laravel hands route parameters to the
     * method positionally once a model of that type is already bound — leave
     * it out and `$invoice` receives the team slug instead of the invoice.
     */
    public function show(Request $request, string $current_team, Invoice $invoice): Response
    {
        $team = $this->currentTeam($request);

        abort_unless($invoice->team_id === $team->id, 404);

        $invoice->load(['distributor', 'items', 'creator']);

        return Inertia::render('company/invoices/show', [
            'invoice' => InvoicePresenter::detail($invoice),
            'statuses' => DeliveryStatus::assignable(),
        ]);
    }

    /**
     * Change an invoice's delivery status.
     */
    public function updateStatus(
        Request $request,
        string $current_team,
        Invoice $invoice,
        UpdateDeliveryStatus $updateStatus,
    ): RedirectResponse {
        $team = $this->currentTeam($request);

        abort_unless($invoice->team_id === $team->id, 404);

        $validated = $request->validate([
            'delivery_status' => ['required', Rule::enum(DeliveryStatus::class)],
        ]);

        $updateStatus->handle($invoice, DeliveryStatus::from($validated['delivery_status']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice updated.')]);

        return back();
    }
}
