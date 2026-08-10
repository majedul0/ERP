<?php

namespace App\Actions\Invoices;

use App\Enums\DeliveryStatus;
use App\Models\Distributor;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class DeleteInvoice
{
    public function __construct(
        private readonly UpdateDeliveryStatus $updateStatus,
        private readonly RecalculateDistributorBalance $recalculateBalance,
    ) {}

    /**
     * Remove an invoice raised by mistake — a duplicate, typically.
     *
     * Voiding it first is not a formality. Cancelling is the one path that
     * returns the stock to the shelf and clears the debt, under the locks that
     * make those safe, and it is already the tested route for undoing a sale.
     * Deleting without it would leave the goods sold and the money owed with
     * nothing on file to explain either.
     *
     * A soft delete, deliberately:
     *
     * - The row stays, so the unique index on (team_id, invoice_number) keeps
     *   holding and the number can never be handed to a second invoice. A
     *   number that reached a customer must not name two different documents.
     * - The account is recoverable. An invoice removed in error is a row to
     *   restore, not a document to retype.
     *
     * The sequence keeps counting, so deleting INV7 leaves a gap between INV6
     * and INV8. That gap is the honest record of a document that existed.
     */
    public function handle(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            // Returns stock and clears the debt, or does nothing if the invoice
            // was already void.
            $this->updateStatus->handle($invoice, DeliveryStatus::Cancelled);

            $distributor = Distributor::whereKey($invoice->distributor_id)
                ->lockForUpdate()
                ->firstOrFail();

            $invoice->delete();

            // A cancelled invoice already owed nothing, so the figures do not
            // move here — but its own row leaves the statement, and the replay
            // is what rewrites the lines that followed it.
            $this->recalculateBalance->handle($distributor);
        });
    }
}
