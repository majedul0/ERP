<?php

namespace App\Actions\Invoices;

use App\Enums\DeliveryStatus;
use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class UpdateDeliveryStatus
{
    public function __construct(
        private readonly RecalculateDistributorBalance $recalculateBalance,
    ) {}

    /**
     * Move an invoice to a new delivery status.
     *
     * Going void returns the stock to the shelf and clears the debt; coming
     * back to life takes both again. Those two move together — see
     * DeliveryStatus::isLive() — so one check drives both.
     *
     * Two people pressing "Delivered" at once must not move stock twice, so
     * the invoice row is locked and the status re-read inside the transaction:
     * the second request sees what the first committed and does nothing.
     */
    public function handle(Invoice $invoice, DeliveryStatus $status): Invoice
    {
        return DB::transaction(function () use ($invoice, $status): Invoice {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $previous = $invoice->delivery_status;

            if ($previous === $status) {
                return $invoice;
            }

            $distributor = Distributor::whereKey($invoice->distributor_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($previous->isLive() !== $status->isLive()) {
                $this->moveStock($invoice, returning: ! $status->isLive());
            }

            $invoice->update(['delivery_status' => $status]);

            // A voided sale is no longer owed, so every later invoice for this
            // distributor carries forward a different figure.
            $this->recalculateBalance->handle($distributor);

            return $invoice->refresh();
        });
    }

    /**
     * Add every line's quantity back to stock, or take it out again.
     */
    private function moveStock(Invoice $invoice, bool $returning): void
    {
        $items = $invoice->items()->whereNotNull('product_id')->get();

        // Ascending product id, the same order CreateInvoice locks in, so the
        // two cannot deadlock against each other.
        $products = Product::query()
            ->whereIn('id', $items->pluck('product_id')->unique()->sort()->values())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($items as $item) {
            $product = $products->get($item->product_id);

            if (! $product) {
                continue;
            }

            // Explicit, not increment()/decrement() — see CreateInvoice.
            $product->update([
                'stock_quantity' => $returning
                    ? $product->stock_quantity + $item->total_quantity
                    : $product->stock_quantity - $item->total_quantity,
            ]);
        }
    }
}
