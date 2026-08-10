import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatAmount, formatMoney } from '@/lib/format';
import { useCompanyBrand } from '@/modules/company';
import { LowStockBadge } from '@/modules/raw-materials';
import type { StockLevel, StockSummary } from '@/modules/raw-materials';
import { edit } from '@/routes/materials';
import { create as newPurchase } from '@/routes/purchases';

const headCell =
    'bg-coffee-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase';
const bodyCell = 'px-4 py-3 whitespace-nowrap text-coffee-900';

function SummaryCard({
    label,
    value,
    tone = 'plain',
}: {
    label: string;
    value: string;
    tone?: 'plain' | 'warning' | 'danger';
}) {
    const toneClasses = {
        plain: 'text-coffee-900',
        warning: 'text-amber-700',
        danger: 'text-red-700',
    }[tone];

    return (
        <div className="rounded-lg border border-coffee-100 bg-white p-4 shadow-sm">
            <p className="text-xs font-semibold tracking-wide text-coffee-800/60 uppercase">
                {label}
            </p>
            <p
                className={`mt-1 text-2xl font-bold tabular-nums ${toneClasses}`}
            >
                {value}
            </p>
        </div>
    );
}

export default function StockLevels({
    levels,
    summary,
}: {
    levels: StockLevel[];
    summary: StockSummary;
}) {
    const brand = useCompanyBrand();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    return (
        <>
            <Head title="Stock Levels" />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-bold text-coffee-900">
                    Stock Levels
                </h1>
                <Button asChild className="bg-coffee-600 hover:bg-coffee-700">
                    <Link href={newPurchase(teamSlug)}>+ Record Purchase</Link>
                </Button>
            </div>

            <div className="mb-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <SummaryCard
                    label="Materials"
                    value={String(summary.materialCount)}
                />
                <SummaryCard
                    label="Low"
                    value={String(summary.lowCount)}
                    tone={summary.lowCount > 0 ? 'warning' : 'plain'}
                />
                <SummaryCard
                    label="Out of Stock"
                    value={String(summary.outOfStockCount)}
                    tone={summary.outOfStockCount > 0 ? 'danger' : 'plain'}
                />
                <SummaryCard
                    label="Stock Value"
                    value={formatMoney(
                        summary.totalValue,
                        brand.currencySymbol,
                    )}
                />
            </div>

            <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[52rem] text-sm">
                        <thead>
                            <tr>
                                <th className={headCell}>Material</th>
                                <th className={headCell}>Code</th>
                                <th className={`${headCell} text-right`}>
                                    In Stock
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Reorder At
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Shortfall
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Stock Value
                                </th>
                                <th className={headCell}>
                                    <span className="sr-only">Recount</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-coffee-100">
                            {levels.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-10 text-center text-coffee-800/60"
                                    >
                                        No raw materials registered yet.
                                    </td>
                                </tr>
                            )}

                            {levels.map((level) => (
                                <tr
                                    key={level.id}
                                    className={
                                        level.isLow
                                            ? 'bg-amber-50/60'
                                            : 'transition-colors hover:bg-coffee-50/60'
                                    }
                                >
                                    <td className={`${bodyCell} font-medium`}>
                                        <span className="flex items-center gap-2">
                                            {level.name}
                                            {level.isLow && <LowStockBadge />}
                                        </span>
                                    </td>
                                    <td className={bodyCell}>{level.code}</td>
                                    <td
                                        className={`${bodyCell} text-right font-medium tabular-nums`}
                                    >
                                        {formatAmount(level.stockQuantity)}{' '}
                                        {level.unitShort}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right tabular-nums`}
                                    >
                                        {level.reorderLevel > 0
                                            ? formatAmount(level.reorderLevel)
                                            : '—'}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right font-semibold text-amber-700 tabular-nums`}
                                    >
                                        {level.shortfall > 0
                                            ? formatAmount(level.shortfall)
                                            : '—'}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right tabular-nums`}
                                    >
                                        {formatMoney(
                                            level.stockValue,
                                            brand.currencySymbol,
                                        )}
                                    </td>
                                    <td className={bodyCell}>
                                        <Link
                                            href={edit({
                                                current_team: teamSlug,
                                                material: level.id,
                                            })}
                                            className="text-coffee-700 underline underline-offset-4 hover:text-coffee-900"
                                        >
                                            Recount
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
