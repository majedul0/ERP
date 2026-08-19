<?php

namespace Database\Factories;

use App\Models\Holiday;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'date' => fake()->unique()->date(),
            'name' => fake()->randomElement(['Eid-ul-Fitr', 'Victory Day', 'Independence Day']),
        ];
    }
}
