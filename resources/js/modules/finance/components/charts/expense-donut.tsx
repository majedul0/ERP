import { useState } from 'react';
import { formatMoney } from '@/lib/format';
import type { ExpenseCategoryTotal } from '../../types';
import { chartInk, chartSeries } from './chart-palette';

const size = 220;
const radius = 84;
const thickness = 26;
const circumference = 2 * Math.PI * radius;

/**
 * The 2px surface gap between segments, expressed as arc length.
 *
 * White doing the separating, rather than a stroke drawn around each segment —
 * a border would add ink that is not data, and at this radius two neighbouring
 * steps read as distinct because of the gap alone.
 */
const gap = 3;

/**
 * Where the money went, as a share of the period's spending.
 *
 * A ring is only honest for part-to-whole at a glance, and only up to about six
 * segments — past that the slices are thinner than the gaps between them, which
 * is why `FinancialAnalytics` folds the tail into "Other categories" before this
 * ever sees it. The exact figures live in the legend beside it and in the
 * expenses table below; the ring is for the shape, not for reading values off.
 */
export function ExpenseDonut({
    categories,
    currencySymbol,
}: {
    categories: ExpenseCategoryTotal[];
    currencySymbol: string;
}) {
    const [active, setActive] = useState<string | null>(null);

    const total = categories.reduce(
        (running, category) => running + category.amount,
        0,
    );
    const money = (amount: number) => formatMoney(amount, currencySymbol);

    if (total <= 0) {
        return (
            <p className="flex h-56 items-center justify-center text-sm text-coffee-800/60">
                No expenses recorded in this period.
            </p>
        );
    }

    const shares = categories.map((category) => category.amount / total);

    const segments = categories.map((category, index) => {
        const length = shares[index] * circumference;

        return {
            ...category,
            share: shares[index],
            color: chartSeries[index % chartSeries.length],
            // Shortened by the gap so the surface shows through between
            // neighbours; never below zero for a sliver of a category.
            dash: Math.max(length - gap, 0.5),
            // Where this arc starts: everything before it, summed. At six
            // segments the repeated walk is free, and it keeps the map pure —
            // a running total carried across iterations is a mutation the
            // React compiler is right to refuse.
            offset:
                shares
                    .slice(0, index)
                    .reduce((running, share) => running + share, 0) *
                circumference,
        };
    });

    const highlighted = segments.find((segment) => segment.category === active);

    return (
        <div className="flex flex-col items-center">
            <div className="relative">
                <svg
                    viewBox={`0 0 ${size} ${size}`}
                    className="h-56 w-56"
                    role="img"
                    aria-label="Expenses by category"
                >
                    <g transform={`rotate(-90 ${size / 2} ${size / 2})`}>
                        {segments.map((segment) => (
                            <circle
                                key={segment.category}
                                cx={size / 2}
                                cy={size / 2}
                                r={radius}
                                fill="none"
                                stroke={segment.color}
                                strokeWidth={
                                    // The hovered segment lifts, so the reader
                                    // sees the chart respond to them.
                                    active === segment.category
                                        ? thickness + 6
                                        : thickness
                                }
                                strokeDasharray={`${segment.dash} ${circumference - segment.dash}`}
                                strokeDashoffset={-segment.offset}
                                className="cursor-default transition-[stroke-width]"
                                onPointerEnter={() =>
                                    setActive(segment.category)
                                }
                                onPointerLeave={() => setActive(null)}
                            />
                        ))}
                    </g>

                    {/* The centre carries the total, which is the one figure
                        the ring itself cannot show. */}
                    <text
                        x={size / 2}
                        y={size / 2 - 4}
                        textAnchor="middle"
                        fontSize="11"
                        fill={chartInk.muted}
                    >
                        {highlighted ? highlighted.label : 'Total spent'}
                    </text>
                    <text
                        x={size / 2}
                        y={size / 2 + 16}
                        textAnchor="middle"
                        fontSize="17"
                        fontWeight="700"
                        fill={chartInk.primary}
                    >
                        {money(highlighted ? highlighted.amount : total)}
                    </text>
                </svg>
            </div>

            {/* Written labels with their figures: the legend is the identity
                channel, and it is also what keeps every value reachable without
                hovering anything. */}
            <ul className="mt-3 grid w-full gap-1.5 sm:grid-cols-2">
                {segments.map((segment) => (
                    <li
                        key={segment.category}
                        className="flex items-center gap-2 rounded px-1.5 py-1 text-xs"
                        onPointerEnter={() => setActive(segment.category)}
                        onPointerLeave={() => setActive(null)}
                        style={{
                            backgroundColor:
                                active === segment.category
                                    ? 'var(--color-coffee-50)'
                                    : undefined,
                        }}
                    >
                        <span
                            aria-hidden="true"
                            className="size-2.5 shrink-0 rounded-sm"
                            style={{ backgroundColor: segment.color }}
                        />
                        <span className="truncate text-coffee-800/80">
                            {segment.label}
                        </span>
                        <span className="ml-auto shrink-0 font-semibold text-coffee-900 tabular-nums">
                            {money(segment.amount)}
                        </span>
                        <span className="w-10 shrink-0 text-right text-coffee-800/60 tabular-nums">
                            {Math.round(segment.share * 100)}%
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
