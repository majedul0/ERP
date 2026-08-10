import { formatAmount, formatMoney, formatSaleDate } from '@/lib/format';
import type { CompanyBrand } from '@/modules/company';
import type { InvoicePayment } from '@/modules/payments';
import type { InvoiceDetail } from '../types';
import InvoiceDocumentHeader from './invoice-document-header';
import InvoiceTotals from './invoice-totals';

const headCell =
    'bg-coffee-500 px-3 py-2 text-left text-[11px] font-bold tracking-wide text-white uppercase print:py-1';
const bodyCell = 'px-3 py-2 text-coffee-900 print:py-1';

function PaymentNote({
    payments,
    currencySymbol,
}: {
    payments: InvoicePayment[];
    currencySymbol: string;
}) {
    return (
        <div className="text-sm text-coffee-900 italic">
            {payments.map((payment) => (
                <p key={payment.id} className="mb-1">
                    {payment.bankName ?? 'Payment'} payment on{' '}
                    {formatSaleDate(payment.paidOn)} for{' '}
                    <span className="font-semibold">
                        {formatMoney(payment.amount, currencySymbol)}
                    </span>
                    .
                </p>
            ))}
        </div>
    );
}

function SignatureLine({
    label,
    name,
}: {
    label: string;
    name?: string | null;
}) {
    return (
        <div className="min-w-48">
            {/* The name sits above the rule so the rule reads as the place to
                sign, exactly as it does on the paper form. */}
            <p className="mb-1 h-5 text-sm text-coffee-900">{name ?? ' '}</p>
            <p className="border-t border-coffee-900 pt-1 text-sm font-bold text-coffee-900">
                {label}
            </p>
        </div>
    );
}

/**
 * The printable invoice.
 *
 * Laid out for a single A4 sheet: a tight table, totals stacked on the right,
 * any payments noted on the left beside them, and the signature block anchored
 * at the foot. `print:` variants shrink the padding so a long invoice still
 * fits, and the screen border and shadow disappear on paper.
 */
export default function InvoiceDocument({
    brand,
    invoice,
}: {
    brand: CompanyBrand;
    invoice: InvoiceDetail;
}) {
    return (
        <article className="rounded-lg border border-coffee-100 bg-white p-6 shadow-sm print:flex print:min-h-[26cm] print:flex-col print:rounded-none print:border-0 print:p-0 print:shadow-none">
            <InvoiceDocumentHeader
                brand={brand}
                title="Invoice"
                invoiceNumber={invoice.invoiceNumber}
                soldAt={invoice.soldAt}
                distributor={invoice.distributor}
                meta={
                    invoice.deliveryStatus === 'pending'
                        ? []
                        : [
                              {
                                  label: 'Status',
                                  value: invoice.deliveryStatusLabel,
                              },
                          ]
                }
            />

            <div className="mt-6 overflow-x-auto print:overflow-visible">
                <table className="w-full min-w-[48rem] text-sm print:min-w-0 print:text-xs">
                    <thead>
                        <tr>
                            <th className={headCell}>ID</th>
                            <th className={headCell}>Product</th>
                            <th className={headCell}>CTN QTY</th>
                            <th className={headCell}>Total QTY</th>
                            <th className={`${headCell} text-right`}>
                                Unit Price
                            </th>
                            <th className={`${headCell} text-right`}>Amount</th>
                            <th className={`${headCell} text-right`}>
                                Discount
                            </th>
                            <th className={headCell}>Remarks</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-coffee-100">
                        {invoice.items.map((item) => (
                            <tr key={item.id}>
                                <td className={bodyCell}>{item.lineNumber}</td>
                                <td className={`${bodyCell} font-medium`}>
                                    {item.productName}
                                </td>
                                <td className={bodyCell}>
                                    {item.cartonQuantity}
                                </td>
                                <td className={bodyCell}>
                                    {item.totalQuantity}
                                </td>
                                <td
                                    className={`${bodyCell} text-right tabular-nums`}
                                >
                                    {formatAmount(item.unitPrice)}
                                </td>
                                <td
                                    className={`${bodyCell} text-right tabular-nums`}
                                >
                                    {formatAmount(item.amount)}
                                </td>
                                <td
                                    className={`${bodyCell} text-right tabular-nums`}
                                >
                                    {formatAmount(item.discount)}
                                </td>
                                <td className={bodyCell}>
                                    {item.remarks ?? ''}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Payments sit level with the totals they settle. */}
            <div className="mt-4 flex flex-wrap items-start justify-between gap-6">
                <div className="max-w-xs flex-1">
                    {invoice.payments.length > 0 && (
                        <PaymentNote
                            payments={invoice.payments}
                            currencySymbol={brand.currencySymbol}
                        />
                    )}
                </div>

                <InvoiceTotals
                    className="max-w-sm flex-1"
                    currencySymbol={brand.currencySymbol}
                    rows={[
                        { label: 'Invoice Total', value: invoice.invoiceTotal },
                        {
                            label: 'Discount Total',
                            value: invoice.discountTotal,
                        },
                        ...(invoice.schemeAmount > 0
                            ? [
                                  {
                                      label:
                                          invoice.schemeDescription ?? 'Scheme',
                                      value: invoice.schemeAmount,
                                  },
                              ]
                            : []),
                        /*
                         * Hidden invoices print as a clean bill: no Previous
                         * Dues line, and a total that is this invoice alone.
                         * The distributor's account is untouched by either —
                         * the statement still carries the same figures.
                         */
                        ...(invoice.hidePreviousDues
                            ? []
                            : [
                                  {
                                      label: 'Previous Dues',
                                      value: invoice.previousDues,
                                  },
                              ]),
                        {
                            label: 'Total Amount',
                            value: invoice.hidePreviousDues
                                ? invoice.netAmount
                                : invoice.totalAmount,
                            emphasis: true,
                        },
                    ]}
                />
            </div>

            {invoice.comment && (
                <p className="mt-6 text-sm text-coffee-800/80">
                    <span className="font-bold text-coffee-900">Comment:</span>{' '}
                    {invoice.comment}
                </p>
            )}

            {/* mt-auto pins this to the foot of the printed sheet. */}
            <div className="mt-20 print:mt-auto print:pt-10">
                <div className="flex flex-wrap justify-between gap-10">
                    <SignatureLine
                        label="Prepared By"
                        name={invoice.createdBy}
                    />
                    <SignatureLine label="Authorized Signature" />
                </div>

                <div className="mt-10">
                    <SignatureLine label="Received By" />
                </div>
            </div>
        </article>
    );
}
