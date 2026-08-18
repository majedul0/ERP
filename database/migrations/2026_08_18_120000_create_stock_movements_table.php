<?php

use App\Enums\DeliveryStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Every change to a product's stock that is not a sale or a return.
         *
         * Sales and returns are already dated records of their own, so logging
         * them here would be a second copy that an edit could leave disagreeing
         * with the first. What was missing was a date for everything else: a
         * production run, a recount, goods written off. Without one, "how much
         * stock did we hold at the end of July" had no answer at all —
         * `products.stock_quantity` only ever says what is on the shelf now.
         *
         * With this table the stock report walks backwards from today's figure
         * instead of storing a monthly snapshot, which means correcting a
         * production entered against the wrong day corrects both months.
         */
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // A calendar date, like an invoice's `sold_at`. Production that ran
            // on the 31st and was typed in on the 1st belongs to July.
            $table->date('occurred_on');

            /*
             * Signed: positive put goods on the shelf, negative took them off.
             *
             * One signed column rather than a quantity plus a direction flag,
             * because every figure the report wants is a SUM over this column
             * and a flag would mean the sign living in two places.
             */
            $table->bigInteger('quantity');

            // See App\Enums\StockMovementReason. Stored as a string so a stale
            // row keeps its meaning if the enum grows.
            $table->string('reason');
            $table->string('remarks')->nullable();

            $table->timestamps();

            $table->index(['team_id', 'occurred_on']);
            $table->index(['product_id', 'occurred_on']);
        });

        $this->backfillOpeningStock();
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }

    /**
     * Give every product that already exists the stock it started with.
     *
     * Products registered before this table carry an unexplained
     * `stock_quantity`: goods arrived without a dated record saying when. Walk
     * each product's current figure backwards through the sales and returns
     * that *are* dated, and whatever is left is what it must have opened with.
     * That row is dated the day the product was registered, so the months
     * before this migration still add up instead of showing stock appearing
     * from nowhere.
     */
    private function backfillOpeningStock(): void
    {
        $live = array_map(
            fn (DeliveryStatus $status) => $status->value,
            array_filter(DeliveryStatus::cases(), fn (DeliveryStatus $status) => $status->isLive()),
        );

        $sold = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->whereNull('invoices.deleted_at')
            ->whereIn('invoices.delivery_status', $live)
            ->whereNotNull('invoice_items.product_id')
            ->groupBy('invoice_items.product_id')
            ->select('invoice_items.product_id')
            ->selectRaw('SUM(invoice_items.total_quantity) AS quantity')
            ->get()
            ->keyBy('product_id');

        $restocked = DB::table('sales_return_items')
            ->join('sales_returns', 'sales_returns.id', '=', 'sales_return_items.sales_return_id')
            ->whereNull('sales_returns.deleted_at')
            ->where('sales_returns.restock', true)
            ->whereNotNull('sales_return_items.product_id')
            ->groupBy('sales_return_items.product_id')
            ->select('sales_return_items.product_id')
            ->selectRaw('SUM(sales_return_items.quantity) AS quantity')
            ->get()
            ->keyBy('product_id');

        $now = now();

        DB::table('products')->whereNull('deleted_at')->orderBy('id')->chunk(200, function ($products) use ($sold, $restocked, $now) {
            $rows = [];

            foreach ($products as $product) {
                $opening = (int) $product->stock_quantity
                    + (int) ($sold[$product->id]->quantity ?? 0)
                    - (int) ($restocked[$product->id]->quantity ?? 0);

                if ($opening === 0) {
                    continue;
                }

                $rows[] = [
                    'team_id' => $product->team_id,
                    'product_id' => $product->id,
                    'created_by' => null,
                    'occurred_on' => substr((string) $product->created_at, 0, 10),
                    'quantity' => $opening,
                    'reason' => 'opening',
                    'remarks' => 'Opening stock',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows !== []) {
                DB::table('stock_movements')->insert($rows);
            }
        });
    }
};
