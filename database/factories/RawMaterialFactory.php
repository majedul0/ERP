<?php

namespace Database\Factories;

use App\Enums\MaterialUnit;
use App\Models\RawMaterial;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RawMaterial>
 */
class RawMaterialFactory extends Factory
{
    protected $model = RawMaterial::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->words(2, true),
            'code' => Str::upper(Str::random(8)),
            'unit' => fake()->randomElement(MaterialUnit::cases()),
            'stock_quantity' => fake()->numberBetween(50, 500),
            'reorder_level' => fake()->numberBetween(10, 40),
            'unit_cost' => fake()->numberBetween(10, 500),
            'note' => null,
        ];
    }

    /**
     * Stock at or below the reorder level, for testing the low-stock view.
     */
    public function low(): static
    {
        return $this->state(fn () => [
            'reorder_level' => 50,
            'stock_quantity' => fake()->numberBetween(0, 50),
        ]);
    }
}
