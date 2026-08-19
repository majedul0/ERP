<?php

namespace App\Http\Controllers\Finance;

use App\Concerns\ResolvesCurrentTeam;
use App\Enums\ExpenseCategory;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expenses\SaveExpenseRequest;
use App\Models\Bank;
use App\Models\Expense;
use App\Models\SalaryPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    use ResolvesCurrentTeam;

    /**
     * What the company has spent, newest first — wages included.
     *
     * Salary payments appear here **read from `salary_payments`**, not copied
     * into `expenses`. Wages are money out and belong on this screen, but they
     * are recorded once, in Payroll, and the financial report sums them from
     * that one table. Writing a second row would mean two records of one
     * payment that an edit or a deletion could leave disagreeing — which is the
     * whole reason the Salary category was closed in the first place.
     *
     * They are not editable here for the same reason: the payment screen owns
     * them, and a row you cannot edit in two places cannot drift.
     *
     * **Gated on `payroll:view`.** A wage row names a person and states what
     * they were paid, which is exactly the disclosure the payroll permission
     * exists to control — `expense:view` opens the spending screen, not the
     * salary list. Somebody without it sees the expenses and a single total for
     * wages, which is what a spending screen needs to reconcile without naming
     * anybody.
     */
    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);
        $user = $request->user();

        // Either payroll permission is enough, the same "any one will do" rule
        // the route middleware applies.
        $seesWages = $user !== null && (
            $user->hasTeamPermission($team, TeamPermission::ViewPayroll)
            || $user->hasTeamPermission($team, TeamPermission::ManagePayroll)
        );

        $expenses = $team->expenses()
            ->with('bank')
            ->latest('spent_on')
            ->latest('id')
            ->limit(200)
            ->get();

        $wages = $seesWages
            ? $team->salaryPayments()
                ->with(['bank', 'employee'])
                ->latest('paid_on')
                ->latest('id')
                ->limit(200)
                ->get()
            : collect();

        return Inertia::render('company/finance/expenses/index', [
            'expenses' => $expenses->map(fn (Expense $expense) => $this->present($expense))->all(),
            'wages' => $wages->map(fn (SalaryPayment $payment) => [
                'id' => $payment->id,
                'categoryLabel' => __('Salary & Wages'),
                'description' => __(':kind — :name', [
                    'kind' => $payment->kind->label(),
                    'name' => $payment->employee->name,
                ]),
                'spentOn' => $payment->paid_on->toDateString(),
                'amount' => $payment->amount,
                'bankName' => $payment->bank?->name,
            ])->all(),
            'total' => (int) $team->expenses()->sum('amount'),

            /*
             * The total is shown to everyone: it names nobody, and without it
             * the spending figure on this screen would not agree with the
             * financial report.
             */
            'wagesTotal' => (int) $team->salaryPayments()->sum('amount'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('company/finance/expenses/create', [
            'categories' => ExpenseCategory::options(),
            'banks' => $this->bankOptions($request),
        ]);
    }

    public function store(SaveExpenseRequest $request): RedirectResponse
    {
        $team = $this->currentTeam($request);
        $user = $request->user();

        abort_if($user === null, 403);

        $team->expenses()->create([
            ...$request->validated(),
            'created_by' => $user->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense recorded.')]);

        return to_route('expenses.index', ['current_team' => $team->slug]);
    }

    /**
     * See InvoiceController::show() for why `$current_team` must be declared.
     */
    public function edit(Request $request, string $current_team, Expense $expense): Response
    {
        $team = $this->currentTeam($request);

        abort_unless($expense->team_id === $team->id, 404);

        return Inertia::render('company/finance/expenses/edit', [
            'expense' => $this->present($expense),
            // Re-admits Salary for a row that already is one, so editing a
            // legacy wage expense does not silently recategorise it.
            'categories' => ExpenseCategory::options($expense->category),
            'banks' => $this->bankOptions($request),
        ]);
    }

    public function update(
        SaveExpenseRequest $request,
        string $current_team,
        Expense $expense,
    ): RedirectResponse {
        $team = $this->currentTeam($request);

        abort_unless($expense->team_id === $team->id, 404);

        $expense->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense updated.')]);

        return to_route('expenses.index', ['current_team' => $team->slug]);
    }

    public function destroy(Request $request, string $current_team, Expense $expense): RedirectResponse
    {
        $team = $this->currentTeam($request);

        abort_unless($expense->team_id === $team->id, 404);

        // Soft delete: money recorded as spent is worth being able to recover.
        $expense->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense deleted.')]);

        return to_route('expenses.index', ['current_team' => $team->slug]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'category' => $expense->category->value,
            'categoryLabel' => $expense->category->label(),
            'description' => $expense->description,
            'spentOn' => $expense->spent_on->toDateString(),
            'amount' => $expense->amount,
            'bankId' => $expense->bank_id,
            'bankName' => $expense->bank?->name,
            'note' => $expense->note,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bankOptions(Request $request): array
    {
        return $this->currentTeam($request)
            ->banks()
            ->orderBy('name')
            ->get()
            ->map(fn (Bank $bank) => ['id' => $bank->id, 'name' => $bank->name])
            ->all();
    }
}
