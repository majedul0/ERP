<?php

namespace Database\Factories;

use App\Models\MaterialPurchase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialPurchase>
 */
class MaterialPurchaseFactory extends Factory
{
    protected $model = MaterialPurchase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'created_by' => User::factory(),
            'supplier_name' => fake()->company(),
            'reference' => fake()->bothify('BILL-####'),
            'purchased_at' => fake()->dateTimeBetween('-3 months')->format('Y-m-d'),
            'total_amount' => fake()->numberBetween(1000, 50000),
            'note' => null,
        ];
    }
}
