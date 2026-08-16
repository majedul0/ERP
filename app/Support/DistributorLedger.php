<?php

namespace App\Support;

use App\Data\LedgerEntry;
use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SalesReturn;

/**
 * A distributor's running account: every invoice they were charged, every
 * payment they made and every return they sent back, in the order those things
 * happened.
 *
 * A return is a credit, exactly like a payment: no money arrived, but the goods
 * did, and the distributor stops owing for them. It is not a negative invoice —
 * treating it as one would let a return quietly reduce what the sales figures
 * say was sold on the day the goods went out.
 *
 * This is the single ordering in the system. The statement renders it, and
 * `RecalculateDistributorBalance` writes the same walk back onto the invoices
 * — so a statement can never disagree with the dues printed on an invoice.
 *
 * Ordering is by **document date**, then by when the record was actually
 * entered, then by type and id to break a genuine tie.
 *
 * Entry time is the tie-breaker because on a shared day it is the only honest
 * evidence of what happened first. Forcing invoices ahead of payments — the
 * previous rule — reads correctly when a distributor settles the day's invoice,
 * but silently reorders the opposite and far more common case: money paid in
 * advance, then goods invoiced against it the same day. That showed an advance
 * arriving after the invoices it had already covered, and every invoice in
 * between carried the wrong figure forward.
 *
 * A backdated document still sorts by its own date, which is the point of
 * having a date separate from `created_at`.
 *
 * The account is a plain running total: balance, plus what each invoice
 * charges, less what each payment settles. Nothing typed on an invoice form
 * alters it — see the loop below.
 *
 * Timestamps are stored to the second, so two records entered within the same
 * second tie. That tie falls back to invoice-before-payment and then id, which
 * is stable rather than arbitrary — the account always renders the same way
 * twice. Real entry is minutes apart, so the fallback is a formality; if it
 * ever stops being one, the fix is a monotonic ordering column, not a finer
 * guess here.
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

        $returns = SalesReturn::query()
            ->where('distributor_id', $distributor->id)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get();

        $rows = [];

        foreach ($invoices as $invoice) {
            $rows[] = [
                'sortDate' => $invoice->sold_at->format('Y-m-d'),
                'sortEnteredAt' => $invoice->created_at?->format('Y-m-d H:i:s.u') ?? '',
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
            ];
        }

        foreach ($payments as $payment) {
            $rows[] = [
                'sortDate' => $payment->paid_on->format('Y-m-d'),
                'sortEnteredAt' => $payment->created_at?->format('Y-m-d H:i:s.u') ?? '',
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
            ];
        }

        foreach ($returns as $return) {
            $rows[] = [
                'sortDate' => $return->returned_on->format('Y-m-d'),
                'sortEnteredAt' => $return->created_at?->format('Y-m-d H:i:s.u') ?? '',
                'sortGroup' => 2,
                'sortId' => $return->id,
                'type' => 'return',
                'id' => $return->id,
                'occurredOn' => $return->returned_on,
                'reference' => $return->return_number,
                'description' => $return->comment ?: 'Goods returned',
                'debit' => 0,
                // A credit, not a negative charge: what came back reduces what
                // is owed, and the invoice that sold it stays as it was.
                'credit' => $return->return_total,
            ];
        }

        // Group and id only break a tie between two records entered in the same
        // microsecond, which keeps the order stable rather than arbitrary.
        usort($rows, fn (array $a, array $b) => [$a['sortDate'], $a['sortEnteredAt'], $a['sortGroup'], $a['sortId']]
            <=> [$b['sortDate'], $b['sortEnteredAt'], $b['sortGroup'], $b['sortId']]);

        $balance = 0;
        $entries = [];

        foreach ($rows as $row) {
            /*
             * A plain running total, and nothing else: each invoice adds what
             * it charges, each payment takes off what was paid.
             *
             * A figure typed into Previous Dues on an invoice deliberately does
             * not appear here. That field decides what one sheet of paper says;
             * it is not a restatement of the account. Letting it restart the
             * running balance meant a number entered for a customer's benefit
             * silently rewrote what they owed, and an accidental one wiped out
             * an advance they had actually paid.
             */
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
