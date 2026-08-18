import { formatMoney } from '@/lib/format';
import { chartInk, financeSeriesColor } from './chart-palette';

/**
 * The 2px surface gap, as a percentage of the bar it splits.
 *
 * White doing the separating, so the two parts read as two without a stroke
 * drawn around either of them.
 */
const gapPercent = 0.6;

function Bar({
    label,
    value,
    share,
    color,
    currencySymbol,
}: {
    label: string;
    value: number;
    share: number;
    color: string;
    currencySymbol: string;
}) {
    return (
        <div>
            <div className="flex items-baseline justify-between gap-3 text-xs">
                <span className="flex items-center gap-2 text-coffee-800/80">
                    <span
                        aria-hidden="true"
                        className="size-2.5 shrink-0 rounded-sm"
                        style={{ backgroundColor: color }}
                    />
                    {label}
                </span>
                <span className="font-semibold text-coffee-900 tabular-nums">
                    {formatMoney(value, currencySymbol)}
                </span>
            </div>

            <div
                className="mt-1.5 h-2.5 w-full overflow-hidden rounded-full"
                style={{ backgroundColor: chartInk.grid }}
            >
                <div
                    className="h-full rounded-full"
                    style={{
                        width: `${Math.max(share * 100, value > 0 ? 1 : 0)}%`,
                        backgroundColor: color,
                    }}
                />
            </div>
        </div>
    );
}

/**
 * What revenue was consumed by, and what the company is still owed.
 *
 * Two questions on one card, and they are **not** two slices of one ring — the
 * reason this is not part of the expenses donut. A ring states parts of one
 * whole, and revenue, profit and outstanding dues are not parts of anything
 * shared: the first two are flows over the period, and money still owed is a
 * balance as of today with no period at all. Putting them in one circle would
 * draw a total that means nothing, which is the most common way a donut lies.
 *
 * So: revenue is the whole, split into what was spent and what was left. Dues
 * get their own pair of bars, on their own scale, under their own heading that
 * says which day they are true on.
 */
export function RevenueBreakdown({
    revenue,
    expenses,
    net,
    receivable,
    payable,
    currencySymbol,
}: {
    revenue: number;
    expenses: number;
    net: number;
    /** Owed to the company by its distributors, as of today. */
    receivable: number;
    /** Owed by the company to its vendors, as of today. */
    payable: number;
    currencySymbol: string;
}) {
    const money = (amount: number) => formatMoney(amount, currencySymbol);

    // Overspending is a real month, not an error, and a stacked bar cannot show
    // it: the parts no longer fit in the whole. The bar is capped and the
    // caption says what happened instead of the geometry pretending otherwise.
    const overspent = revenue > 0 && expenses > revenue;
    const spentShare = revenue > 0 ? Math.min(expenses / revenue, 1) : 0;
    const netShare = revenue > 0 ? Math.max(net / revenue, 0) : 0;

    // The gap is only taken out when there are two parts for it to separate.
    // A month with revenue and no spending at all has one part, and charging it
    // a gap gave the spent segment a negative width.
    const split = spentShare > 0 && netShare > 0;

    // Dues are two magnitudes side by side, so they share one scale — drawn
    // against separate scales, the smaller would look as long as the bigger.
    const owedScale = Math.max(receivable, payable, 1);

    return (
        <div className="space-y-5">
            <div>
                <div className="flex items-baseline justify-between gap-3">
                    <p className="text-xs font-semibold tracking-wide text-coffee-800/60 uppercase">
                        Revenue
                    </p>
                    <p className="font-bold text-coffee-900 tabular-nums">
                        {money(revenue)}
                    </p>
                </div>

                {revenue > 0 ? (
                    <>
                        {/* One bar, two parts: what went out, and what was
                            left. The whole is revenue, which is the only whole
                            these two are actually parts of. */}
                        <div className="mt-2 flex h-3.5 w-full overflow-hidden rounded-full">
                            <div
                                style={{
                                    width: `${spentShare * 100 - (split ? gapPercent : 0)}%`,
                                    backgroundColor:
                                        financeSeriesColor.expenses,
                                }}
                            />
                            {netShare > 0 && (
                                <>
                                    {split && (
                                        <div
                                            style={{ width: `${gapPercent}%` }}
                                            className="bg-white"
                                        />
                                    )}
                                    <div
                                        style={{
                                            width: `${netShare * 100}%`,
                                            backgroundColor:
                                                financeSeriesColor.net,
                                        }}
                                    />
                                </>
                            )}
                        </div>

                        <dl className="mt-2.5 space-y-1.5 text-xs">
                            <div className="flex items-center gap-2">
                                <span
                                    aria-hidden="true"
                                    className="size-2.5 shrink-0 rounded-sm"
                                    style={{
                                        backgroundColor:
                                            financeSeriesColor.expenses,
                                    }}
                                />
                                <dt className="text-coffee-800/80">Spent</dt>
                                <dd className="ml-auto font-semibold text-coffee-900 tabular-nums">
                                    {money(expenses)}
                                    <span className="ml-2 font-normal text-coffee-800/60">
                                        {Math.round((expenses / revenue) * 100)}
                                        %
                                    </span>
                                </dd>
                            </div>

                            <div className="flex items-center gap-2">
                                <span
                                    aria-hidden="true"
                                    className="size-2.5 shrink-0 rounded-sm"
                                    style={{
                                        backgroundColor: financeSeriesColor.net,
                                    }}
                                />
                                <dt className="text-coffee-800/80">Left</dt>
                                <dd className="ml-auto font-semibold text-coffee-900 tabular-nums">
                                    {money(net)}
                                    <span className="ml-2 font-normal text-coffee-800/60">
                                        {Math.round((net / revenue) * 100)}%
                                    </span>
                                </dd>
                            </div>
                        </dl>

                        {overspent && (
                            <p className="mt-2 text-xs font-medium text-red-700">
                                Spending was{' '}
                                {Math.round((expenses / revenue) * 100)}% of
                                revenue — more went out than came in.
                            </p>
                        )}
                    </>
                ) : (
                    <p className="mt-2 text-xs text-coffee-800/60">
                        No revenue recorded in this period.
                    </p>
                )}
            </div>

            <div className="border-t border-coffee-100 pt-4">
                <p className="text-xs font-semibold tracking-wide text-coffee-800/60 uppercase">
                    Outstanding today
                </p>
                <p className="mt-0.5 mb-3 text-xs text-coffee-800/60">
                    Balances as of now — not filtered by the period above.
                </p>

                <div className="space-y-3">
                    {/* The figure that was asked for carries the colour; the
                        one beside it for context stays grey, so the card has
                        one subject rather than two competing ones. */}
                    <Bar
                        label="Total due from distributors"
                        value={receivable}
                        share={receivable / owedScale}
                        color={financeSeriesColor.revenue}
                        currencySymbol={currencySymbol}
                    />
                    <Bar
                        label="Owed to vendors"
                        value={payable}
                        share={payable / owedScale}
                        color={chartInk.muted}
                        currencySymbol={currencySymbol}
                    />
                </div>
            </div>
        </div>
    );
}
