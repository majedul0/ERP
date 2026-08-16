import { formatAmount } from '@/lib/format';
import type { CompanyBrand } from '@/modules/company';
import { InvoiceDocumentHeader, InvoiceTotals } from '@/modules/invoices';
import type { SalesReturnDetail } from '../types';

const headCell =
    'bg-coffee-500 px-3 py-2 text-left text-[11px] font-bold tracking-wide text-white uppercase print:py-1';
const bodyCell = 'px-3 py-2 text-coffee-900 print:py-1';

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
            <p className="mb-1 h-5 text-sm text-coffee-900">{name ?? ' '}</p>
            <p className="border-t border-coffee-900 pt-1 text-sm font-bold text-coffee-900">
                {label}
            </p>
        </div>
    );
}

/**
 * The printable return invoice.
 *
 * Deliberately the same document as a sales invoice — same masthead, same
 * table, same totals block, same signature foot — because it is handed to the
 * same person for the same reason: proof of what moved and what it was worth.
 * It reuses `InvoiceDocumentHeader` and `InvoiceTotals` rather than copying
 * them, so a layout fix or a changed company address lands on both.
 *
 * What it says is the opposite of an invoice: the goods came back, and the
 * figure at the foot is a credit rather than a charge. The account's running
 * balance is deliberately absent — this sheet is about one return, and the
 * statement is where the account is read.
 */
export default function ReturnDocument({
    brand,
    salesReturn,
}: {
    brand: CompanyBrand;
    salesReturn: SalesReturnDetail;
}) {
    return (
        <article className="rounded-lg border border-coffee-100 bg-white p-6 shadow-sm print:flex print:min-h-[26cm] print:flex-col print:rounded-none print:border-0 print:p-0 print:shadow-none">
            <InvoiceDocumentHeader
                brand={brand}
                title="Return Invoice"
                invoiceNumber={salesReturn.returnNumber}
                soldAt={salesReturn.returnedOn}
                dateLabel="Return Date"
                distributor={salesReturn.distributor}
                meta={
                    salesReturn.restock
                        ? []
                        : // Worth printing: it tells the warehouse the goods
                          // were credited but never went back on the shelf.
                          [{ label: 'Condition', value: 'Not restocked' }]
                }
            />

            <div className="mt-6 overflow-x-auto print:overflow-visible">
                <table className="w-full min-w-[40rem] text-sm print:min-w-0 print:text-xs">
                    <thead>
                        <tr>
                            <th className={headCell}>ID</th>
                            <th className={headCell}>Product</th>
                            <th className={headCell}>SKU</th>
                            <th className={`${headCell} text-right`}>
                                Quantity
                            </th>
                            <th className={`${headCell} text-right`}>
                                Unit Price
                            </th>
                            <th className={`${headCell} text-right`}>Amount</th>
                            <th className={headCell}>Remarks</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-coffee-100">
                        {salesReturn.items.map((item, index) => (
                            <tr key={item.id}>
                                <td className={bodyCell}>{index + 1}</td>
                                <td className={`${bodyCell} font-medium`}>
                                    {item.productName}
                                </td>
                                <td className={bodyCell}>
                                    {item.productSku ?? ''}
                                </td>
                                <td
                                    className={`${bodyCell} text-right tabular-nums`}
                                >
                                    {item.quantity}
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
                                <td className={bodyCell}>
                                    {item.remarks ?? ''}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="mt-4 flex flex-wrap items-start justify-between gap-6">
                <div className="max-w-xs flex-1">
                    {salesReturn.comment && (
                        <p className="text-sm text-coffee-800/80">
                            <span className="font-bold text-coffee-900">
                                Reason:
                            </span>{' '}
                            {salesReturn.comment}
                        </p>
                    )}
                </div>

                <InvoiceTotals
                    className="max-w-sm flex-1"
                    currencySymbol={brand.currencySymbol}
                    rows={[
                        {
                            label: 'Total Credit',
                            value: salesReturn.amount,
                            emphasis: true,
                            hint: 'Deducted from the outstanding balance',
                        },
                    ]}
                />
            </div>

            {/* mt-auto pins this to the foot of the printed sheet. */}
            <div className="mt-20 print:mt-auto print:pt-10">
                <div className="flex flex-wrap justify-between gap-10">
                    <SignatureLine
                        label="Received By"
                        name={salesReturn.createdBy}
                    />
                    <SignatureLine label="Authorized Signature" />
                </div>

                <div className="mt-10">
                    <SignatureLine label="Returned By" />
                </div>
            </div>
        </article>
    );
}
