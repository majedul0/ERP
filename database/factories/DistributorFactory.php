<?php

namespace Database\Factories;

use App\Models\Distributor;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Distributor>
 */
class DistributorFactory extends Factory
{
    protected $model = Distributor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->company(),
            'proprietor_name' => fake()->name(),
            'phone' => fake()->numerify('017########'),
            'address' => fake()->streetAddress(),
            'thana' => fake()->city(),
            'district' => fake()->city(),
            'division' => fake()->randomElement([
                'Dhaka', 'Chattogram', 'Khulna', 'Rajshahi',
                'Sylhet', 'Barishal', 'Rangpur', 'Mymensingh',
            ]),
            'balance' => 0,
        ];
    }
}
