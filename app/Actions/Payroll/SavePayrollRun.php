<?php

namespace App\Actions\Payroll;

use App\Actions\Employees\ReplayEmployeeBalance;
use App\Enums\PayrollRunStatus;
use App\Enums\SalaryPaymentKind;
use App\Models\AdvanceRepayment;
use App\Models\Employee;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\SalaryPayment;
use App\Models\Team;
use App\Models\User;
use App\Support\PayrollCalculator;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Opening, recomputing, approving and reopening a month's payroll.
 *
 * One class for all four because they are the same sequence read forwards and
 * backwards, and the reversal an approval needs is exactly what a reopen must
 * undo.
 *
 * The invariant, stated once: **a draft holds no truth; an approved run is a
 * document.** A draft is recomputed in full whenever it is touched, so
 * correcting a mark corrects the run. Approving freezes the figures because
 * that is when a payslip is printed and handed over — and from then on the run
 * only changes if somebody deliberately reopens it, which is refused once any
 * of it has been paid.
 *
 * Lock order is fixed at **run → employees (ascending id) → advances (ascending
 * id)**, matching the shape SaveSalesReturn uses, so a payroll approval and a
 * salary payment landing together queue rather than deadlock.
 */
class SavePayrollRun
{
    public function __construct(
        private readonly ReplayEmployeeBalance $replayBalance,
        private readonly ReplayAdvanceOutstanding $replayAdvance,
    ) {}

    /**
     * Open the month's run, creating it if this is the first look.
     */
    public function open(Team $team, CarbonInterface $month, ?User $actor = null): PayrollRun
    {
        $period = $month->copy()->startOfMonth();

        return DB::transaction(function () use ($team, $period, $actor): PayrollRun {
            $run = $team->payrollRuns()
                ->whereDate('period_month', $period->toDateString())
                ->lockForUpdate()
                ->first();

            if ($run === null) {
                $run = $team->payrollRuns()->create([
                    'created_by' => $actor?->id,
                    'period_month' => $period->toDateString(),
                    'status' => PayrollRunStatus::Draft,
                ]);
            }

            if ($run->status->isOpen()) {
                $this->recompute($run);
            }

            return $run->refresh();
        });
    }

    /**
     * Rebuild every line of a draft from the attendance, rates and advances as
     * they stand right now.
     *
     * The typed inputs — overtime, ad-hoc additions and deductions, remarks —
     * are read back out of the existing lines and handed to the calculator, so
     * a recompute never loses what somebody entered by hand.
     *
     * @param  array<int, array<string, mixed>>  $inputs  Fresh typed inputs, keyed by employee id.
     */
    public function recompute(PayrollRun $run, array $inputs = []): PayrollRun
    {
        if (! $run->status->isOpen()) {
            throw ValidationException::withMessages([
                'status' => __('This payroll has been approved. Reopen it before changing anything.'),
            ]);
        }

        return DB::transaction(function () use ($run, $inputs): PayrollRun {
            $existing = $run->lines()->get()->keyBy('employee_id');

            // Anything typed on this request wins over what the line held.
            foreach ($inputs as $employeeId => $typed) {
                $line = $existing->get($employeeId) ?? new PayrollLine;
                $line->fill($typed);
                $existing->put($employeeId, $line);
            }

            $computed = PayrollCalculator::forMonth($run->team, $run->period_month, $existing);

            // Delete and rebuild rather than diff: the set of people on a month
            // changes when somebody joins or leaves, and a diff would have to
            // decide what a line for a departed employee means.
            $run->lines()->delete();

            foreach ($computed as $line) {
                $run->lines()->create([
                    'employee_id' => $line['employee_id'],
                    'overtime_hours' => $line['overtime_hours'],
                    'overtime_rate' => $line['overtime_rate'],
                    'other_addition' => $line['other_addition'],
                    'other_deduction' => $line['other_deduction'],
                    'remarks' => $line['remarks'],
                    'salary_type' => $line['salary_type'],
                    'rate_applied' => $line['rate_applied'],
                    'unit_total' => $line['unit_total'],
                    'unit_payable' => $line['unit_payable'],
                    'present_days' => $line['present_days'],
                    'half_days' => $line['half_days'],
                    'absent_days' => $line['absent_days'],
                    'leave_days' => $line['leave_days'],
                    'gross_earned' => $line['gross_earned'],
                    'overtime_amount' => $line['overtime_amount'],
                    'bonus_amount' => $line['bonus_amount'],
                    'advance_deduction' => $line['advance_deduction'],
                    'net_payable' => $line['net_payable'],
                ]);
            }

            return $run->refresh();
        });
    }

    /**
     * Agree the month.
     *
     * Recomputes one last time so what is frozen is what the data says, records
     * the advance recoveries the lines call for, and puts every affected
     * person's balance back where the ledger says it should be.
     */
    public function approve(PayrollRun $run, ?User $actor = null): PayrollRun
    {
        if (! $run->status->isOpen()) {
            return $run;
        }

        $this->recompute($run);

        return DB::transaction(function () use ($run, $actor): PayrollRun {
            $run = PayrollRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
            $lines = $run->lines()->get();

            $employees = $this->lockEmployees($run->team_id, $lines->pluck('employee_id')->all());

            $this->recordRepayments($run, $lines);

            $run->update([
                'status' => PayrollRunStatus::Approved,
                'approved_by' => $actor?->id,
                'approved_at' => now(),
            ]);

            foreach ($employees as $employee) {
                $this->replayBalance->handle($employee);
            }

            return $run->refresh();
        });
    }

    /**
     * Put an approved run back into draft.
     *
     * Refused once any of it has been paid: the payslips are out and the money
     * has gone, and a run that can be rewritten underneath a payment is not a
     * record of anything. Delete the payment first if it was a mistake.
     *
     * The advance repayments this run wrote cascade away with its lines, and
     * every advance it touched is replayed — so reopening genuinely hands the
     * outstanding back rather than leaving somebody credited for a recovery
     * that no longer exists.
     */
    public function reopen(PayrollRun $run, ?User $actor = null): PayrollRun
    {
        return DB::transaction(function () use ($run): PayrollRun {
            $run = PayrollRun::whereKey($run->id)->lockForUpdate()->firstOrFail();

            if ($run->status->isOpen()) {
                return $run;
            }

            if ($run->payments()->exists()) {
                throw ValidationException::withMessages([
                    'status' => __('This payroll has payments against it. Delete those first.'),
                ]);
            }

            $lines = $run->lines()->get();
            $employees = $this->lockEmployees($run->team_id, $lines->pluck('employee_id')->all());

            // Which advances this run recovered against, before the rows go.
            $advanceIds = AdvanceRepayment::query()
                ->whereIn('payroll_line_id', $lines->pluck('id')->all())
                ->pluck('salary_payment_id')
                ->unique()
                ->all();

            $run->update([
                'status' => PayrollRunStatus::Draft,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            // Cascades take the repayment rows with the lines.
            $run->lines()->delete();

            foreach ($this->lockAdvances($advanceIds) as $advance) {
                $this->replayAdvance->handle($advance);
            }

            foreach ($employees as $employee) {
                $this->replayBalance->handle($employee);
            }

            return $this->recompute($run->refresh());
        });
    }

    /**
     * Write down what each line recovered, oldest advance first.
     *
     * The calculator decided *how much* comes off this month; this decides
     * *which* advances it comes off, and the two must agree — so the same
     * oldest-first rule is applied here to the same locked rows.
     *
     * @param  Collection<int, PayrollLine>  $lines
     */
    private function recordRepayments(PayrollRun $run, $lines): void
    {
        $repaidOn = $run->period_month->copy()->endOfMonth()->toDateString();

        foreach ($lines as $line) {
            if ($line->advance_deduction <= 0) {
                continue;
            }

            $remaining = $line->advance_deduction;

            $advances = SalaryPayment::query()
                ->where('team_id', $run->team_id)
                ->where('employee_id', $line->employee_id)
                ->where('kind', SalaryPaymentKind::Advance->value)
                ->where('outstanding', '>', 0)
                ->orderBy('paid_on')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($advances as $advance) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min($advance->installment_amount ?? $advance->outstanding, $advance->outstanding, $remaining);

                if ($take <= 0) {
                    continue;
                }

                AdvanceRepayment::create([
                    'team_id' => $run->team_id,
                    'salary_payment_id' => $advance->id,
                    'payroll_line_id' => $line->id,
                    'repaid_on' => $repaidOn,
                    'amount' => $take,
                ]);

                $remaining -= $take;

                $this->replayAdvance->handle($advance);
            }
        }
    }

    /**
     * @param  array<int, int>  $employeeIds
     * @return Collection<int, Employee>
     */
    private function lockEmployees(int $teamId, array $employeeIds)
    {
        return Employee::query()
            ->where('team_id', $teamId)
            ->whereIn('id', array_unique($employeeIds))
            // Ascending id, always: the order two concurrent writers take the
            // rows in is what decides whether they queue or deadlock.
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param  array<int, int>  $advanceIds
     * @return Collection<int, SalaryPayment>
     */
    private function lockAdvances(array $advanceIds)
    {
        return SalaryPayment::query()
            ->whereIn('id', $advanceIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }
}
