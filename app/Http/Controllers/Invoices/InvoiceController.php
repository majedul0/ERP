<?php

namespace App\Http\Controllers\Invoices;

use App\Actions\Invoices\CreateInvoice;
use App\Actions\Invoices\UpdateDeliveryStatus;
use App\Actions\Invoices\UpdateInvoice;
use App\Concerns\ResolvesCurrentTeam;
use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\SaveInvoiceRequest;
use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Team;
use App\Support\InvoiceNumbers;
use App\Support\InvoicePresenter;
use App\Support\StockVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'distributors' => $this->distributorOptions($team),
            'products' => $this->productOptions($team),
            'nextInvoiceNumber' => InvoiceNumbers::preview($team),
            'stockVersion' => StockVersion::current($team->id),
        ]);
    }

    /**
     * Has any stock moved in this company?
     *
     * One Redis read, no database. An open invoice form asks this when the
     * user adds a line, when the tab regains focus, and on a slow timer; only
     * a changed number costs a real query.
     *
     * @return array{version: int}
     */
    public function stockVersion(Request $request): array
    {
        return ['version' => StockVersion::current($this->currentTeam($request)->id)];
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
     * What the invoice form needs to price and check a line.
     *
     * Stock travels to the browser so the form can warn early, but it is a
     * snapshot: another user may sell the last carton while this form is open.
     * CreateInvoice and UpdateInvoice re-check under a row lock, and that
     * check is the one that decides.
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
                'cartonSize' => $product->carton_size,
                'distributorPrice' => $product->distributor_price,
                'stockQuantity' => $product->stock_quantity,
            ])
            ->all();
    }

    /**
     * Write the invoice.
     */
    public function store(SaveInvoiceRequest $request, CreateInvoice $createInvoice): RedirectResponse
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
     * Show the edit screen, prefilled with what the invoice currently says.
     *
     * See `show()` for why `$current_team` must be declared.
     */
    public function edit(Request $request, string $current_team, Invoice $invoice): Response
    {
        $team = $this->currentTeam($request);

        abort_unless($invoice->team_id === $team->id, 404);

        $invoice->load(['distributor', 'items']);

        return Inertia::render('company/invoices/edit', [
            'invoice' => InvoicePresenter::detail($invoice),
            'distributors' => $this->distributorOptions($team),

            /*
             * Stock here already excludes what this invoice is holding, so the
             * form adds it back for its own lines — otherwise raising a
             * quantity by one would look like it needed a whole new allocation.
             */
            'products' => $this->productOptions($team),
        ]);
    }

    /**
     * Rewrite the invoice.
     */
    public function update(
        SaveInvoiceRequest $request,
        string $current_team,
        Invoice $invoice,
        UpdateInvoice $updateInvoice,
    ): RedirectResponse {
        $team = $this->currentTeam($request);

        abort_unless($invoice->team_id === $team->id, 404);

        $updateInvoice->handle($team, $invoice, $request->invoiceData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Invoice :number updated.', ['number' => $invoice->invoice_number]),
        ]);

        return to_route('invoices.show', [
            'current_team' => $team->slug,
            'invoice' => $invoice->id,
        ]);
    }

    /**
     * The delivery note for an invoice.
     *
     * Rendered from the invoice every time it is asked for — there is no
     * stored challan — so an edited invoice produces an updated challan with
     * nothing to regenerate.
     *
     * See `show()` for why `$current_team` must be declared.
     */
    public function challan(Request $request, string $current_team, Invoice $invoice): Response
    {
        $team = $this->currentTeam($request);

        abort_unless($invoice->team_id === $team->id, 404);

        $invoice->load(['distributor', 'items']);

        return Inertia::render('company/invoices/challan', [
            'challan' => InvoicePresenter::challan($invoice),
        ]);
    }

    /**
     * Download the invoice as a spreadsheet.
     *
     * CSV rather than a binary .xlsx: Excel, LibreOffice and Google Sheets all
     * open it natively, and it needs no extra dependency on the VPS. The
     * response is streamed, so a long invoice never has to sit in memory.
     */
    public function export(Request $request, string $current_team, Invoice $invoice): StreamedResponse
    {
        $team = $this->currentTeam($request);

        abort_unless($invoice->team_id === $team->id, 404);

        $invoice->load(['distributor', 'items']);

        $filename = "{$invoice->invoice_number}.csv";

        return response()->streamDownload(function () use ($team, $invoice) {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                throw new RuntimeException('Could not open the output stream for the invoice export.');
            }

            // Excel reads a file as the system codepage unless a UTF-8 BOM
            // says otherwise, which would mangle ৳ and any Bangla text.
            fwrite($handle, "\u{FEFF}");

            foreach ($this->exportRows($team, $invoice) as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Every row of the exported spreadsheet, in order.
     *
     * @return list<list<string|int>>
     */
    private function exportRows(Team $team, Invoice $invoice): array
    {
        $rows = [
            [$team->name],

            // array_values, because filtering out a missing address would
            // otherwise leave a gap and stop this being a list.
            array_values(array_filter([$team->address, $team->phone])),
            [],
            ['Invoice', $invoice->invoice_number],
            ['Sale Date', $invoice->sold_at->format('d-m-Y H:i')],
            ['Status', $invoice->delivery_status->label()],
            ['Distributor', $invoice->distributor->name],
            ['Proprietor', (string) $invoice->distributor->proprietor_name],
            ['Address', $invoice->distributor->fullAddress()],
            ['Phone', (string) $invoice->distributor->phone],
            [],
            ['ID', 'Product', 'SKU', 'CTN QTY', 'Total QTY', 'Unit Price', 'Amount', 'Discount', 'Remarks'],
        ];

        foreach ($invoice->items as $item) {
            $rows[] = [
                $item->line_number,
                $item->product_name,
                (string) $item->product_sku,
                $item->carton_quantity,
                $item->total_quantity,
                $item->unit_price,
                $item->amount,
                $item->discount,
                (string) $item->remarks,
            ];
        }

        $rows[] = [];
        $rows[] = ['Invoice Total', $invoice->invoice_total];
        $rows[] = ['Discount Total', $invoice->discount_total];

        if ($invoice->scheme_amount > 0) {
            $rows[] = [$invoice->scheme_description ?: 'Scheme', $invoice->scheme_amount];
        }

        $rows[] = ['Previous Dues', $invoice->previous_dues];
        $rows[] = ['Total Amount', $invoice->total_amount];

        return $rows;
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
