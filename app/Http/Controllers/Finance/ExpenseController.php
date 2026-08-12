<?php

namespace App\Http\Controllers\Finance;

use App\Concerns\ResolvesCurrentTeam;
use App\Enums\ExpenseCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expenses\SaveExpenseRequest;
use App\Models\Bank;
use App\Models\Expense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    use ResolvesCurrentTeam;

    /**
     * What the company has spent, newest first.
     */
    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);

        $expenses = $team->expenses()
            ->with('bank')
            ->latest('spent_on')
            ->latest('id')
            ->limit(200)
            ->get();

        return Inertia::render('company/finance/expenses/index', [
            'expenses' => $expenses->map(fn (Expense $expense) => $this->present($expense))->all(),
            'total' => (int) $team->expenses()->sum('amount'),
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
            'categories' => ExpenseCategory::options(),
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
