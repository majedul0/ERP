import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/format';
import { useCompanyBrand } from '@/modules/company';
import type { DistributorOption } from '@/modules/invoices';
import { create, edit, show } from '@/routes/distributors';

const headCell =
    'bg-coffee-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase';
const bodyCell = 'px-4 py-3 whitespace-nowrap text-coffee-900';

export default function Distributors({
    distributors,
}: {
    distributors: DistributorOption[];
}) {
    const brand = useCompanyBrand();
    const { currentTeam } = usePage().props;

    return (
        <>
            <Head title="Distributors" />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-bold text-coffee-900">
                    Distributors
                </h1>
                <Button asChild className="bg-coffee-600 hover:bg-coffee-700">
                    <Link href={create(currentTeam?.slug ?? '')}>
                        + Add Distributor
                    </Link>
                </Button>
            </div>

            <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[60rem] text-sm">
                        <thead>
                            <tr>
                                <th className={headCell}>ID</th>
                                <th className={headCell}>Name</th>
                                <th className={headCell}>Proprietor</th>
                                <th className={headCell}>Phone</th>
                                <th className={headCell}>Address</th>
                                <th className={`${headCell} text-right`}>
                                    Due
                                </th>
                                <th className={headCell}>
                                    <span className="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-coffee-100">
                            {distributors.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-10 text-center text-coffee-800/60"
                                    >
                                        No distributors yet.
                                    </td>
                                </tr>
                            )}

                            {distributors.map((distributor) => (
                                <tr
                                    key={distributor.id}
                                    className="transition-colors hover:bg-coffee-50/60"
                                >
                                    <td className={bodyCell}>
                                        {distributor.id}
                                    </td>
                                    <td className={`${bodyCell} font-medium`}>
                                        <Link
                                            href={show({
                                                current_team:
                                                    currentTeam?.slug ?? '',
                                                distributor: distributor.id,
                                            })}
                                            className="text-coffee-700 underline underline-offset-4 hover:text-coffee-900"
                                        >
                                            {distributor.name}
                                        </Link>
                                    </td>
                                    <td className={bodyCell}>
                                        {distributor.proprietorName ?? '—'}
                                    </td>
                                    <td className={bodyCell}>
                                        {distributor.phone ?? '—'}
                                    </td>
                                    <td className={bodyCell}>
                                        {distributor.fullAddress || '—'}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right font-medium tabular-nums`}
                                    >
                                        {formatMoney(
                                            distributor.balance,
                                            brand.currencySymbol,
                                        )}
                                    </td>
                                    <td className={bodyCell}>
                                        <Link
                                            href={edit({
                                                current_team:
                                                    currentTeam?.slug ?? '',
                                                distributor: distributor.id,
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
