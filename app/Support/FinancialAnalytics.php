<?php

namespace App\Support;

use App\Enums\DeliveryStatus;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The shape of the year: what came in, what went out, month by month.
 *
 * The same figures `FinancialReport` states for one period, cut into buckets so
 * a trend is visible. Both read the same tables under the same rules — a void
 * invoice is not revenue in either, returns land on the day the goods came
 * back in either — so a month on the chart and a month in the report are the
 * same number, and `FinancialAnalyticsTest` asserts exactly that rather than
 * trusting it.
 *
 * Deliberately **not** called profit. An invoice records what a product sold
 * for, never what it cost to make, so no figure here can be revenue less cost
 * of goods. `net` is revenue less what was spent and billed over the same
 * stretch — an operating result, honest about being one. A profit line would
 * need a cost price per product, which the catalogue does not hold.
 */
final class FinancialAnalytics
{
    /** The earliest year the filters offer, and the oldest bucket worth drawing. */
    public const EARLIEST_YEAR = 2020;

    /**
     * @param  'monthly'|'yearly'  $granularity
     * @return array<string, mixed>
     */
    public static function build(Team $team, string $granularity, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $yearly = $granularity === 'yearly';

        $revenue = self::revenue($team, $yearly, $from, $to);
        $returns = self::bucketed($team, $from, $to, 'sales_returns', 'returned_on',
            self::bucket('returned_on', $yearly), 'COALESCE(SUM(return_total), 0) AS total');
        $expenses = self::bucketed($team, $from, $to, 'expenses', 'spent_on',
            self::bucket('spent_on', $yearly), 'COALESCE(SUM(amount), 0) AS total');
        $bills = self::bucketed($team, $from, $to, 'vendor_bills', 'billed_on',
            self::bucket('billed_on', $yearly), 'COALESCE(SUM(amount), 0) AS total');

        $buckets = [];

        foreach (self::keys($yearly, $from, $to) as $bucket) {
            $key = $bucket['key'];
            $earned = ($revenue[$key] ?? 0) - ($returns[$key] ?? 0);
            $spent = ($expenses[$key] ?? 0) + ($bills[$key] ?? 0);

            $buckets[] = [
                'key' => $key,
                'label' => $bucket['label'],
                'revenue' => $earned,
                'expenses' => $spent,
                'net' => $earned - $spent,
            ];
        }

        return [
            'granularity' => $granularity,
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'label' => $granularity === 'yearly'
                    ? "{$from->year} – {$to->year}"
                    : $from->format('Y'),
            ],
            'buckets' => $buckets,
            'totals' => [
                'revenue' => (int) array_sum(array_column($buckets, 'revenue')),
                'expenses' => (int) array_sum(array_column($buckets, 'expenses')),
                'net' => (int) array_sum(array_column($buckets, 'net')),
            ],
            'expenseBreakdown' => self::expenseBreakdown($team, $from, $to),
        ];
    }

    /**
     * Every bucket in the range, empty ones included.
     *
     * A month with no trade is part of the shape of the year — dropping it
     * would draw January straight to March and call it a trend.
     *
     * A list of pairs rather than a keyed map, because PHP turns a key of
     * `'2026'` into the integer `2026` and the type stops describing what is
     * actually there.
     *
     * @return list<array{key: string, label: string}>
     */
    private static function keys(bool $yearly, Carbon $from, Carbon $to): array
    {
        $keys = [];

        if ($yearly) {
            for ($year = $from->year; $year <= $to->year; $year++) {
                $keys[] = ['key' => (string) $year, 'label' => (string) $year];
            }

            return $keys;
        }

        $cursor = $from->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($to)) {
            $keys[] = ['key' => $cursor->format('Y-m'), 'label' => $cursor->format('M')];
            $cursor->addMonth();
        }

        return $keys;
    }

    /**
     * What was charged to distributors per bucket, ignoring void invoices.
     *
     * Gross less discounts and schemes, exactly as `FinancialReport::sales()`
     * computes it; returns are taken off separately because they are dated by
     * when the goods came back, not by the invoice that sold them.
     *
     * @return array<string, int>
     */
    private static function revenue(Team $team, bool $yearly, Carbon $from, Carbon $to): array
    {
        $live = array_map(
            fn (DeliveryStatus $status) => $status->value,
            array_filter(DeliveryStatus::cases(), fn (DeliveryStatus $status) => $status->isLive()),
        );

        $bucket = self::bucket('sold_at', $yearly);

        $rows = DB::table('invoices')
            ->where('team_id', $team->id)
            ->whereNull('deleted_at')
            ->whereIn('delivery_status', $live)
            ->whereBetween('sold_at', [$from->toDateString(), $to->toDateString()])
            ->groupByRaw($bucket)
            ->selectRaw($bucket.' AS bucket')
            ->selectRaw('COALESCE(SUM(invoice_total - discount_total - scheme_amount), 0) AS total')
            ->get();

        return self::keyed($rows);
    }

    /**
     * One dated money column, summed per bucket.
     *
     * @param  literal-string  $bucket
     * @param  literal-string  $sum
     * @return array<string, int>
     */
    private static function bucketed(
        Team $team,
        Carbon $from,
        Carbon $to,
        string $table,
        string $dateColumn,
        string $bucket,
        string $sum,
    ): array {
        $rows = DB::table($table)
            ->where('team_id', $team->id)
            /*
             * Every table this reads soft-deletes, and `DB::table()` does not
             * apply the model's global scope — so a deleted expense went on
             * being counted here while the breakdown beside it, which goes
             * through Eloquent, dropped it. The two figures disagreed on
             * screen. Filtered unconditionally rather than per table, because
             * the next caller will not remember either.
             */
            ->whereNull('deleted_at')
            ->whereBetween($dateColumn, [$from->toDateString(), $to->toDateString()])
            ->groupByRaw($bucket)
            ->selectRaw($bucket.' AS bucket')
            ->selectRaw($sum)
            ->get();

        return self::keyed($rows);
    }

    /**
     * The bucket key, as SQL.
     *
     * `substr(cast(date as varchar), 1, n)` rather than a driver's own date
     * function: the columns are plain dates, both Postgres and the sqlite the
     * tests run on render them as `YYYY-MM-DD`, and the first seven or four
     * characters are exactly the bucket. One expression, no driver branch, and
     * the test suite exercises the same SQL production does.
     *
     * Spelled out per column rather than interpolated, so every string that
     * reaches the query builder is one written here — a column name arriving
     * from anywhere else can never become SQL.
     *
     * @param  'sold_at'|'returned_on'|'spent_on'|'billed_on'  $column
     * @return literal-string
     */
    private static function bucket(string $column, bool $yearly): string
    {
        return match ($column) {
            'sold_at' => $yearly
                ? 'substr(cast(sold_at as varchar), 1, 4)'
                : 'substr(cast(sold_at as varchar), 1, 7)',
            'returned_on' => $yearly
                ? 'substr(cast(returned_on as varchar), 1, 4)'
                : 'substr(cast(returned_on as varchar), 1, 7)',
            'spent_on' => $yearly
                ? 'substr(cast(spent_on as varchar), 1, 4)'
                : 'substr(cast(spent_on as varchar), 1, 7)',
            'billed_on' => $yearly
                ? 'substr(cast(billed_on as varchar), 1, 4)'
                : 'substr(cast(billed_on as varchar), 1, 7)',
        };
    }

    /**
     * @param  Collection<int, \stdClass>  $rows
     * @return array<string, int>
     */
    private static function keyed($rows): array
    {
        $totals = [];

        foreach ($rows as $row) {
            $totals[(string) $row->bucket] = (int) $row->total;
        }

        return $totals;
    }

    /**
     * Spending by category, folded to at most six slices.
     *
     * Six because that is where a ring stops being readable — past it the
     * slices are thinner than the gaps between them. The tail is summed into
     * "Other categories" rather than dropped, so the ring still totals what was
     * spent, and the full list stays in the table below the chart.
     *
     * @return array<int, array{category: string, label: string, amount: int}>
     */
    private static function expenseBreakdown(Team $team, Carbon $from, Carbon $to): array
    {
        $categories = FinancialReport::expensesByCategory($team, $from, $to);

        if (count($categories) <= 6) {
            return $categories;
        }

        $shown = array_slice($categories, 0, 5);
        $tail = array_slice($categories, 5);

        $shown[] = [
            'category' => 'other-categories',
            'label' => __('Other categories'),
            'amount' => (int) array_sum(array_column($tail, 'amount')),
        ];

        return $shown;
    }
}
