<?php

namespace Database\Factories;

use App\Enums\SalaryType;
use App\Models\Employee;
use App\Models\EmployeeSalaryRate;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeSalaryRate>
 */
class EmployeeSalaryRateFactory extends Factory
{
    protected $model = EmployeeSalaryRate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'employee_id' => Employee::factory(),
            'salary_type' => SalaryType::Monthly,
            'amount' => 20000,
            'effective_from' => '2025-01-01',
        ];
    }
}
