import { Head, Link, usePage } from '@inertiajs/react';
import { FileText, Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useCompanyBrand } from '@/modules/company';
import type { ChallanDetail } from '@/modules/invoices';
import { InvoiceDocumentHeader } from '@/modules/invoices';
import { show } from '@/routes/invoices';

const headCell =
    'bg-coffee-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase';
const bodyCell = 'px-4 py-3 text-coffee-900';

export default function Challan({ challan }: { challan: ChallanDetail }) {
    const brand = useCompanyBrand();
    const { currentTeam } = usePage().props;

    return (
        <>
            <Head title={`Challan ${challan.invoiceNumber}`} />

            {/* Screen-only controls; the print output is the challan alone. */}
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
                <h1 className="text-xl font-bold text-coffee-900">
                    Sales Invoice
                </h1>

                <div className="flex flex-wrap items-center gap-2">
                    <Button asChild variant="outline">
                        <Link
                            href={show({
                                current_team: currentTeam?.slug ?? '',
                                invoice: challan.id,
                            })}
                        >
                            <FileText className="size-4" />
                            Invoice
                        </Link>
                    </Button>

                    <Button
                        type="button"
                        onClick={() => window.print()}
                        className="bg-coffee-700 hover:bg-coffee-800"
                    >
                        <Printer className="size-4" />
                        Print
                    </Button>
                </div>
            </div>

            <article className="rounded-lg border border-coffee-100 bg-white p-6 shadow-sm print:border-0 print:p-0 print:shadow-none">
                <InvoiceDocumentHeader
                    brand={brand}
                    title="Challan"
                    invoiceNumber={challan.invoiceNumber}
                    soldAt={challan.soldAt}
                    distributor={challan.distributor}
                />

                <div className="mt-6 overflow-x-auto">
                    <table className="w-full min-w-[48rem] text-sm">
                        <thead>
                            <tr>
                                <th className={headCell}>ID</th>
                                <th className={headCell}>Product</th>
                                <th className={headCell}>CTN QTY</th>
                                <th className={headCell}>Total QTY</th>
                                <th className={headCell}>Remarks</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-coffee-100">
                            {challan.items.map((item) => (
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
                                    <td className={bodyCell}>
                                        {item.remarks ?? ''}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {challan.comment && (
                    <p className="mt-6 text-sm text-coffee-800/80">
                        <span className="font-bold text-coffee-900">
                            Comment:
                        </span>{' '}
                        {challan.comment}
                    </p>
                )}

                {/* A challan is signed on delivery, so it needs somewhere to sign. */}
                <div className="mt-16 flex flex-wrap justify-between gap-10 text-sm text-coffee-800/80">
                    {['Delivered by', 'Received by'].map((label) => (
                        <div key={label} className="min-w-56 flex-1">
                            <div className="border-t border-coffee-300 pt-2">
                                {label}
                            </div>
                        </div>
                    ))}
                </div>
            </article>
        </>
    );
}
