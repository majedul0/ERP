import { Head, router, usePage } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatAmount, formatSaleDateTime } from '@/lib/format';
import { CompanyLogo, useCompanyBrand } from '@/modules/company';
import type { DeliveryStatusOption, InvoiceDetail } from '@/modules/invoices';
import { InvoiceTotals } from '@/modules/invoices';
import { update } from '@/routes/invoices/status';

const headCell =
    'bg-ocean-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase';
const bodyCell = 'px-4 py-3 text-ocean-900';

type Props = {
    invoice: InvoiceDetail;
    statuses: DeliveryStatusOption[];
};

export default function ShowInvoice({ invoice, statuses }: Props) {
    const brand = useCompanyBrand();
    const { currentTeam } = usePage().props;

    const setStatus = (value: string) =>
        router.patch(
            update({
                current_team: currentTeam?.slug ?? '',
                invoice: invoice.id,
            }).url,
            { delivery_status: value },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title={`Invoice ${invoice.invoiceNumber}`} />

            {/* Controls are screen-only; the printed page is the invoice. */}
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
                <h1 className="text-xl font-bold text-ocean-900">
                    Sales Invoice
                </h1>

                <div className="flex flex-wrap items-center gap-2">
                    {statuses.map((status) => (
                        <Button
                            key={status.value}
                            type="button"
                            variant={
                                invoice.deliveryStatus === status.value
                                    ? 'default'
                                    : 'outline'
                            }
                            onClick={() => setStatus(status.value)}
                            className={
                                invoice.deliveryStatus === status.value
                                    ? 'bg-ocean-600 hover:bg-ocean-700'
                                    : undefined
                            }
                        >
                            {status.label}
                        </Button>
                    ))}

                    <Button
                        type="button"
                        onClick={() => window.print()}
                        className="bg-ocean-700 hover:bg-ocean-800"
                    >
                        <Printer className="size-4" />
                        Print
                    </Button>
                </div>
            </div>

            <article className="rounded-lg border border-ocean-100 bg-white p-6 shadow-sm print:border-0 print:p-0 print:shadow-none">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <CompanyLogo brand={brand} />
                    <div className="text-right text-sm text-ocean-800/80">
                        <p>{brand.name}</p>
                    </div>
                </header>

                <div className="mt-8 text-center">
                    <h2 className="text-2xl font-bold text-ocean-900">
                        Invoice
                    </h2>
                    <p className="mt-1 font-semibold text-ocean-800">
                        #{invoice.invoiceNumber}
                    </p>
                </div>

                <div className="mt-8 flex flex-wrap justify-between gap-6">
                    <div>
                        <p className="text-sm font-bold text-ocean-900">
                            Distributor
                        </p>
                        <p className="mt-1 text-lg font-semibold text-ocean-800 underline underline-offset-4">
                            {invoice.distributor.name}
                        </p>
                        {invoice.distributor.fullAddress && (
                            <p className="text-sm text-ocean-800/80">
                                {invoice.distributor.fullAddress}
                            </p>
                        )}
                        {invoice.distributor.phone && (
                            <p className="text-sm text-ocean-800/80">
                                {invoice.distributor.phone}
                            </p>
                        )}
                    </div>

                    <div className="text-right text-sm text-ocean-800/80">
                        <p>
                            <span className="font-bold text-ocean-900">
                                Sale Date:
                            </span>{' '}
                            {formatSaleDateTime(invoice.soldAt)}
                        </p>
                        <p className="mt-1">
                            <span className="font-bold text-ocean-900">
                                Status:
                            </span>{' '}
                            {invoice.deliveryStatusLabel}
                        </p>
                        {invoice.createdBy && (
                            <p className="mt-1">
                                <span className="font-bold text-ocean-900">
                                    Issued by:
                                </span>{' '}
                                {invoice.createdBy}
                            </p>
                        )}
                    </div>
                </div>

                <div className="mt-6 overflow-x-auto">
                    <table className="w-full min-w-[56rem] text-sm">
                        <thead>
                            <tr>
                                <th className={headCell}>ID</th>
                                <th className={headCell}>Product</th>
                                <th className={headCell}>CTN QTY</th>
                                <th className={headCell}>Total QTY</th>
                                <th className={`${headCell} text-right`}>
                                    Unit Price
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Amount
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Discount
                                </th>
                                <th className={headCell}>Remarks</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-ocean-100">
                            {invoice.items.map((item) => (
                                <tr key={item.id}>
                                    <td className={bodyCell}>
                                        {item.lineNumber}
                                    </td>
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

                <InvoiceTotals
                    className="mt-4"
                    currencySymbol={brand.currencySymbol}
                    rows={[
                        {
                            label: 'Invoice Total',
                            value: invoice.invoiceTotal,
                        },
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
                        { label: 'Previous Dues', value: invoice.previousDues },
                        {
                            label: 'Total Amount',
                            value: invoice.totalAmount,
                            emphasis: true,
                        },
                    ]}
                />

                {invoice.comment && (
                    <p className="mt-6 text-sm text-ocean-800/80">
                        <span className="font-bold text-ocean-900">
                            Comment:
                        </span>{' '}
                        {invoice.comment}
                    </p>
                )}
            </article>
        </>
    );
}
