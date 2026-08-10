<?php

namespace App\Support;

use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Carbon;

/**
 * Shapes invoices for the React side.
 *
 * One place, so the dashboard row and the invoice screen cannot disagree about
 * what an amount or a status is called. Amounts are whole integers in the
 * database and stay whole integers in the payload.
 */
final class InvoicePresenter
{
    /**
     * A row in a list — the dashboard's Today's Sales, for instance.
     *
     * @return array<string, mixed>
     */
    public static function summary(Invoice $invoice, string $teamSlug): array
    {
        return [
            'id' => $invoice->id,
            'invoiceNumber' => $invoice->invoice_number,
            'distributorName' => $invoice->distributor->name,
            'distributorUrl' => null,
            'proprietorName' => $invoice->distributor->proprietor_name ?? '',
            // The date the sale is booked under, which is all the form asks
            // for — it carries no time of day, so nothing here should print
            // one.
            'saleDate' => $invoice->sold_at->toDateString(),
            // When the invoice was actually written. This is the only real
            // clock reading an invoice has.
            'createdAt' => $invoice->created_at?->toIso8601String(),
            'amount' => $invoice->total_amount,
            'deliveryStatus' => $invoice->delivery_status->value,
            'detailUrl' => "/{$teamSlug}/sales/invoices/{$invoice->id}",
        ];
    }

    /**
     * The full invoice, for the invoice screen and printing.
     *
     * @return array<string, mixed>
     */
    public static function detail(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoiceNumber' => $invoice->invoice_number,
            'soldAt' => $invoice->sold_at->toDateString(),
            'createdAt' => $invoice->created_at?->toIso8601String(),
            'deliveryStatus' => $invoice->delivery_status->value,
            'deliveryStatusLabel' => $invoice->delivery_status->label(),
            'comment' => $invoice->comment,
            'schemeDescription' => $invoice->scheme_description,
            'schemeAmount' => $invoice->scheme_amount,
            'invoiceTotal' => $invoice->invoice_total,
            'discountTotal' => $invoice->discount_total,
            /*
             * What this invoice prints: the typed figure when there is one,
             * otherwise what the account said before this sale. The account
             * itself is untouched by the choice — see DistributorLedger.
             */
            'previousDues' => $invoice->previous_dues_override ?? $invoice->previous_dues,
            /** What the account actually said, for the edit form to fall back to. */
            'accountPreviousDues' => $invoice->previous_dues,
            /*
             * Null when this invoice follows the account, a figure when someone
             * pinned it. The edit form needs the difference: seeding the field
             * as "edited" whenever it has a value would re-pin every invoice
             * that was only ever following along.
             */
            'previousDuesOverride' => $invoice->previous_dues_override,

            /*
             * Print this one without the running account on it.
             *
             * The figures above are unchanged and the statement still uses
             * them, so hiding only decides what the paper shows. `netAmount` is
             * what this invoice alone comes to — the same figure the ledger
             * charges — and is what the printed total falls back to.
             */
            'hidePreviousDues' => $invoice->hide_previous_dues,
            'netAmount' => $invoice->invoice_total
                - $invoice->discount_total
                - $invoice->scheme_amount,

            'totalAmount' => $invoice->total_amount,
            'createdBy' => $invoice->creator?->name,
            'payments' => self::paymentsSettling($invoice),
            'distributor' => self::distributor($invoice->distributor),
            'items' => $invoice->items->map(fn ($item) => [
                'id' => $item->id,
                'lineNumber' => $item->line_number,
                'productId' => $item->product_id,
                'productName' => $item->product_name,
                'productSku' => $item->product_sku,
                'cartonQuantity' => $item->carton_quantity,
                'totalQuantity' => $item->total_quantity,
                'unitPrice' => $item->unit_price,
                'amount' => $item->amount,
                'discount' => $item->discount,
                'remarks' => $item->remarks,
            ])->all(),
        ];
    }

    /**
     * The payments that settled this invoice, for the note printed beside the
     * totals.
     *
     * Payments are made against a running account, not against one invoice, so
     * "settling this one" means everything received after it and before the
     * next invoice to the same distributor. That is precisely the money the
     * distributor handed over while this invoice was the outstanding one.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function paymentsSettling(Invoice $invoice): array
    {
        $nextInvoiceDate = Invoice::query()
            ->where('distributor_id', $invoice->distributor_id)
            ->whereKeyNot($invoice->id)
            ->where(fn ($query) => $query
                ->where('sold_at', '>', $invoice->sold_at)
                ->orWhere(fn ($tie) => $tie
                    ->where('sold_at', $invoice->sold_at)
                    ->where('id', '>', $invoice->id)))
            ->orderBy('sold_at')
            ->orderBy('id')
            ->value('sold_at');

        return Payment::query()
            ->where('distributor_id', $invoice->distributor_id)
            ->with('bank')
            ->whereDate('paid_on', '>=', $invoice->sold_at->toDateString())
            ->when($nextInvoiceDate, fn ($query) => $query->whereDate(
                'paid_on',
                '<',
                Carbon::parse($nextInvoiceDate)->toDateString(),
            ))
            ->orderBy('paid_on')
            ->orderBy('id')
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                // `paid_on` is a date column — a payment is booked on a day,
                // not at a moment. Sending a timestamp would invite a
                // timezone shift onto a figure that has no time in it.
                'paidOn' => $payment->paid_on->toDateString(),
                'bankName' => $payment->bank?->name,
                'amount' => $payment->amount,
                'comment' => $payment->comment,
            ])
            ->all();
    }

    /**
     * The delivery note.
     *
     * A challan travels with the goods and is signed by whoever receives
     * them, so it carries quantities and nothing priced — no unit price, no
     * amount, no discount, no balance. That is not styling: the money is
     * absent from the payload, so no future edit to the page can put a price
     * in front of a delivery driver or a shopkeeper's assistant.
     *
     * @return array<string, mixed>
     */
    public static function challan(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoiceNumber' => $invoice->invoice_number,
            'soldAt' => $invoice->sold_at->toDateString(),
            'createdAt' => $invoice->created_at?->toIso8601String(),
            'deliveryStatus' => $invoice->delivery_status->value,
            'deliveryStatusLabel' => $invoice->delivery_status->label(),
            'comment' => $invoice->comment,

            // Contact details only. The full distributor payload carries an
            // outstanding balance, which has no business on a delivery note.
            'distributor' => self::distributorContact($invoice->distributor),
            'items' => $invoice->items->map(fn ($item) => [
                'id' => $item->id,
                'lineNumber' => $item->line_number,
                'productName' => $item->product_name,
                'productSku' => $item->product_sku,
                'cartonQuantity' => $item->carton_quantity,
                'totalQuantity' => $item->total_quantity,
                'remarks' => $item->remarks,
            ])->all(),
        ];
    }

    /**
     * Who the distributor is and where to find them. No money.
     *
     * @return array<string, mixed>
     */
    public static function distributorContact(Distributor $distributor): array
    {
        return [
            'id' => $distributor->id,
            'name' => $distributor->name,
            'proprietorName' => $distributor->proprietor_name,
            'phone' => $distributor->phone,
            'address' => $distributor->address,
            'thana' => $distributor->thana,
            'district' => $distributor->district,
            'division' => $distributor->division,
            'fullAddress' => $distributor->fullAddress(),
        ];
    }

    /**
     * Contact details plus the outstanding balance, for priced screens.
     *
     * @return array<string, mixed>
     */
    public static function distributor(Distributor $distributor): array
    {
        return [
            ...self::distributorContact($distributor),
            'balance' => $distributor->balance,
        ];
    }
}
