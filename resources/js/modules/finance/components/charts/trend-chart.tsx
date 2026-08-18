import { useState } from 'react';
import { formatCompactAmount, formatMoney } from '@/lib/format';
import type { AnalyticsBucket } from '../../types';
import { chartInk, financeSeriesColor } from './chart-palette';

/** The plot area inside the SVG's own coordinate space. */
const view = { width: 760, height: 300 };
const pad = { top: 16, right: 20, bottom: 34, left: 64 };

const plot = {
    width: view.width - pad.left - pad.right,
    height: view.height - pad.top - pad.bottom,
};

const series = [
    { key: 'revenue', label: 'Revenue', color: financeSeriesColor.revenue },
    { key: 'expenses', label: 'Expenses', color: financeSeriesColor.expenses },
    { key: 'net', label: 'Net', color: financeSeriesColor.net },
] as const;

/**
 * Axis ticks on round numbers, covering the data.
 *
 * The scale always includes zero: a revenue line floating above a baseline that
 * is not zero exaggerates every movement on it, which is the oldest way to
 * mislead with a chart. Negative net is why the floor is not simply zero.
 */
function scale(values: number[]): {
    min: number;
    max: number;
    ticks: number[];
} {
    const low = Math.min(0, ...values);
    const high = Math.max(0, ...values);

    if (low === 0 && high === 0) {
        return { min: 0, max: 1, ticks: [0, 1] };
    }

    /*
     * A round step, never smaller than 1. Money is whole units, so a gridline
     * at 2.5 means nothing — and rounding sub-unit ticks for display used to
     * produce two ticks both labelled "0", which React then saw as a duplicate
     * key.
     */
    const rough = (high - low) / 4;
    const magnitude = Math.max(1, 10 ** Math.floor(Math.log10(rough)));
    const step =
        [1, 2, 5, 10].find((factor) => factor * magnitude >= rough) ?? 10;
    const size = Math.max(1, step * magnitude);

    const min = Math.floor(low / size) * size;
    const max = Math.ceil(high / size) * size;
    const ticks: number[] = [];

    for (let tick = min; tick <= max + size / 2; tick += size) {
        ticks.push(Math.round(tick));
    }

    return { min, max, ticks };
}

/**
 * Revenue, expenses and the net between them, over the chosen period.
 *
 * Hand-drawn SVG rather than a charting library: three lines and an axis do not
 * justify shipping a few hundred kilobytes to every page, and drawing it here
 * means the marks obey the same specs as the rest of the app instead of a
 * library's defaults.
 *
 * The hover layer is part of the chart, not an extra. A full-height hit band per
 * bucket means the reader aims at a month rather than at a 2px line, and the
 * same readout is on keyboard focus with the arrow keys — every value the
 * tooltip shows is also in the table below, so nothing is gated behind a hover.
 */
export function TrendChart({
    buckets,
    currencySymbol,
}: {
    buckets: AnalyticsBucket[];
    currencySymbol: string;
}) {
    const [active, setActive] = useState<number | null>(null);

    const values = buckets.flatMap((bucket) => [
        bucket.revenue,
        bucket.expenses,
        bucket.net,
    ]);
    const { min, max, ticks } = scale(values);

    const x = (index: number) =>
        buckets.length === 1
            ? pad.left + plot.width / 2
            : pad.left + (index * plot.width) / (buckets.length - 1);

    const y = (value: number) =>
        pad.top + plot.height - ((value - min) / (max - min)) * plot.height;

    const line = (key: (typeof series)[number]['key']) =>
        buckets
            .map((bucket, index) => `${x(index)},${y(bucket[key])}`)
            .join(' ');

    const area = `${pad.left},${y(min)} ${line('revenue')} ${x(buckets.length - 1)},${y(min)}`;

    const money = (amount: number) => formatMoney(amount, currencySymbol);
    const empty = values.every((value) => value === 0);

    return (
        <div className="relative">
            <svg
                viewBox={`0 0 ${view.width} ${view.height}`}
                className="h-auto w-full touch-none"
                role="img"
                aria-label="Revenue, expenses and net over the period"
                tabIndex={0}
                onKeyDown={(event) => {
                    if (
                        event.key === 'ArrowRight' ||
                        event.key === 'ArrowLeft'
                    ) {
                        event.preventDefault();
                        const step = event.key === 'ArrowRight' ? 1 : -1;
                        const next = (active ?? 0) + step;
                        setActive(
                            Math.max(0, Math.min(buckets.length - 1, next)),
                        );
                    }

                    if (event.key === 'Escape') {
                        setActive(null);
                    }
                }}
                onBlur={() => setActive(null)}
            >
                {/* Gridlines and axis: solid hairlines one step off the
                    surface, so they sit behind the data rather than beside it. */}
                {ticks.map((tick) => (
                    <g key={tick}>
                        <line
                            x1={pad.left}
                            x2={pad.left + plot.width}
                            y1={y(tick)}
                            y2={y(tick)}
                            stroke={tick === 0 ? chartInk.axis : chartInk.grid}
                            strokeWidth="1"
                        />
                        <text
                            x={pad.left - 10}
                            y={y(tick) + 4}
                            textAnchor="end"
                            fontSize="11"
                            fill={chartInk.muted}
                        >
                            {formatCompactAmount(tick)}
                        </text>
                    </g>
                ))}

                {buckets.map((bucket, index) => (
                    <text
                        key={bucket.key}
                        x={x(index)}
                        y={view.height - 12}
                        textAnchor="middle"
                        fontSize="11"
                        fill={chartInk.muted}
                    >
                        {bucket.label}
                    </text>
                ))}

                {!empty && (
                    <>
                        {/* A wash under revenue only — it is the line the page
                            leads with; the other two stay as strokes so three
                            fills never stack into mud. */}
                        <polygon
                            points={area}
                            fill={financeSeriesColor.revenue}
                            opacity="0.1"
                        />

                        {series.map((line_) => (
                            <polyline
                                key={line_.key}
                                points={line(line_.key)}
                                fill="none"
                                stroke={line_.color}
                                strokeWidth="2"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            />
                        ))}
                    </>
                )}

                {/* The crosshair, and a marker per series at the active bucket.
                    Each marker carries a 2px ring in the surface colour so it
                    stays legible where two lines cross. */}
                {active !== null && !empty && (
                    <g>
                        <line
                            x1={x(active)}
                            x2={x(active)}
                            y1={pad.top}
                            y2={pad.top + plot.height}
                            stroke={chartInk.axis}
                            strokeWidth="1"
                        />
                        {series.map((line_) => (
                            <circle
                                key={line_.key}
                                cx={x(active)}
                                cy={y(buckets[active][line_.key])}
                                r="4.5"
                                fill={line_.color}
                                stroke={chartInk.surface}
                                strokeWidth="2"
                            />
                        ))}
                    </g>
                )}

                {/* Hit bands: one per bucket, full height, so the pointer only
                    has to be nearest — never on the mark. */}
                {buckets.map((bucket, index) => (
                    <rect
                        key={bucket.key}
                        x={
                            buckets.length === 1
                                ? pad.left
                                : x(index) -
                                  plot.width / (buckets.length - 1) / 2
                        }
                        y={pad.top}
                        width={
                            buckets.length === 1
                                ? plot.width
                                : plot.width / (buckets.length - 1)
                        }
                        height={plot.height}
                        fill="transparent"
                        onPointerEnter={() => setActive(index)}
                        onPointerLeave={() => setActive(null)}
                    />
                ))}
            </svg>

            {empty && (
                <p className="absolute inset-0 flex items-center justify-center text-sm text-coffee-800/60">
                    Nothing recorded in this period yet.
                </p>
            )}

            {active !== null && !empty && (
                <div
                    className="pointer-events-none absolute top-2 z-10 min-w-40 -translate-x-1/2 rounded-lg border border-coffee-100 bg-white p-3 shadow-lg"
                    style={{
                        // Clamped, so a tooltip on January or December stays
                        // inside the card instead of half off the edge of it.
                        left: `${Math.min(Math.max((x(active) / view.width) * 100, 14), 86)}%`,
                    }}
                >
                    <p className="mb-1.5 text-xs font-semibold text-coffee-800/70">
                        {buckets[active].label}
                    </p>
                    <dl className="space-y-1">
                        {series.map((line_) => (
                            <div
                                key={line_.key}
                                className="flex items-center gap-2 text-xs"
                            >
                                <span
                                    aria-hidden="true"
                                    className="h-0.5 w-3 shrink-0 rounded-full"
                                    style={{ backgroundColor: line_.color }}
                                />
                                <dt className="text-coffee-800/70">
                                    {line_.label}
                                </dt>
                                <dd className="ml-auto font-semibold text-coffee-900 tabular-nums">
                                    {money(buckets[active][line_.key])}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </div>
            )}

            {/* A legend is always present for two or more series: identity is
                never carried by colour alone. */}
            <ul className="mt-2 flex flex-wrap items-center justify-center gap-x-5 gap-y-2">
                {series.map((line_) => (
                    <li
                        key={line_.key}
                        className="flex items-center gap-2 text-xs text-coffee-800/70"
                    >
                        <span
                            aria-hidden="true"
                            className="h-0.5 w-4 rounded-full"
                            style={{ backgroundColor: line_.color }}
                        />
                        {line_.label}
                    </li>
                ))}
            </ul>
        </div>
    );
}
