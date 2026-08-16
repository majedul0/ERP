import { Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatAmount, formatMoney, formatSaleDate } from '@/lib/format';
import { useCan, useCompanyBrand } from '@/modules/company';
import type { SalesReturnRow } from '@/modules/sales-returns';
import { create, show } from '@/routes/returns';

const headCell =
    'bg-coffee-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase';
const bodyCell = 'px-4 py-3 whitespace-nowrap text-coffee-900';

export default function SalesReturns({
    returns,
    total,
}: {
    returns: SalesReturnRow[];
    total: number;
}) {
    const brand = useCompanyBrand();
    const can = useCan();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    return (
        <>
            <Head title="Sales Returns" />

            <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="mb-1 text-2xl font-bold text-coffee-900">
                        Sales Returns
                    </h1>
                    <p className="font-display text-sm text-coffee-800/60">
                        Goods sent back. Each one credits the distributor's
                        account and appears on their statement.
                    </p>
                </div>

                {can('return:manage') && (
                    <Button
                        asChild
                        className="bg-coffee-600 hover:bg-coffee-700"
                    >
                        <Link href={create(teamSlug)}>
                            <Plus className="size-4" />
                            Add New Return
                        </Link>
                    </Button>
                )}
            </div>

            <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[58rem] text-sm">
                        <thead>
                            <tr>
                                <th className={headCell}>Id</th>
                                <th className={headCell}>Distributor</th>
                                <th className={headCell}>Proprietor</th>
                                <th className={headCell}>Return Date</th>
                                <th className={`${headCell} text-right`}>
                                    Amount
                                </th>
                                <th className={headCell}>Stock</th>
                                <th className={headCell}>Comment</th>
                                <th className={headCell}>Details</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-coffee-100">
                            {returns.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-4 py-10 text-center text-coffee-800/60"
                                    >
                                        No returns recorded yet.
                                    </td>
                                </tr>
                            )}

                            {returns.map((item) => (
                                <tr
                                    key={item.id}
                                    className="transition-colors hover:bg-coffee-50/60"
                                >
                                    <td
                                        className={`${bodyCell} font-medium tabular-nums`}
                                    >
                                        {item.returnNumber}
                                    </td>
                                    <td className={bodyCell}>
                                        <Link
                                            href={item.distributorUrl}
                                            className="font-medium text-coffee-700 underline underline-offset-4 hover:text-coffee-900"
                                        >
                                            {item.distributorName}
                                        </Link>
                                    </td>
                                    <td className={bodyCell}>
                                        {item.proprietorName ?? '—'}
                                    </td>
                                    <td className={bodyCell}>
                                        {formatSaleDate(item.returnedOn)}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right font-medium text-emerald-700 tabular-nums`}
                                    >
                                        {formatAmount(item.amount)}
                                    </td>
                                    <td className={bodyCell}>
                                        {item.restock ? (
                                            'Restocked'
                                        ) : (
                                            <span className="text-red-700">
                                                Not restocked
                                            </span>
                                        )}
                                    </td>
                                    <td className={bodyCell}>
                                        {item.comment ?? '—'}
                                    </td>
                                    <td className={bodyCell}>
                                        <Link
                                            href={show({
                                                current_team: teamSlug,
                                                return: item.id,
                                            })}
                                            className="text-coffee-700 underline underline-offset-4 hover:text-coffee-900"
                                        >
                                            Detail
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <p className="mt-4 text-right text-sm text-coffee-800/70">
                Total credited{' '}
                <span className="ml-2 text-base font-bold text-coffee-900 tabular-nums">
                    {formatMoney(total, brand.currencySymbol)}
                </span>
            </p>
        </>
    );
}
