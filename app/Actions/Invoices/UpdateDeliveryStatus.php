<?php

namespace App\Actions\Invoices;

use App\Enums\DeliveryStatus;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class UpdateDeliveryStatus
{
    /**
     * Move an invoice to a new delivery status, returning stock to the shelf
     * when it stops being a sale and taking it back when it becomes one again.
     *
     * Two people pressing "Delivered" at once must not double-move stock, so
     * the invoice row is locked and the status re-read inside the transaction:
     * the second request sees the status the first one committed and does
     * nothing.
     */
    public function handle(Invoice $invoice, DeliveryStatus $status): Invoice
    {
        return DB::transaction(function () use ($invoice, $status): Invoice {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $previous = $invoice->delivery_status;

            if ($previous === $status) {
                return $invoice;
            }

            if ($previous->holdsStock() !== $status->holdsStock()) {
                $this->moveStock($invoice, returning: ! $status->holdsStock());
            }

            $invoice->update(['delivery_status' => $status]);

            return $invoice;
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

            $returning
                ? $product->increment('stock_quantity', $item->total_quantity)
                : $product->decrement('stock_quantity', $item->total_quantity);
        }
    }
}
