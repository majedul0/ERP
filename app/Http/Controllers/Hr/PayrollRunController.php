<?php

namespace App\Http\Controllers\Hr;

use App\Actions\Payroll\SavePayrollRun;
use App\Concerns\ResolvesCurrentTeam;
use App\Enums\PayrollRunStatus;
use App\Http\Controllers\Controller;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Support\PayrollCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class PayrollRunController extends Controller
{
    use ResolvesCurrentTeam;

    /**
     * Every month's payroll, newest first.
     */
    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);

        $runs = $team->payrollRuns()
            ->withCount('lines')
            ->withSum('lines', 'net_payable')
            ->orderByDesc('period_month')
            ->limit(60)
            ->get();

        return Inertia::render('company/hr/payroll/index', [
            'runs' => $runs->map(fn (PayrollRun $run) => [
                'id' => $run->id,
                'month' => $run->period_month->format('Y-m'),
                'monthLabel' => $run->period_month->format('F Y'),
                'status' => $run->status->value,
                'statusLabel' => $run->status->label(),
                'employeeCount' => (int) $run->lines_count,
                'netTotal' => (int) ($run->lines_sum_net_payable ?? 0),
                'approvedAt' => $run->approved_at?->toDateString(),
            ])->all(),
            'currentMonth' => Carbon::now()->format('Y-m'),
        ]);
    }

    /**
     * Open a month, creating and computing its draft if this is the first look.
     */
    public function open(Request $request, SavePayrollRun $savePayrollRun): RedirectResponse
    {
        $team = $this->currentTeam($request);

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $run = $savePayrollRun->open(
            $team,
            Carbon::createFromFormat('Y-m-d', $validated['month'].'-01'),
            $request->user(),
        );

        return to_route('payroll.show', [
            'current_team' => $team->slug,
            'run' => $run->id,
        ]);
    }

    /**
     * One month's payroll.
     *
     * An approved run is recomputed **in memory** and compared against what was
     * frozen, so a mark corrected after approval is visible as drift rather
     * than silently rewriting a payslip somebody is holding. The stored figures
     * are what is shown; the recomputation only decides which rows to flag.
     */
    public function show(Request $request, string $current_team, PayrollRun $run): Response
    {
        $team = $this->currentTeam($request);

        abort_unless($run->team_id === $team->id, 404);

        $run->load(['lines.employee', 'approver']);

        /*
         * What has actually been handed over, per person.
         *
         * Read from `salary_payments` rather than stored on the line: a payment
         * can be recorded, corrected or removed after the run was approved, and
         * a "paid" flag written onto the line would be another figure to keep
         * in step. Counted against this run only — an advance paid last month
         * settles nothing here.
         */
        $paid = $run->payments()
            ->selectRaw('employee_id, COALESCE(SUM(amount), 0) AS total')
            ->groupBy('employee_id')
            ->pluck('total', 'employee_id');

        $drifted = [];

        if ($run->status === PayrollRunStatus::Approved) {
            $fresh = collect(PayrollCalculator::forMonth(
                $team,
                $run->period_month,
                $run->lines->keyBy('employee_id'),
            ))->keyBy('employee_id');

            foreach ($run->lines as $line) {
                $now = $fresh->get($line->employee_id);

                if ($now === null || $now['net_payable'] !== $line->net_payable) {
                    $drifted[] = $line->employee_id;
                }
            }
        }

        return Inertia::render('company/hr/payroll/show', [
            'run' => [
                'id' => $run->id,
                'month' => $run->period_month->format('Y-m'),
                'monthLabel' => $run->period_month->format('F Y'),
                'status' => $run->status->value,
                'statusLabel' => $run->status->label(),
                'approvedAt' => $run->approved_at?->toDateString(),
                'approvedBy' => $run->approver?->name,
                'note' => $run->note,
            ],
            'lines' => $run->lines
                ->map(fn (PayrollLine $line) => [
                    ...$this->line($line),
                    'paid' => (int) ($paid[$line->employee_id] ?? 0),
                ])
                ->all(),
            'driftedEmployeeIds' => $drifted,
            'paidTotal' => (int) $paid->sum(),
        ]);
    }

    /**
     * Save the typed inputs and recompute the draft around them.
     */
    public function update(
        Request $request,
        string $current_team,
        PayrollRun $run,
        SavePayrollRun $savePayrollRun,
    ): RedirectResponse {
        $team = $this->currentTeam($request);

        abort_unless($run->team_id === $team->id, 404);

        $validated = $request->validate([
            'lines' => ['present', 'array', 'max:1000'],
            'lines.*.employee_id' => ['required', 'integer'],
            // Whole amounts, `integer` not `numeric`, like every other money
            // field in this app.
            'lines.*.overtime_hours' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'lines.*.overtime_rate' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'lines.*.other_addition' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'lines.*.other_deduction' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'lines.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $inputs = [];

        foreach ($validated['lines'] as $line) {
            $inputs[(int) $line['employee_id']] = [
                'overtime_hours' => (int) ($line['overtime_hours'] ?? 0),
                'overtime_rate' => (int) ($line['overtime_rate'] ?? 0),
                'other_addition' => (int) ($line['other_addition'] ?? 0),
                'other_deduction' => (int) ($line['other_deduction'] ?? 0),
                'remarks' => $line['remarks'] ?? null,
            ];
        }

        $savePayrollRun->recompute($run, $inputs);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payroll recalculated.')]);

        return to_route('payroll.show', [
            'current_team' => $team->slug,
            'run' => $run->id,
        ]);
    }

    /**
     * Agree the month, freezing its figures.
     */
    public function approve(
        Request $request,
        string $current_team,
        PayrollRun $run,
        SavePayrollRun $savePayrollRun,
    ): RedirectResponse {
        $team = $this->currentTeam($request);

        abort_unless($run->team_id === $team->id, 404);

        $savePayrollRun->approve($run, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Payroll for :month approved.', [
                'month' => $run->period_month->format('F Y'),
            ]),
        ]);

        return to_route('payroll.show', [
            'current_team' => $team->slug,
            'run' => $run->id,
        ]);
    }

    /**
     * Put an approved month back into draft.
     */
    public function reopen(
        Request $request,
        string $current_team,
        PayrollRun $run,
        SavePayrollRun $savePayrollRun,
    ): RedirectResponse {
        $team = $this->currentTeam($request);

        abort_unless($run->team_id === $team->id, 404);

        $savePayrollRun->reopen($run, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payroll reopened.')]);

        return to_route('payroll.show', [
            'current_team' => $team->slug,
            'run' => $run->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function line(PayrollLine $line): array
    {
        return [
            'employeeId' => $line->employee_id,
            'employeeName' => $line->employee->name,
            'employeeCode' => $line->employee->employee_code,
            'salaryType' => $line->salary_type->value,
            'rateApplied' => $line->rate_applied,
            'unitTotal' => $line->unit_total,
            'unitPayable' => $line->unit_payable,
            'presentDays' => $line->present_days,
            'halfDays' => $line->half_days,
            'absentDays' => $line->absent_days,
            'leaveDays' => $line->leave_days,
            'grossEarned' => $line->gross_earned,
            // The complement, so the row adds up left to right even where the
            // division truncated — see PayrollCalculator.
            'absenceDeduction' => max($line->rate_applied - $line->gross_earned, 0),
            'overtimeHours' => $line->overtime_hours,
            'overtimeRate' => $line->overtime_rate,
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
