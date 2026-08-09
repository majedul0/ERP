<?php

namespace Database\Factories;

use App\Models\Bank;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bank>
 */
class BankFactory extends Factory
{
    protected $model = Bank::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->randomElement([
                'Dutch Bangla Bank Limited',
                'BRAC Bank Limited',
                'Islami Bank Bangladesh',
                'City Bank Limited',
                'Cash',
            ]).' '.fake()->unique()->numberBetween(1, 100000),
            'account_number' => fake()->numerify('##########'),
        ];
    }
}
