<?php

namespace App\Actions\Products;

use App\Enums\StockMovementReason;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Money;
use App\Support\TenantFileStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class UpdateProduct
{
    /**
     * Change a product's details, prices, stock or photo.
     *
     * Nothing here reaches backwards. Invoice lines copy the name, SKU and
     * unit price at the moment they are sold, so repricing a product changes
     * what the *next* invoice costs and leaves every invoice already issued
     * exactly as it was printed.
     *
     * Stock typed here is an absolute count, not a delta — it is the shelf
     * being recounted. The row is locked so a recount and a sale landing
     * together cannot lose one of the two.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Product $product, array $data, ?UploadedFile $photo = null, ?User $actor = null): Product
    {
        $photoPath = null;

        try {
            return DB::transaction(function () use ($product, $data, $photo, $actor, &$photoPath): Product {
                $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
                $previousPhoto = $product->photo_path;
                $previousStock = $product->stock_quantity;

                $attributes = [
                    'name' => $data['name'],
                    'sku' => $data['sku'],
                    'carton_size' => (int) $data['carton_size'],
                    'distributor_price' => Money::fromInput($data['distributor_price']),
                    'trade_price' => Money::fromInput($data['trade_price']),
                    'mrp' => Money::fromInput($data['mrp']),
                    'stock_quantity' => (int) $data['stock_quantity'],
                ];

                if ($photo) {
                    // Named from the previous path, so a replacement lands at
                    // product-ofc-15-2.png and no cached copy of -1 can mask it.
                    $photoPath = TenantFileStore::put(
                        'product_photos',
                        $product->team_id,
                        $photo,
                        previousPath: $previousPhoto,
                        nameSuffix: $attributes['sku'],
                    );

                    $attributes['photo_path'] = $photoPath;
                }

                $product->update($attributes);

                /*
                 * A recount is a dated event like any other stock movement, so
                 * it is recorded as the difference it made. Without this the
                 * stock report would walk backwards past a correction and
                 * report a month that never happened.
                 */
                if ($product->stock_quantity !== $previousStock) {
                    StockMovement::create([
                        'team_id' => $product->team_id,
                        'product_id' => $product->id,
                        'created_by' => $actor?->id,
                        'occurred_on' => now()->toDateString(),
                        'quantity' => $product->stock_quantity - $previousStock,
                        'reason' => StockMovementReason::Adjustment,
                        'remarks' => __('Stock recount'),
                    ]);
                }

                // Only once the new path is committed.
                if ($photoPath !== null && $previousPhoto !== $photoPath) {
                    TenantFileStore::delete('product_photos', $previousPhoto);
                }

                return $product;
            });
        } catch (Throwable $e) {
            TenantFileStore::delete('product_photos', $photoPath);

            throw $e;
        }
    }
}
