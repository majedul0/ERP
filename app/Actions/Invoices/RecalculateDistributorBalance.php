<?php

namespace App\Actions\Invoices;

use App\Models\Distributor;
use App\Models\Invoice;
use App\Support\DistributorLedger;

class RecalculateDistributorBalance
{
    /**
     * Replay a distributor's whole account and write the result back.
     *
     * Each invoice stores the balance that stood before it (`previous_dues`)
     * and the balance after it (`total_amount`). That makes every invoice a
     * statement you can hand over and reconcile — and it means changing
     * anything invalidates every later line for the same distributor. So
     * editing an invoice, voiding one, or recording a payment does not patch a
     * single row: it replays the account.
     *
     * The walk is `App\Support\DistributorLedger`, the same one the statement
     * screen renders, so the dues printed on an invoice and the running
     * balance on the statement can never disagree.
     *
     * Must be called inside a transaction that already holds the distributor
     * row; the invoices and payments are locked here.
     */
    public function handle(Distributor $distributor): Distributor
    {
        $entries = DistributorLedger::entries($distributor, lock: true);

        $invoices = Invoice::query()
            ->where('distributor_id', $distributor->id)
            ->get()
            ->keyBy('id');

        $balance = 0;

        foreach ($entries as $entry) {
            if ($entry->type === 'invoice') {
                $invoice = $invoices->get($entry->id);

                // Only write rows that actually moved, so replaying an account
                // that is already correct costs nothing.
                if ($invoice && ($invoice->previous_dues !== $balance || $invoice->total_amount !== $entry->balanceAfter)) {
                    $invoice->update([
                        'previous_dues' => $balance,
                        'total_amount' => $entry->balanceAfter,
                    ]);
                }
            }

            $balance = $entry->balanceAfter;
        }

        $distributor->update(['balance' => $balance]);

        return $distributor;
    }
}
