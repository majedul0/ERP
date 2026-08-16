<?php

namespace App\Actions\SalesReturns;

use App\Actions\Invoices\RecalculateDistributorBalance;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Team;
use App\Models\User;
use App\Support\Money;
use App\Support\ReturnNumbers;
use App\Support\StockVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Recording, correcting and removing goods a distributor sent back.
 *
 * One class rather than three actions because all three do the same two things
 * in the same order — move stock, then replay the account — and the reversal
 * logic an edit needs is exactly what a delete needs. Splitting them would mean
 * keeping that reversal correct in two places.
 */
class SaveSalesReturn
{
    public function __construct(
        private readonly RecalculateDistributorBalance $recalculateBalance,
    ) {}

    /**
     * Write a return, or overwrite an existing one.
     *
     * Every amount is recomputed here from the locked product rows and the
     * quantities, the same rule invoices follow: what the browser worked out is
     * for the person filling the form and is never read back.
     *
     * The return number is allocated once and never changes on edit — it is on
     * paper the distributor already has.
     *
     * Lock order is fixed and matches CreateInvoice — distributor, then
     * products by ascending id — so a return and an invoice landing together
     * queue instead of deadlocking.
     *
     * @param  array{
     *     distributor_id: int,
     *     returned_on: string,
     *     comment?: string|null,
     *     restock?: bool,
     *     items: list<array{product_id: int, quantity: int, unit_price?: int|null, remarks?: string|null}>
     * }  $data
     */
    public function handle(Team $team, User $user, array $data, ?SalesReturn $return = null): SalesReturn
    {
        return DB::transaction(function () use ($team, $user, $data, $return): SalesReturn {
            /*
             * Both accounts are locked up front, in ascending id order: a
             * return moved between distributors changes two ledgers, and
             * reaching for the second one later would break the lock order this
             * shares with CreateInvoice and UpdateInvoice.
             */
            $distributors = $this->lockDistributors($team, $return, (int) $data['distributor_id']);
            $distributor = $distributors[(int) $data['distributor_id']];

            /*
             * Lock every product either version of the return touches, in one
             * ordered query. Locking the old set separately after the new one
             * would break the ascending-id rule and reintroduce the deadlock it
             * exists to prevent.
             */
            $productIds = collect($data['items'])->pluck('product_id')->map(intval(...));

            if ($return !== null) {
                $productIds = $productIds->merge(
                    $return->items()->pluck('product_id')->filter()
                );
            }

            $products = $this->lockProducts($team, $productIds->all());

            // Take the old version off the shelf before putting the new one on,
            // so an edit is measured against stock that no longer includes what
            // it was holding.
            if ($return !== null) {
                $this->reverseStock($return, $products);
            }

            $lines = $this->buildLines($data['items'], $products);
            $total = array_sum(array_column($lines, 'amount'));
            $restock = (bool) ($data['restock'] ?? true);

            if ($return === null) {
                ['number' => $number, 'sequence' => $sequence] = ReturnNumbers::next($team);

                $return = SalesReturn::create([
                    'team_id' => $team->id,
                    'distributor_id' => $distributor->id,
                    'created_by' => $user->id,
                    'return_number' => $number,
                    'sequence_number' => $sequence,
                    'returned_on' => Carbon::parse($data['returned_on'])->startOfDay(),
                    'comment' => $data['comment'] ?? null,
                    'restock' => $restock,
                    'return_total' => $total,
                ]);
            } else {
                $return->update([
                    'distributor_id' => $distributor->id,
                    'returned_on' => Carbon::parse($data['returned_on'])->startOfDay(),
                    'comment' => $data['comment'] ?? null,
                    'restock' => $restock,
                    'return_total' => $total,
                ]);

                $return->items()->delete();
            }

            $return->items()->createMany($lines);

            if ($restock) {
                $this->applyStock($lines, $products, +1);
            }

            /*
             * Both accounts, because moving a return to a different distributor
             * leaves the old one carrying a credit it no longer has.
             */
            foreach ($distributors as $each) {
                $this->recalculateBalance->handle($each);
            }

            StockVersion::bump($team->id);

            return $return->refresh();
        });
    }

    /**
     * Remove a return recorded by mistake.
     *
     * A soft delete, for the same two reasons an invoice gets one: the row
     * keeps the unique index on `(team_id, return_number)` holding, so a number
     * that reached a distributor can never name a second document, and a
     * mistake stays recoverable. The sequence keeps counting, so a deleted
     * RNV7 leaves an honest gap.
     */
    public function delete(SalesReturn $return): void
    {
        DB::transaction(function () use ($return): void {
            $return = SalesReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();

            $distributor = Distributor::whereKey($return->distributor_id)
                ->lockForUpdate()
                ->firstOrFail();

            $products = $this->lockProducts(
                $distributor->team_id,
                $return->items()->pluck('product_id')->filter()->all(),
            );

            $this->reverseStock($return, $products);

            $return->delete();

            $this->recalculateBalance->handle($distributor);

            StockVersion::bump($distributor->team_id);
        });
    }

    /**
     * Lock the given products in ascending id order.
     *
     * @param  array<int, int|null>  $ids
     * @return array<int, Product> Keyed by product id.
     */
    private function lockProducts(Team|int $team, array $ids): array
    {
        $teamId = $team instanceof Team ? $team->id : $team;

        $ids = collect($ids)->filter()->map(intval(...))->unique()->sort()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $products = Product::query()
            ->where('team_id', $teamId)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        /** @var array<int, Product> */
        return $products->all();
    }

    /**
     * Turn submitted rows into return item attributes.
     *
     * A return is priced at what it is worth coming back. Left blank that is
     * the product's current distributor price, but the field is editable
     * because goods sold at a discount should not be credited at full price.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, Product>  $products
     * @return array<int, array<string, mixed>>
     */
    private function buildLines(array $items, array $products): array
    {
        $lines = [];

        foreach (array_values($items) as $index => $item) {
            $productId = (int) $item['product_id'];
            $product = $products[$productId] ?? null;

            if ($product === null) {
                throw ValidationException::withMessages([
                    'items' => __('One of the selected products is no longer available.'),
                ]);
            }

            $quantity = (int) $item['quantity'];
            $unitPrice = array_key_exists('unit_price', $item) && $item['unit_price'] !== null && $item['unit_price'] !== ''
                ? Money::fromInput($item['unit_price'])
                : $product->distributor_price;

            $lines[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'line_number' => $index + 1,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => Money::multiply($unitPrice, $quantity),
                'remarks' => $item['remarks'] ?? null,
            ];
        }

        return $lines;
    }

    /**
     * Undo the stock a saved return put back.
     *
     * Nothing to undo when it never restocked — damaged goods were credited but
     * never became sellable, so there is none of it on the shelf to take away.
     *
     * @param  array<int, Product>  $products
     */
    private function reverseStock(SalesReturn $return, array $products): void
    {
        if (! $return->restock) {
            return;
        }

        $lines = $return->items()
            ->get()
            ->map(fn (SalesReturnItem $item) => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'product_name' => $item->product_name,
            ])
            ->all();

        $this->assertStockCoversReversal($lines, $products);
        $this->applyStock($lines, $products, -1);
    }

    /**
     * Move stock by the given lines, in the given direction.
     *
     * Explicit `update()`, never `increment()`: increment syncs the model's
     * original attributes, which leaves a `saved` listener unable to see that
     * stock moved — and that listener is what tells other people's open invoice
     * forms to refresh. Safe because the rows are locked FOR UPDATE.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<int, Product>  $products
     */
    private function applyStock(array $lines, array $products, int $direction): void
    {
        foreach ($lines as $line) {
            $product = $products[$line['product_id']] ?? null;

            // Null when the product was deleted after the return was recorded.
            // The credit stands — it is money, not stock — and there is no row
            // left to move.
            if ($product === null) {
                continue;
            }

            $product->update([
                'stock_quantity' => $product->stock_quantity + ($direction * (int) $line['quantity']),
            ]);
        }
    }

    /**
     * Refuse to take back stock that is no longer there.
     *
     * Editing or deleting a return removes goods the shelf was credited with.
     * If they have since been sold on, removing them again would leave stock
     * negative — a figure no other screen in the system can produce, and one
     * that would silently corrupt every stock check that followed.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<int, Product>  $products
     */
    private function assertStockCoversReversal(array $lines, array $products): void
    {
        $needed = [];

        foreach ($lines as $line) {
            $id = $line['product_id'];
            $needed[$id] = ($needed[$id] ?? 0) + (int) $line['quantity'];
        }

        $errors = [];

        foreach ($needed as $productId => $quantity) {
            $product = $products[$productId] ?? null;

            if ($product !== null && $quantity > $product->stock_quantity) {
                $errors['items'][] = __(
                    'The returned :product has already been sold on — :available in stock, :requested would have to come back off.',
                    [
                        'product' => $product->name,
                        'available' => $product->stock_quantity,
                        'requested' => $quantity,
                    ],
                );
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Lock the account the return is leaving and the one it is joining.
     *
     * Usually the same row. Both are locked in one ordered query so the pair is
     * taken in ascending id order, which is what keeps two concurrent edits
     * from deadlocking against each other.
     *
     * @return array<int, Distributor>
     */
    private function lockDistributors(Team $team, ?SalesReturn $return, int $newDistributorId): array
    {
        $ids = collect([$return?->distributor_id, $newDistributorId])
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $distributors = Distributor::query()
            ->where('team_id', $team->id)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if (! $distributors->has($newDistributorId)) {
            throw ValidationException::withMessages([
                'distributor_id' => __('That distributor is no longer available.'),
            ]);
        }

        /** @var array<int, Distributor> */
        return $distributors->all();
    }
}
