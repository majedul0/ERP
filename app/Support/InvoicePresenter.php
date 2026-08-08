<?php

namespace App\Support;

use App\Models\Distributor;
use App\Models\Invoice;

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
            'saleAt' => $invoice->sold_at->toIso8601String(),
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
            'soldAt' => $invoice->sold_at->toIso8601String(),
            'deliveryStatus' => $invoice->delivery_status->value,
            'deliveryStatusLabel' => $invoice->delivery_status->label(),
            'comment' => $invoice->comment,
            'schemeDescription' => $invoice->scheme_description,
            'schemeAmount' => $invoice->scheme_amount,
            'invoiceTotal' => $invoice->invoice_total,
            'discountTotal' => $invoice->discount_total,
            'previousDues' => $invoice->previous_dues,
            'totalAmount' => $invoice->total_amount,
            'createdBy' => $invoice->creator?->name,
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
     * @return array<string, mixed>
     */
    public static function distributor(Distributor $distributor): array
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
            'balance' => $distributor->balance,
        ];
    }
}
