<?php

namespace App\Http\Controllers\Hr;

use App\Concerns\ResolvesCurrentTeam;
use App\Enums\SalaryType;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeSalaryRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What each person is paid, and from when.
 *
 * Effective-dated rather than a figure on the employee record, so a raise in
 * June leaves January's payslip exactly as it was printed. Correcting a rate
 * typed wrongly means editing the row that was wrong, which then replays
 * through every draft that reads it.
 */
class SalaryRateController extends Controller
{
    use ResolvesCurrentTeam;

    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);

        $employees = $team->employees()->orderBy('name')->get();

        $rates = EmployeeSalaryRate::query()
            ->where('team_id', $team->id)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get()
            ->groupBy('employee_id');

        return Inertia::render('company/hr/payroll/rates', [
            'employees' => $employees->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'employeeCode' => $employee->employee_code,
                'salaryType' => $employee->salary_type->value,
                'isActive' => $employee->isActive(),
                'rates' => $rates->get($employee->id, collect())
                    ->map(fn (EmployeeSalaryRate $rate) => [
                        'id' => $rate->id,
                        'salaryType' => $rate->salary_type->value,
                        'salaryTypeLabel' => $rate->salary_type->rateLabel(),
                        'amount' => $rate->amount,
                        'effectiveFrom' => $rate->effective_from->toDateString(),
                    ])
                    ->values()
                    ->all(),
            ])->all(),
            'salaryTypes' => SalaryType::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $team = $this->currentTeam($request);

        $validated = $request->validate([
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')
                    ->where('team_id', $team->id)
                    ->whereNull('deleted_at'),
            ],
            'salary_type' => ['required', Rule::enum(SalaryType::class)],
            // Whole amounts, `integer` not `numeric`, like all money here.
            'amount' => ['required', 'integer', 'min:0', 'max:99999999'],
            'effective_from' => [
                'required',
                'date_format:Y-m-d',
                /*
                 * Two rates starting the same day would make "which one
                 * applied" a coin toss. The unique index enforces it; this
                 * turns the collision into a sentence somebody can act on.
                 */
                Rule::unique('employee_salary_rates', 'effective_from')
                    ->where('employee_id', (int) $request->integer('employee_id')),
            ],
        ], [
            'effective_from.unique' => __('This employee already has a rate starting that day.'),
            'amount.integer' => __('Enter a whole amount, with no decimals.'),
        ]);

        EmployeeSalaryRate::create([
            ...$validated,
            'team_id' => $team->id,
            'created_by' => $request->user()?->id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Rate saved. Open drafts recalculate from it.'),
        ]);

        return to_route('salary-rates.index', ['current_team' => $team->slug]);
    }

    /**
     * Remove a rate.
     *
     * Hard, because a rate that lingers still answers "what applied in March".
     * Approved runs keep the figure they froze, so this changes only what a
     * future recompute reads.
     */
    public function destroy(Request $request, string $current_team, EmployeeSalaryRate $rate): RedirectResponse
    {
        $team = $this->currentTeam($request);

        abort_unless($rate->team_id === $team->id, 404);

        $rate->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rate removed.')]);

        return to_route('salary-rates.index', ['current_team' => $team->slug]);
    }
}
