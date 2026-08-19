<?php

namespace App\Models;

use App\Enums\SalaryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One person's line on one payroll run.
 *
 * Two kinds of column live here and the difference matters: the **inputs**
 * (overtime, ad-hoc additions and deductions, remarks) are typed by a person
 * and survive a recompute; every other figure is derived and is rewritten from
 * scratch each time the draft is recomputed. See App\Support\PayrollCalculator.
 *
 * @property int $id
 * @property int $payroll_run_id
 * @property int $employee_id
 * @property int $overtime_hours
 * @property int $overtime_rate
 * @property int $other_addition
 * @property int $other_deduction
 * @property string|null $remarks
 * @property SalaryType $salary_type
 * @property int $rate_applied
 * @property int $unit_total
 * @property int $unit_payable
 * @property int $present_days
 * @property int $half_days
 * @property int $absent_days
 * @property int $leave_days
 * @property int $gross_earned
 * @property int $overtime_amount
 * @property int $bonus_amount
 * @property int $advance_deduction
 * @property int $net_payable
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PayrollRun $run
 * @property-read Employee $employee
 */
#[Fillable([
    'payroll_run_id',
    'employee_id',
    'overtime_hours',
    'overtime_rate',
    'other_addition',
    'other_deduction',
    'remarks',
    'salary_type',
    'rate_applied',
    'unit_total',
    'unit_payable',
    'present_days',
    'half_days',
    'absent_days',
    'leave_days',
    'gross_earned',
    'overtime_amount',
    'bonus_amount',
    'advance_deduction',
    'net_payable',
])]
class PayrollLine extends Model
{
    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /**
     * The person this line pays.
     *
     * Without the soft-delete scope: somebody removed from the registry must
     * still appear on the payslip they were paid by. A line naming nobody is
     * not a record of anything.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /**
     * What this person is owed for the month, before anything is withheld.
     *
     * The ledger's debit — see App\Support\EmployeeLedger. Deliberately not
     * `net_payable`: an advance already reached them as cash, and counting the
     * withholding as a smaller entitlement would hide it twice.
     */
    public function earned(): int
    {
        return $this->gross_earned + $this->overtime_amount + $this->other_addition;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'salary_type' => SalaryType::class,
        ];
    }
}
