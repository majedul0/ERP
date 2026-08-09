<?php

namespace App\Support;

use App\Data\LedgerEntry;
use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Payment;

/**
 * A distributor's running account: every invoice they were charged and every
 * payment they made, in the order those things happened.
 *
 * This is the single ordering in the system. The statement renders it, and
 * `RecalculateDistributorBalance` writes the same walk back onto the invoices
 * — so a statement can never disagree with the dues printed on an invoice.
 *
 * Ordering is by **document date**, then invoices before payments on the same
 * day, then by id. Charging before settling on a shared day is the convention
 * a running account expects: a payment made the day an invoice is raised
 * settles that invoice rather than appearing to precede it.
 */
final class DistributorLedger
{
    /**
     * Build the account in order, with the balance after each line.
     *
     * `$lock` takes `FOR UPDATE` on the rows; pass it when the caller is about
     * to write the balance back, so a concurrent invoice or payment cannot
     * slip in between the read and the write.
     *
     * @return list<LedgerEntry>
     */
    public static function entries(Distributor $distributor, bool $lock = false): array
    {
        $invoices = Invoice::query()
            ->where('distributor_id', $distributor->id)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get();

        $payments = Payment::query()
            ->where('distributor_id', $distributor->id)
            ->with('bank')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get();

        $rows = [];

        foreach ($invoices as $invoice) {
            $rows[] = [
                'sortDate' => $invoice->sold_at->format('Y-m-d'),
                'sortGroup' => 0,
                'sortId' => $invoice->id,
                'type' => 'invoice',
                'id' => $invoice->id,
                'occurredOn' => $invoice->sold_at,
                'reference' => $invoice->invoice_number,
                'description' => $invoice->delivery_status->isLive()
                    ? 'Sales invoice'
                    : 'Sales invoice ('.strtolower($invoice->delivery_status->label()).')',

                // A void invoice stays on the statement — the sale happened
                // and was undone — but it charges nothing.
                'debit' => $invoice->delivery_status->isLive()
                    ? $invoice->invoice_total - $invoice->discount_total - $invoice->scheme_amount
                    : 0,
                'credit' => 0,
                'override' => $invoice->previous_dues_override,
            ];
        }

        foreach ($payments as $payment) {
            $rows[] = [
                'sortDate' => $payment->paid_on->format('Y-m-d'),
                'sortGroup' => 1,
                'sortId' => $payment->id,
                'type' => 'payment',
                'id' => $payment->id,
                'occurredOn' => $payment->paid_on,
                // `bank_id` is nullable, so `??` covers a payment recorded
                // without one — cash in hand, say.
                'reference' => $payment->bank->name ?? 'Payment',
                'description' => $payment->comment ?: 'Payment received',
                'debit' => 0,
                'credit' => $payment->amount,
                'override' => null,
            ];
        }

        usort($rows, fn (array $a, array $b) => [$a['sortDate'], $a['sortGroup'], $a['sortId']]
            <=> [$b['sortDate'], $b['sortGroup'], $b['sortId']]);

        $balance = 0;
        $entries = [];

        foreach ($rows as $row) {
            /*
             * An invoice whose opening balance was typed by hand restarts the
             * account from that figure. The gap between what the account said
             * and what was entered becomes its own visible line, so the
             * statement still adds up top to bottom instead of appearing to
             * jump. Nothing is discarded: every invoice and payment above it
             * stays exactly where it was.
             */
            if ($row['override'] !== null && $row['override'] !== $balance) {
                $difference = $row['override'] - $balance;

                $entries[] = new LedgerEntry(
                    type: 'adjustment',
                    id: $row['id'],
                    occurredOn: $row['occurredOn'],
                    reference: 'Adjustment',
                    description: 'Opening balance set on '.$row['reference'],
                    debit: max($difference, 0),
                    credit: max(-$difference, 0),
                    balanceAfter: $row['override'],
                );

                $balance = $row['override'];
            }

            $balance = $balance + $row['debit'] - $row['credit'];

            $entries[] = new LedgerEntry(
                type: $row['type'],
                id: $row['id'],
                occurredOn: $row['occurredOn'],
                reference: $row['reference'],
                description: $row['description'],
                debit: $row['debit'],
                credit: $row['credit'],
                balanceAfter: $balance,
            );
        }

        return $entries;
    }
}
