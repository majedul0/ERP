import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/format';
import { useCompanyBrand } from '@/modules/company';
import type { Vendor } from '@/modules/vendors';
import { create, edit, show } from '@/routes/vendors';

const headCell =
    'bg-coffee-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase';
const bodyCell = 'px-4 py-3 whitespace-nowrap text-coffee-900';

export default function Vendors({
    vendors,
    totalPayable,
}: {
    vendors: Vendor[];
    totalPayable: number;
}) {
    const brand = useCompanyBrand();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    return (
        <>
            <Head title="Vendors" />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-coffee-900">
                        Vendors
                    </h1>
                    <p className="mt-1 font-display text-sm text-coffee-800/60">
                        Total payable:{' '}
                        <span className="font-semibold">
                            {formatMoney(totalPayable, brand.currencySymbol)}
                        </span>
                    </p>
                </div>
                <Button asChild className="bg-coffee-600 hover:bg-coffee-700">
                    <Link href={create(teamSlug)}>+ Add Vendor</Link>
                </Button>
            </div>

            <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[56rem] text-sm">
                        <thead>
                            <tr>
                                <th className={headCell}>Name</th>
                                <th className={headCell}>Proprietor</th>
                                <th className={headCell}>Phone</th>
                                <th className={headCell}>Address</th>
                                <th className={`${headCell} text-right`}>
                                    Payable
                                </th>
                                <th className={headCell}>
                                    <span className="sr-only">Edit</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-coffee-100">
                            {vendors.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-coffee-800/60"
                                    >
                                        No vendors yet.
                                    </td>
                                </tr>
                            )}

                            {vendors.map((vendor) => (
                                <tr
                                    key={vendor.id}
                                    className="transition-colors hover:bg-coffee-50/60"
                                >
                                    <td className={`${bodyCell} font-medium`}>
                                        <Link
                                            href={show({
                                                current_team: teamSlug,
                                                vendor: vendor.id,
                                            })}
                                            className="text-coffee-700 underline underline-offset-4 hover:text-coffee-900"
                                        >
                                            {vendor.name}
                                        </Link>
                                    </td>
                                    <td className={bodyCell}>
                                        {vendor.proprietorName ?? '—'}
                                    </td>
                                    <td className={bodyCell}>
                                        {vendor.phone ?? '—'}
                                    </td>
                                    <td className={bodyCell}>
                                        {vendor.fullAddress || '—'}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right font-medium tabular-nums`}
                                    >
                                        {formatMoney(
                                            vendor.balance,
                                            brand.currencySymbol,
                                        )}
                                    </td>
                                    <td className={bodyCell}>
                                        <Link
                                            href={edit({
                                                current_team: teamSlug,
                                                vendor: vendor.id,
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
