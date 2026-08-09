<?php

namespace App\Actions\Invoices;

use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Team;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateInvoice
{
    public function __construct(
        private readonly RecalculateDistributorBalance $recalculateBalance,
    ) {}

    /**
     * Rewrite an existing invoice.
     *
     * Three things have to move together, or the books stop reconciling:
     *
     * 1. **Stock.** The quantities the invoice previously took are returned
     *    first, then the new ones are taken. Doing it in that order means an
     *    edit that only changes the price never trips a stock check, and an
     *    edit that raises a quantity is checked against stock that already
     *    includes what this invoice was holding.
     * 2. **Amounts.** Recomputed here from the locked product rows, exactly as
     *    CreateInvoice does. The browser's totals are never read back.
     * 3. **The dues chain.** Every later invoice for the distributor carries
     *    this one's balance forward, so the chain is replayed rather than
     *    patched — and if the invoice moved to a different distributor, both
     *    chains are replayed.
     *
     * The invoice number never changes: it is on documents that have already
     * been printed and handed over.
     *
     * Lock order matches CreateInvoice — invoice, then distributors, then
     * products by ascending id — so the two cannot deadlock against each other.
     *
     * @param  array{
     *     distributor_id: int,
     *     sold_at: string,
     *     comment?: string|null,
     *     scheme_description?: string|null,
     *     scheme_amount?: int|null,
     *     previous_dues?: int|null,
     *     items: list<array{product_id: int, carton_quantity?: int, total_quantity: int, unit_price?: int|null, discount?: int|null, remarks?: string|null}>
     * }  $data
     */
    public function handle(Team $team, Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($team, $invoice, $data): Invoice {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $isLive = $invoice->delivery_status->isLive();

            $distributors = $this->lockDistributors($team, $invoice, (int) $data['distributor_id']);
            $previousItems = $invoice->items()->get();

            $products = $this->lockProducts($team, $previousItems, $data['items']);

            // Put back what this invoice was holding before checking whether
            // the new quantities fit.
            if ($isLive) {
                $this->returnStock($previousItems, $products);
            }

            $lines = $this->buildLines($data['items'], $products);

            if ($isLive) {
                $this->assertStockIsSufficient($lines, $products);
            }

            $invoiceTotal = array_sum(array_column($lines, 'amount'));
            $discountTotal = array_sum(array_column($lines, 'discount'));

            $invoice->update([
                'distributor_id' => (int) $data['distributor_id'],
                'sold_at' => Carbon::parse($data['sold_at']),
                'comment' => $data['comment'] ?? null,
                'scheme_description' => $data['scheme_description'] ?? null,
                'scheme_amount' => Money::fromInput($data['scheme_amount'] ?? 0),
                'invoice_total' => $invoiceTotal,
                'discount_total' => $discountTotal,

                // Cleared before the replay so the account can say what the
                // opening figure would be on its own; re-applied below only if
                // the user really did type something else.
                'previous_dues_override' => null,
            ]);

            $invoice->items()->delete();
            $invoice->items()->createMany($lines);

            if ($isLive) {
                foreach ($lines as $line) {
                    $product = $products[$line['product_id']];

                    // Explicit, not decrement() — see CreateInvoice.
                    $product->update([
                        'stock_quantity' => $product->stock_quantity - $line['total_quantity'],
                    ]);
                }
            }

            // previous_dues and total_amount are written by the replay, not
            // here — they belong to the account, not to this invoice alone.
            foreach ($distributors as $distributor) {
                $this->recalculateBalance->handle($distributor);
            }

            $this->applyPreviousDuesOverride($invoice, $data, $distributors);

            return $invoice->refresh();
        });
    }

    /**
     * Keep a hand-typed opening balance, or drop one that is no longer needed.
     *
     * The replay above has just written what the account says the opening
     * figure should be. If the user submitted that same number — including by
     * leaving the field untouched — there is nothing to override and the
     * invoice goes back to following the account. Anything else is pinned, and
     * the account is replayed again so the difference shows on the statement.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, Distributor>  $distributors
     */
    private function applyPreviousDuesOverride(Invoice $invoice, array $data, array $distributors): void
    {
        if (! isset($data['previous_dues'])) {
            return;
        }

        $submitted = (int) $data['previous_dues'];

        if ($submitted === $invoice->refresh()->previous_dues) {
            return;
        }

        $invoice->update(['previous_dues_override' => $submitted]);

        foreach ($distributors as $distributor) {
            $this->recalculateBalance->handle($distributor);
        }
    }

    /**
     * Lock the distributor the invoice is leaving and the one it is joining.
     *
     * Usually the same row; when an invoice is moved between distributors both
     * ledgers change and both must be replayed.
     *
     * @return array<int, Distributor>
     */
    private function lockDistributors(Team $team, Invoice $invoice, int $newDistributorId): array
    {
        $ids = collect([$invoice->distributor_id, $newDistributorId])->unique()->sort()->values();

        $distributors = Distributor::query()
            ->where('team_id', $team->id)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if (! $distributors->has($newDistributorId)) {
            throw ValidationException::withMessages([
                'distributor_id' => __('That distributor is no longer available.'),
            ]);
        }

        /** @var array<int, Distributor> */
        return $distributors->all();
    }

    /**
     * Lock every product on either side of the edit, in ascending id order.
     *
     * @param  Collection<int, InvoiceItem>  $previousItems
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, Product>
     */
    private function lockProducts(Team $team, Collection $previousItems, array $items): array
    {
        $ids = $previousItems->pluck('product_id')
            ->merge(collect($items)->pluck('product_id'))
            ->filter()
            ->map(intval(...))
            ->unique()
            ->sort()
            ->values();

        $products = Product::query()
            ->where('team_id', $team->id)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $requested = collect($items)->pluck('product_id')->map(intval(...))->unique();

        if ($requested->reject(fn (int $id) => $products->has($id))->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => __('One of the selected products is no longer available.'),
            ]);
        }

        /** @var array<int, Product> */
        return $products->all();
    }

    /**
     * Give back the quantities the invoice was holding.
     *
     * @param  Collection<int, InvoiceItem>  $items
     * @param  array<int, Product>  $products
     */
    private function returnStock(Collection $items, array $products): void
    {
        foreach ($items as $item) {
            if ($item->product_id !== null && isset($products[$item->product_id])) {
                $product = $products[$item->product_id];

                $product->update([
                    'stock_quantity' => $product->stock_quantity + $item->total_quantity,
                ]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, Product>  $products
     * @return array<int, array<string, mixed>>
     */
    private function buildLines(array $items, array $products): array
    {
        $lines = [];

        foreach (array_values($items) as $index => $item) {
            $product = $products[(int) $item['product_id']];

            $quantity = (int) $item['total_quantity'];
            $unitPrice = array_key_exists('unit_price', $item) && $item['unit_price'] !== null && $item['unit_price'] !== ''
                ? Money::fromInput($item['unit_price'])
                : $product->distributor_price;

            $lines[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'line_number' => $index + 1,
                'carton_quantity' => (int) ($item['carton_quantity'] ?? 0),
                'total_quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => Money::multiply($unitPrice, $quantity),
                'discount' => Money::fromInput($item['discount'] ?? 0),
                'remarks' => $item['remarks'] ?? null,
            ];
        }

        return $lines;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<int, Product>  $products
     */
    private function assertStockIsSufficient(array $lines, array $products): void
    {
        $requested = [];

        foreach ($lines as $line) {
            $id = $line['product_id'];
            $requested[$id] = ($requested[$id] ?? 0) + $line['total_quantity'];
        }

        $errors = [];

        foreach ($requested as $productId => $quantity) {
            $product = $products[$productId];

            if ($quantity > $product->stock_quantity) {
                $errors['items'][] = __('Not enough stock for :product — :available in stock, :requested requested.', [
                    'product' => $product->name,
                    'available' => $product->stock_quantity,
                    'requested' => $quantity,
                ]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
