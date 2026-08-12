import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatMoney, formatSaleDate } from '@/lib/format';
import { useCompanyBrand } from '@/modules/company';
import type { VendorBill } from '@/modules/vendors';
import { create, edit } from '@/routes/bills';

const headCell =
    'bg-coffee-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase';
const bodyCell = 'px-4 py-3 whitespace-nowrap text-coffee-900';

export default function VendorBills({
    bills,
    total,
}: {
    bills: VendorBill[];
    total: number;
}) {
    const brand = useCompanyBrand();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    return (
        <>
            <Head title="Vendor Bills" />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-coffee-900">
                        Vendor Bills
                    </h1>
                    <p className="mt-1 font-display text-sm text-coffee-800/60">
                        Billed to date:{' '}
                        <span className="font-semibold">
                            {formatMoney(total, brand.currencySymbol)}
                        </span>
                    </p>
                </div>
                <Button asChild className="bg-coffee-600 hover:bg-coffee-700">
                    <Link href={create(teamSlug)}>+ Add Bill</Link>
                </Button>
            </div>

            <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[52rem] text-sm">
                        <thead>
                            <tr>
                                <th className={headCell}>Date</th>
                                <th className={headCell}>Vendor</th>
                                <th className={headCell}>Bill No.</th>
                                <th className={headCell}>Description</th>
                                <th className={`${headCell} text-right`}>
                                    Amount
                                </th>
                                <th className={headCell}>
                                    <span className="sr-only">Edit</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-coffee-100">
                            {bills.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-coffee-800/60"
                                    >
                                        No bills recorded yet.
                                    </td>
                                </tr>
                            )}

                            {bills.map((bill) => (
                                <tr
                                    key={bill.id}
                                    className="transition-colors hover:bg-coffee-50/60"
                                >
                                    <td className={bodyCell}>
                                        {formatSaleDate(bill.billedOn)}
                                    </td>
                                    <td className={`${bodyCell} font-medium`}>
                                        <Link
                                            href={bill.vendorUrl}
                                            className="text-coffee-700 underline underline-offset-4 hover:text-coffee-900"
                                        >
                                            {bill.vendorName}
                                        </Link>
                                    </td>
                                    <td className={bodyCell}>
                                        {bill.reference ?? '—'}
                                    </td>
                                    <td className={bodyCell}>
                                        {bill.description ?? ''}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right font-medium tabular-nums`}
                                    >
                                        {formatMoney(
                                            bill.amount,
                                            brand.currencySymbol,
                                        )}
                                    </td>
                                    <td className={bodyCell}>
                                        <Link
                                            href={edit({
                                                current_team: teamSlug,
                                                bill: bill.id,
                                            })}
                                            className="text-coffee-700 underline underline-offset-4 hover:text-coffee-900"
                                        >
                                            Edit
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
