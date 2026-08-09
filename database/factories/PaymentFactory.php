<?php

namespace Database\Factories;

use App\Models\Distributor;
use App\Models\Payment;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'distributor_id' => Distributor::factory(),
            'bank_id' => null,
            'paid_on' => now()->toDateString(),
            'amount' => fake()->numberBetween(100, 10000),
            'comment' => null,
        ];
    }
}
