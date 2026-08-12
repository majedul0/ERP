<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->company(),
            'proprietor_name' => fake()->name(),
            'phone' => fake()->numerify('01#########'),
            'address' => fake()->streetAddress(),
            'thana' => null,
            'district' => null,
            'division' => null,
            'balance' => 0,
        ];
    }
}
