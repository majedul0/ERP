<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\Team;
use App\Support\Money;
use App\Support\TenantFileStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class CreateProduct
{
    /**
     * Register a product for a company.
     *
     * Prices arrive as decimals from the form and are stored as integer minor
     * units; see App\Support\Money for why.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Team $team, array $data, ?UploadedFile $photo = null): Product
    {
        $photoPath = null;

        try {
            return DB::transaction(function () use ($team, $data, $photo, &$photoPath): Product {
                $product = Product::create([
                    'team_id' => $team->id,
                    'name' => $data['name'],
                    'sku' => $data['sku'],
                    'carton_size' => (int) $data['carton_size'],
                    'distributor_price' => Money::fromInput($data['distributor_price']),
                    'trade_price' => Money::fromInput($data['trade_price']),
                    'mrp' => Money::fromInput($data['mrp']),
                    'stock_quantity' => (int) ($data['stock_quantity'] ?? 0),
                ]);

                // Written after the insert so the unique SKU has been accepted
                // before it is used to name the file:
                // products/{team}/product-ofc-15gm-1.png.
                if ($photo) {
                    $photoPath = TenantFileStore::put(
                        'product_photos',
                        $team->id,
                        $photo,
                        nameSuffix: $product->sku,
                    );

                    $product->update(['photo_path' => $photoPath]);
                }

                return $product;
            });
        } catch (Throwable $e) {
            TenantFileStore::delete('product_photos', $photoPath);

            throw $e;
        }
    }
}
