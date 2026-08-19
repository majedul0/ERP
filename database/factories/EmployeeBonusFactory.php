<?php

namespace Database\Factories;

use App\Enums\BonusType;
use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeBonus>
 */
class EmployeeBonusFactory extends Factory
{
    protected $model = EmployeeBonus::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'employee_id' => Employee::factory(),
            'bonus_type' => BonusType::Festival,
            'awarded_on' => '2026-08-15',
            'amount' => 5000,
        ];
    }
}
