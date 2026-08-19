<?php

namespace App\Http\Controllers\Hr;

use App\Concerns\ResolvesCurrentTeam;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Support\EmployeeLedger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Payslips, and a person's own account.
 *
 * Payslips are rendered from the run's frozen lines rather than recomputed, for
 * the reason a challan is not: a payslip is a document somebody was handed, and
 * reprinting it must produce the same paper. Drift against today's data is
 * shown on the run screen, not silently applied here.
 *
 * Printed with `print:` variants and the browser's own dialog, like the invoice
 * and the statement — no PDF library, and a batch print is one page per person
 * separated by `print:break-after-page`.
 */
class PayslipController extends Controller
{
    use ResolvesCurrentTeam;

    /**
     * Every payslip on a run, ready to print as one batch.
     */
    public function index(Request $request, string $current_team, PayrollRun $run): Response
    {
        $team = $this->currentTeam($request);

        abort_unless($run->team_id === $team->id, 404);

        $run->load(['lines.employee.department']);

        return Inertia::render('company/hr/payroll/payslips', [
            'run' => [
                'id' => $run->id,
                'month' => $run->period_month->format('Y-m'),
                'monthLabel' => $run->period_month->format('F Y'),
                'status' => $run->status->value,
            ],
            'payslips' => $run->lines
                ->map(fn (PayrollLine $line) => $this->payslip($line))
                ->all(),
        ]);
    }

    /**
     * One person's account: what they earned, what they were paid, what is left.
     */
    public function statement(Request $request, string $current_team, Employee $employee): Response
    {
        $team = $this->currentTeam($request);

        abort_unless($employee->team_id === $team->id, 404);

        $entries = EmployeeLedger::entries($employee);

        return Inertia::render('company/hr/employees/statement', [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'employeeCode' => $employee->employee_code,
                'designation' => $employee->designation,
                'balance' => $employee->balance,
            ],
            'entries' => array_map(fn ($entry) => $entry->toArray(), $entries),
            'totals' => [
                'earned' => array_sum(array_map(fn ($entry) => $entry->debit, $entries)),
                'paid' => array_sum(array_map(fn ($entry) => $entry->credit, $entries)),
                'balance' => $entries === [] ? 0 : end($entries)->balanceAfter,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payslip(PayrollLine $line): array
    {
        return [
            'employeeId' => $line->employee_id,
            'employeeName' => $line->employee->name,
            'employeeCode' => $line->employee->employee_code,
            'designation' => $line->employee->designation,
            'departmentName' => $line->employee->department?->name,
            'salaryTypeLabel' => $line->salary_type->rateLabel(),
            'rateApplied' => $line->rate_applied,
            'presentDays' => $line->present_days,
            'halfDays' => $line->half_days,
            'absentDays' => $line->absent_days,
            'leaveDays' => $line->leave_days,
            'grossEarned' => $line->gross_earned,
            // The complement, so the slip adds up left to right even where the
            // division truncated — see PayrollCalculator.
            'absenceDeduction' => max($line->rate_applied - $line->gross_earned, 0),
            'overtimeHours' => $line->overtime_hours,
            'overtimeAmount' => $line->overtime_amount,
            'bonusAmount' => $line->bonus_amount,
            'otherAddition' => $line->other_addition,
            'otherDeduction' => $line->other_deduction,
            'advanceDeduction' => $line->advance_deduction,
            'netPayable' => $line->net_payable,
            'remarks' => $line->remarks,
        ];
    }
}
