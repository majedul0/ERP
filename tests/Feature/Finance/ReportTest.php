<?php

namespace Tests\Feature\Finance;

use App\Enums\DeliveryStatus;
use App\Models\Distributor;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    private Distributor $distributor;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = $this->user->currentTeam;
        $this->distributor = Distributor::factory()->create(['team_id' => $this->team->id]);
        $this->product = Product::factory()->create([
            'team_id' => $this->team->id,
            'distributor_price' => 100,
            'stock_quantity' => 10_000,
        ]);
    }

    private function sell(int $quantity, string $soldAt = 'now'): Invoice
    {
        $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            [
                'sold_at' => now()->modify($soldAt)->toDateString(),
                'distributor_id' => $this->distributor->id,
                'items' => [[
                    'product_id' => $this->product->id,
                    'total_quantity' => $quantity,
                    'unit_price' => 100,
                ]],
            ],
        )->assertSessionHasNoErrors();

        return Invoice::latest('id')->firstOrFail();
    }

    private function report(array $query = [])
    {
        return $this->actingAs($this->user)->get(route('reports.index', [
            'current_team' => $this->team->slug,
            ...$query,
        ]));
    }

    public function test_it_reports_what_was_sold_in_the_period()
    {
        $this->sell(10);
        $this->sell(5);

        $this->report()->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('company/finance/reports/index')
            ->where('report.sales.invoiceCount', 2)
            ->where('report.sales.gross', 1500)
            ->where('report.sales.net', 1500),
        );
    }

    /**
     * A void sale is not revenue — the same question `DeliveryStatus::isLive()`
     * answers for stock and for the ledger.
     */
    public function test_cancelled_invoices_are_excluded_from_sales()
    {
        $this->sell(10);
        $cancelled = $this->sell(5);

        $this->actingAs($this->user)->patch(
            route('invoices.status.update', [
                'current_team' => $this->team->slug,
                'invoice' => $cancelled->id,
            ]),
            ['delivery_status' => DeliveryStatus::Cancelled->value],
        )->assertSessionHasNoErrors();

        $this->report()->assertInertia(fn (Assert $page) => $page
            ->where('report.sales.invoiceCount', 1)
            ->where('report.sales.net', 1000),
        );
    }

    public function test_sales_outside_the_period_are_excluded()
    {
        $this->sell(10, '-2 months');
        $this->sell(5);

        // The default period is the current month.
        $this->report()->assertInertia(fn (Assert $page) => $page
            ->where('report.sales.invoiceCount', 1)
            ->where('report.sales.net', 500),
        );
    }

    public function test_net_cash_is_receipts_less_money_out()
    {
        $this->actingAs($this->user)->post(
            route('payments.store', ['current_team' => $this->team->slug]),
            [
                'distributor_id' => $this->distributor->id,
                'paid_on' => now()->toDateString(),
                'amount' => 10_000,
            ],
        )->assertSessionHasNoErrors();

        Expense::factory()->create([
            'team_id' => $this->team->id,
            'spent_on' => now()->toDateString(),
            'amount' => 2_000,
        ]);

        $vendor = Vendor::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->user)->post(
            route('vendor-payments.store', ['current_team' => $this->team->slug]),
            [
                'vendor_id' => $vendor->id,
                'paid_on' => now()->toDateString(),
                'amount' => 3_000,
            ],
        )->assertSessionHasNoErrors();

        $this->report()->assertInertia(fn (Assert $page) => $page
            ->where('report.money.received', 10_000)
            ->where('report.money.expenses', 2_000)
            ->where('report.money.vendorPaid', 3_000)
            ->where('report.money.netCash', 5_000),
        );
    }

    public function test_expenses_are_grouped_by_category_largest_first()
    {
        Expense::factory()->create([
            'team_id' => $this->team->id,
            'category' => 'rent',
            'amount' => 5_000,
            'spent_on' => now()->toDateString(),
        ]);
        Expense::factory()->create([
            'team_id' => $this->team->id,
            'category' => 'salary',
            'amount' => 20_000,
            'spent_on' => now()->toDateString(),
        ]);

        $this->report()->assertInertia(fn (Assert $page) => $page
            ->has('report.expensesByCategory', 2)
            ->where('report.expensesByCategory.0.category', 'salary')
            ->where('report.expensesByCategory.0.amount', 20_000)
            ->where('report.expensesByCategory.1.category', 'rent'),
        );
    }

    /**
     * Balances are not flows: "what is owed to us today" has no date range.
     */
    public function test_standing_balances_ignore_the_period()
    {
        $this->sell(10, '-2 months');

        $this->report()->assertInertia(fn (Assert $page) => $page
            ->where('report.sales.invoiceCount', 0)
            ->where('report.standing.receivable', 1000),
        );
    }

    public function test_an_explicit_period_is_honoured()
    {
        $this->sell(10, '-2 months');

        $from = now()->subMonths(3)->toDateString();
        $to = now()->toDateString();

        $this->report(['from' => $from, 'to' => $to])
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.sales.invoiceCount', 1)
                ->where('report.period.from', $from),
            );
    }

    /**
     * A backwards range is a typo, not a request for nothing.
     */
    public function test_a_reversed_range_is_swapped_rather_than_returning_nothing()
    {
        $this->sell(10);

        $this->report([
            'from' => now()->addDay()->toDateString(),
            'to' => now()->subMonth()->toDateString(),
        ])->assertInertia(fn (Assert $page) => $page
            ->where('report.sales.invoiceCount', 1),
        );
    }

    public function test_the_excel_export_carries_the_figures()
    {
        $this->sell(10);

        $response = $this->actingAs($this->user)->get(route('reports.excel', [
            'current_team' => $this->team->slug,
        ]));

        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\u{FEFF}", $csv);
        $this->assertStringContainsString('Financial Report', $csv);
        $this->assertStringContainsString('Net charged', $csv);
        $this->assertStringContainsString('1000', $csv);
    }

    public function test_guests_cannot_read_the_report()
    {
        $this->get(route('reports.index', ['current_team' => $this->team->slug]))
            ->assertRedirect(route('login'));
    }
}
