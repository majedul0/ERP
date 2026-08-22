<?php

namespace App\Http\Controllers\Hr;

use App\Actions\Employees\ReplayEmployeeBalance;
use App\Concerns\ResolvesCurrentTeam;
use App\Enums\BonusType;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeBonus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bonuses awarded — Eid, performance, one-offs.
 *
 * Awarding is not paying. A bonus adds to what the company owes somebody and is
 * folded into the payroll run for the month it is dated in; the money leaves
 * through a salary payment like everything else, which is what keeps the
 * financial report counting it once.
 */
class BonusController extends Controller
{
    use ResolvesCurrentTeam;

    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);

        $bonuses = $team->employeeBonuses()
            ->with('employee')
            ->orderByDesc('awarded_on')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return Inertia::render('company/hr/bonuses/index', [
            'bonuses' => $bonuses->map(fn (EmployeeBonus $bonus) => [
                'id' => $bonus->id,
                'employeeName' => $bonus->employee->name,
                'employeeCode' => $bonus->employee->employee_code,
                'bonusType' => $bonus->bonus_type->value,
                'bonusTypeLabel' => $bonus->bonus_type->label(),
                'awardedOn' => $bonus->awarded_on->toDateString(),
                'amount' => $bonus->amount,
                'note' => $bonus->note,
            ])->all(),
            'employees' => $team->employees()
                ->orderBy('name')
                ->get()
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'employeeCode' => $employee->employee_code,
                ])
                ->all(),
            'bonusTypes' => BonusType::options(),
            'total' => (int) $team->employeeBonuses()->sum('amount'),
        ]);
    }

    public function store(Request $request, ReplayEmployeeBalance $replayBalance): RedirectResponse
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
            'bonus_type' => ['required', Rule::enum(BonusType::class)],
            'awarded_on' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'integer', 'min:1', 'max:99999999'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'amount.integer' => __('Enter a whole amount, with no decimals.'),
        ]);

        DB::transaction(function () use ($team, $validated, $request, $replayBalance): void {
            $employee = Employee::query()
                ->where('team_id', $team->id)
                ->whereKey($validated['employee_id'])
                ->lockForUpdate()
                ->firstOrFail();

            EmployeeBonus::create([
                ...$validated,
                'team_id' => $team->id,
                'created_by' => $request->user()?->id,
            ]);

            $replayBalance->handle($employee);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bonus awarded.')]);

        return to_route('bonuses.index', ['current_team' => $team->slug]);
    }

    public function destroy(
        Request $request,
        string $current_team,
        EmployeeBonus $bonus,
        ReplayEmployeeBalance $replayBalance,
    ): RedirectResponse {
        $team = $this->currentTeam($request);

        abort_unless($bonus->team_id === $team->id, 404);

        DB::transaction(function () use ($bonus, $replayBalance): void {
            $employee = Employee::query()
                ->whereKey($bonus->employee_id)
                ->lockForUpdate()
                ->firstOrFail();

            $bonus->delete();

            $replayBalance->handle($employee);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bonus removed.')]);

        return to_route('bonuses.index', ['current_team' => $team->slug]);
    }
}
