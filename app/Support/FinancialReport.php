<?php

namespace App\Support;

use App\Enums\DeliveryStatus;
use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Models\Team;
use Illuminate\Support\Carbon;

/**
 * What the company did over a period, and where it stands at the end of it.
 *
 * Every figure here is read from the same tables the screens read, so a report
 * cannot quietly disagree with the statement it was built from. Nothing is
 * cached or stored: a report is recomputed whenever it is asked for, which
 * means correcting an invoice yesterday corrects last month's report too.
 *
 * Deliberately absent: profit. The invoice records what a product sold for, not
 * what it cost, so a profit line here would be a guess dressed up as a figure.
 * The report shows what came in, what went out, and what is still owed either
 * way — all of which are known.
 */
final class FinancialReport
{
    /**
     * @return array<string, mixed>
     */
    public static function build(Team $team, Carbon $from, Carbon $to): array
    {
        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'sales' => self::sales($team, $from, $to),
            'money' => self::money($team, $from, $to),
            'expensesByCategory' => self::expensesByCategory($team, $from, $to),
            'standing' => self::standing($team),
        ];
    }

    /**
     * What was billed to distributors, ignoring void invoices — a cancelled
     * sale is not revenue, and `DeliveryStatus::isLive()` is the single place
     * that question is answered.
     *
     * @return array<string, int>
     */
    private static function sales(Team $team, Carbon $from, Carbon $to): array
    {
        $live = $team->invoices()
            ->whereBetween('sold_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->whereIn('delivery_status', array_map(
                fn (DeliveryStatus $status) => $status->value,
                array_filter(DeliveryStatus::cases(), fn (DeliveryStatus $status) => $status->isLive()),
            ));

        $totals = (clone $live)
            ->selectRaw('COALESCE(SUM(invoice_total), 0) AS gross')
            ->selectRaw('COALESCE(SUM(discount_total), 0) AS discounts')
            ->selectRaw('COALESCE(SUM(scheme_amount), 0) AS schemes')
            ->first();

        $gross = (int) ($totals->gross ?? 0);
        $discounts = (int) ($totals->discounts ?? 0);
        $schemes = (int) ($totals->schemes ?? 0);

        /*
         * Returns are counted on the day the goods came back, not against the
         * invoice that sold them. A sale in March returned in April belongs to
         * March's sales and April's returns — restating a closed month because
         * of something that happened later is how a report stops matching the
         * statement it was printed from.
         */
        $returns = (int) $team->salesReturns()
            // whereDate for the reason spelled out in money(): a return
            // recorded on the closing day belongs to the period.
            ->whereDate('returned_on', '>=', $from->toDateString())
            ->whereDate('returned_on', '<=', $to->toDateString())
            ->sum('return_total');

        return [
            'invoiceCount' => (clone $live)->count(),
            'gross' => $gross,
            'discounts' => $discounts,
            'schemes' => $schemes,
            'returns' => $returns,
            // What the distributors were actually charged, less what they sent
            // back.
            'net' => $gross - $discounts - $schemes - $returns,
        ];
    }

    /**
     * Cash in and cash out over the period.
     *
     * @return array<string, int>
     */
    private static function money(Team $team, Carbon $from, Carbon $to): array
    {
        $first = $from->toDateString();
        $last = $to->toDateString();

        /*
         * `whereDate`, not `whereBetween`, and the difference is the last day
         * of the period.
         *
         * Laravel's `date` cast writes `2026-08-31 00:00:00`. Postgres stores
         * that in a real DATE column and truncates it, so a plain string range
         * works there — but sqlite keeps the text, and `'2026-08-31 00:00:00'`
         * sorts *after* `'2026-08-31'`, silently dropping everything recorded
         * on the closing day. The test suite runs on sqlite, so the pattern
         * made a whole class of boundary bug invisible to it. `whereDate` casts
         * the column on both engines and means the same thing in each.
         */
        $inPeriod = fn ($query, string $column) => $query
            ->whereDate($column, '>=', $first)
            ->whereDate($column, '<=', $last);

        $received = (int) $inPeriod($team->payments(), 'paid_on')->sum('amount');
        $expenses = (int) $inPeriod($team->expenses(), 'spent_on')->sum('amount');
        $vendorPaid = (int) $inPeriod($team->vendorPayments(), 'paid_on')->sum('amount');
        $materials = (int) $inPeriod($team->materialPurchases(), 'purchased_at')->sum('total_amount');
        $vendorBilled = (int) $inPeriod($team->vendorBills(), 'billed_on')->sum('amount');

        /*
         * Wages, taken from `salary_payments` and nowhere else.
         *
         * Payroll lines are an entitlement, not a cash movement — an approved
         * run says what people are owed, and only a payment moves money. This
         * is also why `ExpenseCategory::Salary` is no longer offered on the
         * expense form: with two places to record a wage, every company would
         * eventually use both and the report would count it twice.
         */
        $salaryPaid = (int) $inPeriod($team->salaryPayments(), 'paid_on')->sum('amount');

        return [
            'received' => $received,
            'expenses' => $expenses,
            'vendorPaid' => $vendorPaid,
            'salaryPaid' => $salaryPaid,
            'materialPurchases' => $materials,
            'vendorBilled' => $vendorBilled,

            /*
             * Money actually in hand over the period. Material purchases are
             * excluded: they are recorded against a supplier bill rather than a
             * cash movement, so counting both would take the same money out
             * twice. Payroll lines are excluded for the same reason — an
             * entitlement is not cash, and `salaryPaid` is the movement.
             */
            'netCash' => $received - $expenses - $vendorPaid - $salaryPaid,
        ];
    }

    /**
     * Spending grouped by the category it was booked under, biggest first.
     *
     * Public because the analytics band draws the same breakdown as a chart;
     * one implementation means the chart and the list underneath it cannot
     * disagree about what was spent on rent.
     *
     * @return array<int, array{category: string, label: string, amount: int}>
     */
    public static function expensesByCategory(Team $team, Carbon $from, Carbon $to): array
    {
        $byCategory = $team->expenses()
            ->whereDate('spent_on', '>=', $from->toDateString())
            ->whereDate('spent_on', '<=', $to->toDateString())
            ->get()
            ->groupBy(fn (Expense $expense) => $expense->category->value)
            ->map(fn ($group, string $category) => [
                'category' => $category,
                'label' => $group->first()->category->label(),
                'amount' => (int) $group->sum('amount'),
            ])
            ->all();

        /*
         * Wages are a category of spending like any other, even though they
         * live in their own table — a breakdown of where the money went that
         * omits the payroll is answering a different question from the one it
         * appears to.
         *
         * Merged into the `salary` slot rather than added beside it, so a
         * company that recorded wages as expenses before payroll existed does
         * not end up with two slices both labelled Salary & Wages. The label is
         * set here rather than taken from the enum, whose own label carries the
         * "recorded in Payroll" note that belongs on a form, not on a chart.
         */
        $salaryPaid = (int) $team->salaryPayments()
            ->whereDate('paid_on', '>=', $from->toDateString())
            ->whereDate('paid_on', '<=', $to->toDateString())
            ->sum('amount');

        $key = ExpenseCategory::Salary->value;

        if ($salaryPaid > 0 || isset($byCategory[$key])) {
            $byCategory[$key] = [
                'category' => $key,
                'label' => __('Salary & Wages'),
                'amount' => ($byCategory[$key]['amount'] ?? 0) + $salaryPaid,
            ];
        }

        return collect($byCategory)
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    /**
     * Where the company stands right now, whatever the period was.
     *
     * These are balances, not flows, so they are deliberately not filtered by
     * date — "what is owed to us today" does not have a date range.
     *
     * @return array<string, int>
     */
    private static function standing(Team $team): array
    {
        $receivable = (int) $team->distributors()->sum('balance');
        $payable = (int) $team->vendors()->sum('balance');

        $stock = (int) $team->rawMaterials()
            ->selectRaw('COALESCE(SUM(stock_quantity * unit_cost), 0) AS value')
            ->value('value');

        return [
            'receivable' => $receivable,
            'payable' => $payable,
            'materialStockValue' => $stock,
        ];
    }
}
