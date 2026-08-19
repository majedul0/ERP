<?php

namespace Tests\Feature\Finance;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Wages are recorded in Payroll, and counted from `salary_payments`.
 *
 * With two places to record a wage, every company would eventually use both and
 * the financial report would count the same money twice — so the Salary
 * category is closed to new expenses. It is *closed*, not removed: rows
 * recorded before payroll existed keep their meaning, keep reporting, and can
 * still be edited.
 */
class SalaryExpenseRetirementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = $this->user->currentTeam;
    }

    public function test_salary_is_not_offered_on_the_expense_form()
    {
        $this->actingAs($this->user)
            ->get(route('expenses.create', ['current_team' => $this->team->slug]))
            ->assertOk()
            ->assertInertia(function (Assert $page) {
                /** @var list<array{value: string, label: string}> $categories */
                $categories = $page->toArray()['props']['categories'];

                $this->assertNotContains(
                    'salary',
                    array_column($categories, 'value'),
                );
                // The rest are all still there.
                $this->assertContains('rent', array_column($categories, 'value'));
            });
    }

    /**
     * A hidden option is still a postable value, so the refusal lives on the
     * server.
     */
    public function test_a_salary_expense_is_refused_on_create()
    {
        $this->actingAs($this->user)->post(
            route('expenses.store', ['current_team' => $this->team->slug]),
            [
                'category' => ExpenseCategory::Salary->value,
                'description' => 'August wages',
                'spent_on' => '2026-08-31',
                'amount' => 20_000,
            ],
        )->assertSessionHasErrors('category');

        $this->assertSame(0, Expense::count());
    }

    public function test_another_category_is_still_accepted()
    {
        $this->actingAs($this->user)->post(
            route('expenses.store', ['current_team' => $this->team->slug]),
            [
                'category' => ExpenseCategory::Rent->value,
                'description' => 'August rent',
                'spent_on' => '2026-08-31',
                'amount' => 20_000,
            ],
        )->assertSessionHasNoErrors();

        $this->assertSame(1, Expense::count());
    }

    /**
     * A wage recorded before payroll existed must still be editable, or fixing
     * a typo in its description would silently recategorise it.
     */
    public function test_an_existing_salary_expense_can_still_be_edited()
    {
        $expense = Expense::factory()->create([
            'team_id' => $this->team->id,
            'category' => ExpenseCategory::Salary,
            'description' => 'July wages',
            'amount' => 15_000,
        ]);

        $this->actingAs($this->user)
            ->get(route('expenses.edit', [
                'current_team' => $this->team->slug,
                'expense' => $expense->id,
            ]))
            ->assertOk()
            ->assertInertia(function (Assert $page) {
                /** @var list<array{value: string, label: string}> $categories */
                $categories = $page->toArray()['props']['categories'];

                // Re-admitted for this row only.
                $this->assertContains(
                    'salary',
                    array_column($categories, 'value'),
                );
            });

        $this->actingAs($this->user)->put(
            route('expenses.update', [
                'current_team' => $this->team->slug,
                'expense' => $expense->id,
            ]),
            [
                'category' => ExpenseCategory::Salary->value,
                'description' => 'July wages (corrected)',
                'spent_on' => $expense->spent_on->toDateString(),
                'amount' => 15_000,
            ],
        )->assertSessionHasNoErrors();

        $this->assertSame('July wages (corrected)', $expense->fresh()->description);
        $this->assertSame(ExpenseCategory::Salary, $expense->fresh()->category);
    }

    /**
     * An expense that is not already a wage cannot be turned into one.
     */
    public function test_another_expense_cannot_be_recategorised_as_salary()
    {
        $expense = Expense::factory()->create([
            'team_id' => $this->team->id,
            'category' => ExpenseCategory::Rent,
        ]);

        $this->actingAs($this->user)->put(
            route('expenses.update', [
                'current_team' => $this->team->slug,
                'expense' => $expense->id,
            ]),
            [
                'category' => ExpenseCategory::Salary->value,
                'description' => 'Actually wages',
                'spent_on' => $expense->spent_on->toDateString(),
                'amount' => $expense->amount,
            ],
        )->assertSessionHasErrors('category');

        $this->assertSame(ExpenseCategory::Rent, $expense->fresh()->category);
    }

    /**
     * Legacy rows keep reporting — the case was closed, not deleted.
     */
    public function test_legacy_salary_expenses_still_appear_in_the_report()
    {
        Expense::factory()->create([
            'team_id' => $this->team->id,
            'category' => ExpenseCategory::Salary,
            'spent_on' => '2026-08-10',
            'amount' => 12_000,
        ]);

        $this->actingAs($this->user)
            ->get(route('reports.index', [
                'current_team' => $this->team->slug,
                'from' => '2026-08-01',
                'to' => '2026-08-31',
            ]))
            ->assertInertia(function (Assert $page) {
                /** @var array<string, mixed> $report */
                $report = $page->toArray()['props']['report'];

                $this->assertSame(12_000, $report['money']['expenses']);

                $salary = collect($report['expensesByCategory'])
                    ->firstWhere('category', 'salary');

                $this->assertNotNull($salary);
                $this->assertSame(12_000, $salary['amount']);
            });
    }

    public function test_the_enum_still_labels_a_legacy_row()
    {
        $this->assertFalse(ExpenseCategory::Salary->selectable());
        $this->assertStringContainsString(
            'Payroll',
            ExpenseCategory::Salary->label(),
        );
    }
}
