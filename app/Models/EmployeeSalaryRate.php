<?php

namespace App\Models;

use App\Enums\SalaryType;
use Database\Factories\EmployeeSalaryRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What somebody is paid, from a given day.
 *
 * Effective-dated so a raise never rewrites an old payslip: payroll asks for
 * the rate in force during the month it computes, which is the latest row dated
 * on or before that month's last day.
 *
 * @property int $id
 * @property int $team_id
 * @property int $employee_id
 * @property int|null $created_by
 * @property SalaryType $salary_type
 * @property int $amount
 * @property Carbon $effective_from
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Employee $employee
 */
#[Fillable([
    'team_id',
    'employee_id',
    'created_by',
    'salary_type',
    'amount',
    'effective_from',
])]
class EmployeeSalaryRate extends Model
{
    /** @use HasFactory<EmployeeSalaryRateFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'salary_type' => SalaryType::class,
        ];
    }
}
