import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatAmount, formatMoney } from '@/lib/format';
import { useCompanyBrand } from '@/modules/company';
import type { MaterialPurchase } from '@/modules/raw-materials';
import { index } from '@/routes/purchases';

const headCell =
    'bg-coffee-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase';
const bodyCell = 'px-4 py-3 whitespace-nowrap text-coffee-900';

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs font-semibold tracking-wide text-coffee-800/60 uppercase">
                {label}
            </dt>
            <dd className="mt-0.5 text-coffee-900">{value}</dd>
        </div>
    );
}

export default function PurchaseDetail({
    purchase,
}: {
    purchase: MaterialPurchase;
}) {
    const brand = useCompanyBrand();
    const { currentTeam } = usePage().props;

    return (
        <>
            <Head title={`Purchase from ${purchase.supplierName}`} />

            <div className="mx-auto w-full max-w-4xl">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-bold text-coffee-900">
                        {purchase.supplierName}
                    </h1>
                    <Button asChild variant="outline">
                        <Link href={index(currentTeam?.slug ?? '')}>
                            All Purchases
                        </Link>
                    </Button>
                </div>

                <dl className="mb-6 grid gap-4 rounded-lg border border-coffee-100 bg-white p-5 text-sm shadow-sm sm:grid-cols-4">
                    <Detail label="Date" value={purchase.purchasedAt} />
                    <Detail
                        label="Bill No."
                        value={purchase.reference ?? '—'}
                    />
                    <Detail label="Recorded By" value={purchase.recordedBy} />
                    <Detail label="Note" value={purchase.note ?? '—'} />
                </dl>

                <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[40rem] text-sm">
                            <thead>
                                <tr>
                                    <th className={headCell}>Material</th>
                                    <th className={headCell}>Code</th>
                                    <th className={`${headCell} text-right`}>
                                        Quantity
                                    </th>
                                    <th className={`${headCell} text-right`}>
                                        Unit Cost
                                    </th>
                                    <th className={`${headCell} text-right`}>
                                        Line Total
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-coffee-100">
                                {purchase.items.map((item) => (
                                    <tr key={item.id}>
                                        <td
                                            className={`${bodyCell} font-medium`}
                                        >
                                            {item.materialName}
                                        </td>
                                        <td className={bodyCell}>
                                            {item.materialCode}
                                        </td>
                                        <td
                                            className={`${bodyCell} text-right tabular-nums`}
                                        >
                                            {formatAmount(item.quantity)}{' '}
                                            {item.unit}
                                        </td>
                                        <td
                                            className={`${bodyCell} text-right tabular-nums`}
                                        >
                                            {formatMoney(
                                                item.unitCost,
                                                brand.currencySymbol,
                                            )}
                                        </td>
                                        <td
                                            className={`${bodyCell} text-right tabular-nums`}
                                        >
                                            {formatMoney(
                                                item.lineTotal,
                                                brand.currencySymbol,
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr className="border-t-2 border-coffee-200 bg-coffee-50/60">
                                    <td
                                        className={`${bodyCell} font-semibold`}
                                        colSpan={4}
                                    >
                                        Total
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right text-base font-bold tabular-nums`}
                                    >
                                        {formatMoney(
                                            purchase.totalAmount,
                                            brand.currencySymbol,
                                        )}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}
