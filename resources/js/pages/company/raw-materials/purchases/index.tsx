import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/format';
import { useCompanyBrand } from '@/modules/company';
import type { MaterialPurchaseSummary } from '@/modules/raw-materials';
import { create, show } from '@/routes/purchases';

const headCell =
    'bg-coffee-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase';
const bodyCell = 'px-4 py-3 whitespace-nowrap text-coffee-900';

export default function Purchases({
    purchases,
}: {
    purchases: MaterialPurchaseSummary[];
}) {
    const brand = useCompanyBrand();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    return (
        <>
            <Head title="Material Purchases" />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-bold text-coffee-900">
                    Material Purchases
                </h1>
                <Button asChild className="bg-coffee-600 hover:bg-coffee-700">
                    <Link href={create(teamSlug)}>+ Record Purchase</Link>
                </Button>
            </div>

            <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[44rem] text-sm">
                        <thead>
                            <tr>
                                <th className={headCell}>Date</th>
                                <th className={headCell}>Supplier</th>
                                <th className={headCell}>Bill No.</th>
                                <th className={`${headCell} text-right`}>
                                    Materials
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Total
                                </th>
                                <th className={headCell}>
                                    <span className="sr-only">View</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-coffee-100">
                            {purchases.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-coffee-800/60"
                                    >
                                        No purchases recorded yet.
                                    </td>
                                </tr>
                            )}

                            {purchases.map((purchase) => (
                                <tr
                                    key={purchase.id}
                                    className="transition-colors hover:bg-coffee-50/60"
                                >
                                    <td className={bodyCell}>
                                        {purchase.purchasedAt}
                                    </td>
                                    <td className={`${bodyCell} font-medium`}>
                                        {purchase.supplierName}
                                    </td>
                                    <td className={bodyCell}>
                                        {purchase.reference ?? '—'}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right tabular-nums`}
                                    >
                                        {purchase.itemCount}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right font-medium tabular-nums`}
                                    >
                                        {formatMoney(
                                            purchase.totalAmount,
                                            brand.currencySymbol,
                                        )}
                                    </td>
                                    <td className={bodyCell}>
                                        <Link
                                            href={show({
                                                current_team: teamSlug,
                                                purchase: purchase.id,
                                            })}
                                            className="text-coffee-700 underline underline-offset-4 hover:text-coffee-900"
                                        >
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
