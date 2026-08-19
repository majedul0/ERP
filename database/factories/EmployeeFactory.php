<?php

namespace Database\Factories;

use App\Enums\SalaryType;
use App\Models\Employee;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'department_id' => null,
            'employee_code' => 'EMP-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => fake()->name(),
            'father_name' => fake()->name('male'),
            'phone' => fake()->numerify('01#########'),
            'designation' => fake()->randomElement(['Salesman', 'Packer', 'Driver', 'Supervisor']),
            'salary_type' => SalaryType::Monthly,
            'joined_on' => fake()->dateTimeBetween('-3 years', '-1 month')->format('Y-m-d'),
            'left_on' => null,
            // Derived by ReplayEmployeeBalance; a figure set here would be
            // wiped by the first replay, so tests must build history instead.
            'balance' => 0,
        ];
    }

    /**
     * Paid per day worked rather than a monthly figure.
     */
    public function dailyWage(): self
    {
        return $this->state(fn () => ['salary_type' => SalaryType::Daily]);
    }

    /**
     * No longer employed here.
     */
    public function left(string $on = '-1 week'): self
    {
        return $this->state(fn () => [
            'left_on' => now()->modify($on)->format('Y-m-d'),
        ]);
    }
}
