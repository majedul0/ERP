<?php

namespace Tests\Feature\Finance;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExpenseTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function store(array $overrides = [])
    {
        return $this->actingAs($this->user)->post(
            route('expenses.store', ['current_team' => $this->team->slug]),
            [
                'category' => 'rent',
                'description' => 'Warehouse rent',
                'spent_on' => now()->toDateString(),
                'amount' => 25_000,
                ...$overrides,
            ],
        );
    }

    public function test_an_expense_is_recorded_against_the_current_company()
    {
        $this->store()->assertRedirect(
            route('expenses.index', ['current_team' => $this->team->slug]),
        );

        $expense = Expense::firstOrFail();

        $this->assertSame($this->team->id, $expense->team_id);
        $this->assertSame($this->user->id, $expense->created_by);
        $this->assertSame(ExpenseCategory::Rent, $expense->category);
        $this->assertSame(25_000, $expense->amount);
    }

    public function test_a_fractional_amount_is_rejected_rather_than_rounded()
    {
        $this->store(['amount' => 25_000.5])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Expense::count());
    }

    public function test_an_unknown_category_is_rejected()
    {
        $this->store(['category' => 'holiday'])
            ->assertSessionHasErrors('category');

        $this->assertSame(0, Expense::count());
    }

    public function test_required_fields_are_enforced()
    {
        $this->actingAs($this->user)->post(
            route('expenses.store', ['current_team' => $this->team->slug]),
            [],
        )->assertSessionHasErrors(['category', 'description', 'spent_on', 'amount']);
    }

    public function test_an_expense_can_be_updated_and_deleted()
    {
        $this->store();
        $expense = Expense::firstOrFail();

        $this->actingAs($this->user)->put(
            route('expenses.update', [
                'current_team' => $this->team->slug,
                'expense' => $expense->id,
            ]),
            [
                'category' => 'utilities',
                'description' => 'Electricity',
                'spent_on' => now()->toDateString(),
                'amount' => 4_000,
            ],
        )->assertSessionHasNoErrors();

        $expense->refresh();
        $this->assertSame(ExpenseCategory::Utilities, $expense->category);
        $this->assertSame(4_000, $expense->amount);

        $this->actingAs($this->user)->delete(route('expenses.destroy', [
            'current_team' => $this->team->slug,
            'expense' => $expense->id,
        ]))->assertRedirect();

        $this->assertSoftDeleted($expense);
    }

    public function test_the_list_shows_only_the_current_companys_expenses()
    {
        Expense::factory()->create([
            'team_id' => $this->team->id,
            'description' => 'Ours',
        ]);
        Expense::factory()->create(['description' => 'Theirs']);

        $this->actingAs($this->user)
            ->get(route('expenses.index', ['current_team' => $this->team->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('company/finance/expenses/index')
                ->has('expenses', 1)
                ->where('expenses.0.description', 'Ours'),
            );
    }

    public function test_another_companys_expense_cannot_be_edited()
    {
        $theirs = Expense::factory()->create();

        $this->actingAs($this->user)->get(route('expenses.edit', [
            'current_team' => $this->team->slug,
            'expense' => $theirs->id,
        ]))->assertNotFound();
    }

    /**
     * The banner's Total is money in less money out for the day, and expenses
     * are the "out" half that used to be an honest zero.
     */
    public function test_todays_expenses_reach_the_dashboard()
    {
        Expense::factory()->create([
            'team_id' => $this->team->id,
            'spent_on' => now()->toDateString(),
            'amount' => 3_000,
        ]);
        Expense::factory()->create([
            'team_id' => $this->team->id,
            'spent_on' => now()->subDay()->toDateString(),
            'amount' => 9_999,
        ]);

        $this->actingAs($this->user)
            ->get(route('dashboard', ['current_team' => $this->team->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.expenses', 3_000)
                ->where('stats.total', -3_000),
            );
    }

    public function test_guests_cannot_record_expenses()
    {
        $this->post(
            route('expenses.store', ['current_team' => $this->team->slug]),
            ['category' => 'rent', 'description' => 'x', 'spent_on' => now()->toDateString(), 'amount' => 1],
        )->assertRedirect(route('login'));

        $this->assertSame(0, Expense::count());
    }
}
