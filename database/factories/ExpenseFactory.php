<?php

namespace Database\Factories;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'bank_id' => null,
            'created_by' => User::factory(),
            'category' => fake()->randomElement(ExpenseCategory::cases()),
            'description' => fake()->sentence(3),
            'spent_on' => now()->toDateString(),
            'amount' => fake()->numberBetween(500, 50_000),
            'note' => null,
        ];
    }
}
