<?php

namespace App\Support;

use App\Enums\DeliveryStatus;
use App\Models\Product;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What each product held, made, sold and lost over a month.
 *
 * Nothing here is stored. The closing figure is `products.stock_quantity` —
 * the shelf as it is now — walked *backwards* through everything dated after
 * the month being asked about, and the opening figure is that walked back
 * through the month itself. A snapshot table was the obvious alternative and is
 * the reason this class exists instead: a production entered against the wrong
 * day, or an invoice corrected a week later, would leave every snapshot after
 * it wrong until somebody noticed. Here it simply reports differently the next
 * time it is asked, which is the same promise App\Support\FinancialReport makes.
 *
 * The row adds up exactly:
 *
 *     Closing = Opening + Productions − Sales − Damaged
 *
 * which holds because **Sales is net of what came back**. A distributor
 * returning goods did not buy them, whichever condition they arrived in. Fresh
 * ones went back on the shelf and are shown in their own column so the netting
 * is visible rather than silent; damaged ones never became sellable again and
 * are written off under Damaged, beside anything scrapped in the warehouse.
 *
 * Balance is the integrity check, and is zero in a healthy company: stock on
 * the shelf, less everything the books say ever moved. It goes non-zero only if
 * `stock_quantity` was changed without a matching `stock_movements` row, which
 * no code path here does — see App\Actions\Products\RecordStockMovement.
 */
final class ProductStockReport
{
    /**
     * @return array<string, mixed>
     */
    public static function build(Team $team, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        $afterPeriod = $to->copy()->addDay()->startOfDay();

        $products = $team->products()->orderBy('name')->get();

        $sold = self::sold($team, $from, $to);
        $returned = self::returned($team, $from, $to);
        $moved = self::moved($team, $from, $to);

        // Everything dated after the period, which is what separates today's
        // shelf from the shelf as it stood on the last day of the month.
        $soldAfter = self::sold($team, $afterPeriod, null);
        $returnedAfter = self::returned($team, $afterPeriod, null);
        $movedAfter = self::moved($team, $afterPeriod, null);

        // And everything ever, for the Balance column.
        $soldEver = self::sold($team, null, null);
        $returnedEver = self::returned($team, null, null);
        $movedEver = self::moved($team, null, null);

        $rows = array_values($products->map(function (Product $product) use (
            $sold,
            $returned,
            $moved,
            $soldAfter,
            $returnedAfter,
            $movedAfter,
            $soldEver,
            $returnedEver,
            $movedEver,
        ): array {
            $id = $product->id;

            $productions = $moved[$id]['in'] ?? 0;
            $fresh = $returned[$id]['fresh'] ?? 0;
            $damagedReturns = $returned[$id]['damaged'] ?? 0;

            $sales = ($sold[$id]['quantity'] ?? 0) - $fresh - $damagedReturns;
            $salesValue = ($sold[$id]['amount'] ?? 0) - ($returned[$id]['amount'] ?? 0);
            $damaged = $damagedReturns + ($moved[$id]['out'] ?? 0);

            $after = self::delta($soldAfter[$id] ?? null, $returnedAfter[$id] ?? null, $movedAfter[$id] ?? null);
            $ever = self::delta($soldEver[$id] ?? null, $returnedEver[$id] ?? null, $movedEver[$id] ?? null);

            $closing = $product->stock_quantity - $after;
            $opening = $closing - $productions + $sales + $damaged;

            return [
                'id' => $id,
                'name' => $product->name,
                'sku' => $product->sku,
                'opening' => $opening,
                'productions' => $productions,
                'total' => $opening + $productions,
                'sales' => $sales,
                'salesValue' => $salesValue,
                'freshReturns' => $fresh,
                'damaged' => $damaged,
                'closing' => $closing,
                'closingValue' => $closing * $product->distributor_price,
                'balance' => $product->stock_quantity - $ever,
            ];
        })->all());

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'month' => (int) $from->month,
                'year' => (int) $from->year,
                'label' => $from->format('F, Y'),
            ],
            'rows' => $rows,
            'totals' => self::totals($rows),
        ];
    }

    /**
     * How much a product's stock moved, net, over whatever window produced
     * these three figures.
     *
     * Damaged returns are absent on purpose: goods credited but never put back
     * on the shelf did not change it.
     *
     * @param  array{quantity: int, amount: int}|null  $sold
     * @param  array{fresh: int, damaged: int, amount: int}|null  $returned
     * @param  array{in: int, out: int}|null  $moved
     */
    private static function delta(?array $sold, ?array $returned, ?array $moved): int
    {
        return ($moved['in'] ?? 0)
            - ($moved['out'] ?? 0)
            - ($sold['quantity'] ?? 0)
            + ($returned['fresh'] ?? 0);
    }

    /**
     * Quantity and value invoiced per product, ignoring void invoices — a
     * cancelled sale never left the warehouse, and `DeliveryStatus::isLive()`
     * is the single place that question is answered.
     *
     * @return array<int, array{quantity: int, amount: int}>
     */
    private static function sold(Team $team, ?Carbon $from, ?Carbon $to): array
    {
        $live = array_map(
            fn (DeliveryStatus $status) => $status->value,
            array_filter(DeliveryStatus::cases(), fn (DeliveryStatus $status) => $status->isLive()),
        );

        $rows = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.team_id', $team->id)
            ->whereNull('invoices.deleted_at')
            ->whereIn('invoices.delivery_status', $live)
            ->whereNotNull('invoice_items.product_id')
            ->when($from, fn ($query) => $query->whereDate('invoices.sold_at', '>=', $from->toDateString()))
            ->when($to, fn ($query) => $query->whereDate('invoices.sold_at', '<=', $to->toDateString()))
            ->groupBy('invoice_items.product_id')
            ->select('invoice_items.product_id')
            ->selectRaw('COALESCE(SUM(invoice_items.total_quantity), 0) AS quantity')
            ->selectRaw('COALESCE(SUM(invoice_items.amount), 0) AS amount')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[(int) $row->product_id] = [
                'quantity' => (int) $row->quantity,
                'amount' => (int) $row->amount,
            ];
        }

        return $totals;
    }

    /**
     * Quantity and value returned per product, split by whether the goods went
     * back on the shelf.
     *
     * @return array<int, array{fresh: int, damaged: int, amount: int}>
     */
    private static function returned(Team $team, ?Carbon $from, ?Carbon $to): array
    {
        $rows = DB::table('sales_return_items')
            ->join('sales_returns', 'sales_returns.id', '=', 'sales_return_items.sales_return_id')
            ->where('sales_returns.team_id', $team->id)
            ->whereNull('sales_returns.deleted_at')
            ->whereNotNull('sales_return_items.product_id')
            ->when($from, fn ($query) => $query->whereDate('sales_returns.returned_on', '>=', $from->toDateString()))
            ->when($to, fn ($query) => $query->whereDate('sales_returns.returned_on', '<=', $to->toDateString()))
            ->groupBy('sales_return_items.product_id', 'sales_returns.restock')
            ->select('sales_return_items.product_id', 'sales_returns.restock')
            ->selectRaw('COALESCE(SUM(sales_return_items.quantity), 0) AS quantity')
            ->selectRaw('COALESCE(SUM(sales_return_items.amount), 0) AS amount')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $id = (int) $row->product_id;
            $totals[$id] ??= ['fresh' => 0, 'damaged' => 0, 'amount' => 0];

            $key = $row->restock ? 'fresh' : 'damaged';
            $totals[$id][$key] += (int) $row->quantity;
            $totals[$id]['amount'] += (int) $row->amount;
        }

        return $totals;
    }

    /**
     * Stock put on the shelf and taken off it by everything that was not a sale
     * or a return: production, recounts, goods written off.
     *
     * @return array<int, array{in: int, out: int}>
     */
    private static function moved(Team $team, ?Carbon $from, ?Carbon $to): array
    {
        $rows = DB::table('stock_movements')
            ->where('team_id', $team->id)
            ->when($from, fn ($query) => $query->whereDate('occurred_on', '>=', $from->toDateString()))
            ->when($to, fn ($query) => $query->whereDate('occurred_on', '<=', $to->toDateString()))
            ->groupBy('product_id')
            ->select('product_id')
            ->selectRaw('COALESCE(SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END), 0) AS moved_in')
            ->selectRaw('COALESCE(SUM(CASE WHEN quantity < 0 THEN -quantity ELSE 0 END), 0) AS moved_out')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[(int) $row->product_id] = [
                'in' => (int) $row->moved_in,
                'out' => (int) $row->moved_out,
            ];
        }

        return $totals;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private static function totals(array $rows): array
    {
        $columns = [
            'opening', 'productions', 'total', 'sales', 'salesValue',
            'freshReturns', 'damaged', 'closing', 'closingValue', 'balance',
        ];

        $totals = [];

        foreach ($columns as $column) {
            $totals[$column] = (int) array_sum(array_column($rows, $column));
        }

        return $totals;
    }
}
