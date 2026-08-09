import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/format';
import { useCompanyBrand } from '@/modules/company';
import type { DistributorOption } from '@/modules/invoices';
import { create, show } from '@/routes/distributors';

const headCell =
    'bg-ocean-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase';
const bodyCell = 'px-4 py-3 whitespace-nowrap text-ocean-900';

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
                <h1 className="text-xl font-bold text-ocean-900">
                    Distributors
                </h1>
                <Button asChild className="bg-ocean-600 hover:bg-ocean-700">
                    <Link href={create(currentTeam?.slug ?? '')}>
                        + Add Distributor
                    </Link>
                </Button>
            </div>

            <div className="overflow-hidden rounded-lg border border-ocean-100 bg-white shadow-sm">
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
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-ocean-100">
                            {distributors.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-ocean-800/60"
                                    >
                                        No distributors yet.
                                    </td>
                                </tr>
                            )}

                            {distributors.map((distributor) => (
                                <tr
                                    key={distributor.id}
                                    className="transition-colors hover:bg-ocean-50/60"
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
                                            className="text-ocean-700 underline underline-offset-4 hover:text-ocean-900"
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
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
