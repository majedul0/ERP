<?php

namespace App\Actions\Invoices;

use App\Enums\DeliveryStatus;
use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Support\InvoiceNumbers;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateInvoice
{
    public function __construct(
        private readonly RecalculateDistributorBalance $recalculateBalance,
    ) {}

    /**
     * Write an invoice, move the stock it sells, and roll the distributor's
     * balance forward — all or nothing.
     *
     * Everything monetary is recomputed here from the product rows and the
     * quantities. The amounts the browser calculated are for the person
     * filling the form; they are never read back, so a stale price on a form
     * left open for an hour, or a hand-edited request, cannot decide what an
     * invoice is worth.
     *
     * Lock order is fixed — distributor, then products by ascending id — so
     * two invoices sharing a distributor or a product queue instead of
     * deadlocking.
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
    public function handle(Team $team, User $user, array $data): Invoice
    {
        return DB::transaction(function () use ($team, $user, $data): Invoice {
            $distributor = Distributor::query()
                ->where('team_id', $team->id)
                ->whereKey($data['distributor_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $products = $this->lockProducts($team, $data['items']);
            $lines = $this->buildLines($data['items'], $products);

            $this->assertStockIsSufficient($lines, $products);

            $invoiceTotal = array_sum(array_column($lines, 'amount'));
            $discountTotal = array_sum(array_column($lines, 'discount'));
            $schemeAmount = Money::fromInput($data['scheme_amount'] ?? 0);

            /*
             * Previous dues normally come from replaying the account. A figure
             * typed on the form is kept only when it differs from that — an
             * opening balance for a distributor coming off paper, say — so a
             * user who leaves the field alone does not accidentally pin every
             * invoice to a fixed number.
             */
            $override = isset($data['previous_dues']) && (int) $data['previous_dues'] !== $distributor->balance
                ? (int) $data['previous_dues']
                : null;

            $previousDues = $override ?? $distributor->balance;
            $totalAmount = $invoiceTotal - $discountTotal - $schemeAmount + $previousDues;

            ['number' => $number, 'sequence' => $sequence] = InvoiceNumbers::next($team);

            $invoice = Invoice::create([
                'team_id' => $team->id,
                'distributor_id' => $distributor->id,
                'created_by' => $user->id,
                'invoice_number' => $number,
                'sequence_number' => $sequence,
                'sold_at' => Carbon::parse($data['sold_at']),
                'delivery_status' => DeliveryStatus::Pending,
                'comment' => $data['comment'] ?? null,
                'scheme_description' => $data['scheme_description'] ?? null,
                'scheme_amount' => $schemeAmount,
                'invoice_total' => $invoiceTotal,
                'discount_total' => $discountTotal,
                'previous_dues' => $previousDues,
                'previous_dues_override' => $override,
                'total_amount' => $totalAmount,
            ]);

            $invoice->items()->createMany($lines);

            foreach ($lines as $line) {
                $product = $products[$line['product_id']];

                /*
                 * An explicit update rather than decrement(): decrement syncs
                 * the model's original attributes, which leaves a `saved`
                 * listener unable to see that stock moved — and that listener
                 * is what tells other people's open invoice forms to refresh.
                 * Safe because the row is locked FOR UPDATE above.
                 */
                $product->update([
                    'stock_quantity' => $product->stock_quantity - $line['total_quantity'],
                ]);
            }

            /*
             * Replay rather than simply setting the balance: an invoice can be
             * backdated, in which case its true opening figure is the balance
             * as of its own date, not the account's balance right now. The
             * replay puts every invoice's dues where they belong.
             */
            $this->recalculateBalance->handle($distributor);

            return $invoice->refresh();
        });
    }

    /**
     * Lock every product the invoice touches, in ascending id order.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, Product> Keyed by product id.
     */
    private function lockProducts(Team $team, array $items): array
    {
        $ids = collect($items)->pluck('product_id')->map(intval(...))->unique()->sort()->values();

        $products = Product::query()
            ->where('team_id', $team->id)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $missing = $ids->reject(fn (int $id) => $products->has($id));

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => __('One of the selected products is no longer available.'),
            ]);
        }

        /** @var array<int, Product> */
        return $products->all();
    }

    /**
     * Turn submitted rows into invoice item attributes, pricing each from the
     * locked product row unless an explicit unit price was given.
     *
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
            $discount = Money::fromInput($item['discount'] ?? 0);

            $lines[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'line_number' => $index + 1,
                'carton_quantity' => (int) ($item['carton_quantity'] ?? 0),
                'total_quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => Money::multiply($unitPrice, $quantity),
                'discount' => $discount,
                'remarks' => $item['remarks'] ?? null,
            ];
        }

        return $lines;
    }

    /**
     * Refuse the whole invoice if any line outsells its stock.
     *
     * Quantities are summed per product first: the same product can appear on
     * two lines, and each fitting individually does not mean both do.
     *
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
