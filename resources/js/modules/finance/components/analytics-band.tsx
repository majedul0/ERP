import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { formatMoney } from '@/lib/format';
import { mergeQuery } from '../query';
import type { FinancialAnalytics, FinancialReport } from '../types';
import { financeSeriesColor } from './charts/chart-palette';
import { ExpenseDonut } from './charts/expense-donut';
import { RevenueBreakdown } from './charts/revenue-breakdown';
import { TrendChart } from './charts/trend-chart';

const selectClasses =
    'h-9 rounded-md border border-coffee-200 bg-white px-3 text-sm text-coffee-900 shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none';

const card =
    'rounded-xl border border-coffee-100 bg-white p-5 shadow-sm print:break-inside-avoid';

/**
 * A headline figure with the colour of the line it belongs to.
 *
 * The swatch is what carries identity between the tile and the chart; the text
 * stays in ink, because a series hue is not a legible text colour.
 */
function StatTile({
    label,
    value,
    color,
    hint,
}: {
    label: string;
    value: string;
    color: string;
    hint?: string;
}) {
    return (
        <div className={card}>
            <div className="flex items-center gap-2">
                <span
                    aria-hidden="true"
                    className="size-2.5 rounded-sm"
                    style={{ backgroundColor: color }}
                />
                <p className="text-xs font-semibold tracking-wide text-coffee-800/60 uppercase">
                    {label}
                </p>
            </div>
            <p className="mt-1.5 text-2xl font-bold text-coffee-900">{value}</p>
            {hint && <p className="mt-1 text-xs text-coffee-800/60">{hint}</p>}
        </div>
    );
}

/**
 * The analytics band above the report: three figures, a trend, and where the
 * money went.
 *
 * It carries its own period control rather than sharing the report's below.
 * They answer different questions — the report states one period exactly, for
 * reconciling; this one needs a run of months before a line means anything, and
 * a chart of a single month is one dot. Each section names its own period so
 * the two are never mistaken for each other.
 */
export function AnalyticsBand({
    analytics,
    yearOptions,
    standing,
    currencySymbol,
    reportUrl,
}: {
    analytics: FinancialAnalytics;
    yearOptions: number[];
    /**
     * Balances as of today, from the report below — one computation, two
     * renderings, so the card and the report cannot state different dues.
     */
    standing: FinancialReport['standing'];
    currencySymbol: string;
    reportUrl: string;
}) {
    const [showTable, setShowTable] = useState(false);
    const money = (amount: number) => formatMoney(amount, currencySymbol);

    const apply = (granularity: string, year: number) =>
        router.get(
            reportUrl,
            mergeQuery({ granularity, analytics_year: year }),
            { preserveState: true, preserveScroll: true, only: ['analytics'] },
        );

    const year = Number(analytics.period.from.slice(0, 4));

    return (
        <section className="mb-8 space-y-4">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="text-lg font-bold text-coffee-900">
                        Revenue &amp; expenses
                    </h2>
                    <p className="text-sm text-coffee-800/60">
                        {analytics.period.label}
                    </p>
                </div>

                {/* Filters in one row, above everything they scope. */}
                <div className="flex flex-wrap items-end gap-2 print:hidden">
                    <div className="grid gap-1">
                        <Label htmlFor="granularity" className="text-xs">
                            View
                        </Label>
                        <select
                            id="granularity"
                            className={selectClasses}
                            value={analytics.granularity}
                            onChange={(event) =>
                                apply(event.target.value, year)
                            }
                        >
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>

                    {analytics.granularity === 'monthly' && (
                        <div className="grid gap-1">
                            <Label htmlFor="analytics-year" className="text-xs">
                                Year
                            </Label>
                            <select
                                id="analytics-year"
                                className={selectClasses}
                                value={year}
                                onChange={(event) =>
                                    apply(
                                        analytics.granularity,
                                        Number(event.target.value),
                                    )
                                }
                            >
                                {yearOptions.map((option) => (
                                    <option key={option} value={option}>
                                        {option}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <StatTile
                    label="Revenue"
                    value={money(analytics.totals.revenue)}
                    color={financeSeriesColor.revenue}
                    hint="Charged to distributors, less returns"
                />
                <StatTile
                    label="Expenses"
                    value={money(analytics.totals.expenses)}
                    color={financeSeriesColor.expenses}
                    hint="Expenses plus vendor bills"
                />
                <StatTile
                    label="Net"
                    value={money(analytics.totals.net)}
                    color={financeSeriesColor.net}
                    hint="Revenue less expenses — not profit; see below"
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-[3fr_2fr]">
                <div className={card}>
                    <h3 className="mb-3 text-base font-bold text-coffee-900">
                        {analytics.granularity === 'yearly'
                            ? 'Year by year'
                            : 'Month by month'}
                    </h3>

                    <TrendChart
                        buckets={analytics.buckets}
                        currencySymbol={currencySymbol}
                    />

                    <div className="mt-3 print:hidden">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setShowTable(!showTable)}
                        >
                            {showTable ? 'Hide table' : 'Show table'}
                        </Button>
                    </div>

                    {/* Every figure the chart encodes, reachable without
                        hovering anything — and the answer to the palette's
                        contrast warning, not a dismissal of it. */}
                    {showTable && (
                        <div className="mt-2 overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-coffee-100 text-left text-xs text-coffee-800/60 uppercase">
                                        <th className="py-2 pr-3 font-semibold">
                                            Period
                                        </th>
                                        <th className="py-2 pr-3 text-right font-semibold">
                                            Revenue
                                        </th>
                                        <th className="py-2 pr-3 text-right font-semibold">
                                            Expenses
                                        </th>
                                        <th className="py-2 text-right font-semibold">
                                            Net
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-coffee-100">
                                    {analytics.buckets.map((bucket) => (
                                        <tr key={bucket.key}>
                                            <td className="py-1.5 pr-3 text-coffee-900">
                                                {bucket.label}
                                            </td>
                                            <td className="py-1.5 pr-3 text-right text-coffee-900 tabular-nums">
                                                {money(bucket.revenue)}
                                            </td>
                                            <td className="py-1.5 pr-3 text-right text-coffee-900 tabular-nums">
                                                {money(bucket.expenses)}
                                            </td>
                                            <td className="py-1.5 text-right font-medium text-coffee-900 tabular-nums">
                                                {money(bucket.net)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <div className="space-y-4">
                    <div className={card}>
                        <h3 className="mb-3 text-base font-bold text-coffee-900">
                            Where the money went
                        </h3>

                        <ExpenseDonut
                            categories={analytics.expenseBreakdown}
                            currencySymbol={currencySymbol}
                        />
                    </div>

                    {/* Revenue, what it was consumed by, and what is still
                        owed — deliberately beside the ring rather than in it;
                        see RevenueBreakdown for why they are not one circle. */}
                    <div className={card}>
                        <h3 className="mb-4 text-base font-bold text-coffee-900">
                            Revenue and dues
                        </h3>

                        <RevenueBreakdown
                            revenue={analytics.totals.revenue}
                            expenses={analytics.totals.expenses}
                            net={analytics.totals.net}
                            receivable={standing.receivable}
                            payable={standing.payable}
                            currencySymbol={currencySymbol}
                        />
                    </div>
                </div>
            </div>

            <p className="text-xs text-coffee-800/60">
                <strong>Net is not profit.</strong> An invoice records what a
                product sold for, never what it cost to make, so revenue less
                cost of goods cannot be computed from these books. Net here is
                revenue less what was spent and billed over the same period — an
                operating result. Cancelled and returned invoices are excluded
                throughout.
            </p>
        </section>
    );
}
