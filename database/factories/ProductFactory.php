<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Minor units: 9000 is 90.00.
        $distributorPrice = fake()->numberBetween(1000, 50000);

        return [
            'team_id' => Team::factory(),
            'name' => fake()->words(2, true),
            'sku' => Str::upper(Str::random(8)),
            'carton_size' => fake()->randomElement([12, 24, 48]),
            'distributor_price' => $distributorPrice,
            'trade_price' => $distributorPrice + 500,
            'mrp' => $distributorPrice + 1500,
            'stock_quantity' => fake()->numberBetween(50, 500),
            'photo_path' => null,
        ];
    }
}
