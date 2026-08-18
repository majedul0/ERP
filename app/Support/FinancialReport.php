<?php

namespace App\Support;

use App\Enums\DeliveryStatus;
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
            ->whereBetween('returned_on', [$from->toDateString(), $to->toDateString()])
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
        $range = [$from->toDateString(), $to->toDateString()];

        $received = (int) $team->payments()->whereBetween('paid_on', $range)->sum('amount');
        $expenses = (int) $team->expenses()->whereBetween('spent_on', $range)->sum('amount');
        $vendorPaid = (int) $team->vendorPayments()->whereBetween('paid_on', $range)->sum('amount');
        $materials = (int) $team->materialPurchases()->whereBetween('purchased_at', $range)->sum('total_amount');
        $vendorBilled = (int) $team->vendorBills()->whereBetween('billed_on', $range)->sum('amount');

        return [
            'received' => $received,
            'expenses' => $expenses,
            'vendorPaid' => $vendorPaid,
            'materialPurchases' => $materials,
            'vendorBilled' => $vendorBilled,

            /*
             * Money actually in hand over the period. Material purchases are
             * excluded: they are recorded against a supplier bill rather than a
             * cash movement, so counting both would take the same money out
             * twice.
             */
            'netCash' => $received - $expenses - $vendorPaid,
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
        return $team->expenses()
            ->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->groupBy(fn (Expense $expense) => $expense->category->value)
            ->map(fn ($group, string $category) => [
                'category' => $category,
                'label' => $group->first()->category->label(),
                'amount' => (int) $group->sum('amount'),
            ])
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
