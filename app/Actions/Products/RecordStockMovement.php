<?php

namespace App\Actions\Products;

use App\Enums\StockMovementReason;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordStockMovement
{
    /**
     * Put stock on the shelf, or take it off, and say when and why.
     *
     * The movement row and the new `stock_quantity` are written together under
     * one lock. They are two views of the same event — the shelf as it is now,
     * and the history that explains it — and a report that walks backwards from
     * today's figure only lands on the right answer while they cannot drift.
     *
     * The product row is locked `FOR UPDATE` before it is read, so a recount
     * and a sale arriving together queue rather than one overwriting the
     * other's arithmetic. Stock is written with an explicit `update()`, never
     * `increment()` — see the note in App\Models\Product about why the `saved`
     * listener depends on that.
     *
     * @param  int  $quantity  Signed: positive puts goods on, negative takes them off.
     */
    public function handle(
        Product $product,
        int $quantity,
        StockMovementReason $reason,
        Carbon $occurredOn,
        ?string $remarks = null,
        ?User $actor = null,
    ): StockMovement {
        if ($quantity === 0) {
            throw ValidationException::withMessages([
                'quantity' => __('Enter a quantity.'),
            ]);
        }

        return DB::transaction(function () use ($product, $quantity, $reason, $occurredOn, $remarks, $actor): StockMovement {
            /** @var Product $locked */
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();

            $stock = $locked->stock_quantity + $quantity;

            /*
             * Stock cannot go below zero. A reduction bigger than the shelf is
             * either a typo or a sign that something else already took the
             * goods — both are worth stopping on rather than storing a
             * negative that every later figure inherits.
             */
            if ($stock < 0) {
                throw ValidationException::withMessages([
                    'quantity' => __('Only :available in stock.', ['available' => $locked->stock_quantity]),
                ]);
            }

            $locked->update(['stock_quantity' => $stock]);

            return StockMovement::create([
                'team_id' => $locked->team_id,
                'product_id' => $locked->id,
                'created_by' => $actor?->id,
                'occurred_on' => $occurredOn->toDateString(),
                'quantity' => $quantity,
                'reason' => $reason,
                'remarks' => $remarks,
            ]);
        });
    }
}
