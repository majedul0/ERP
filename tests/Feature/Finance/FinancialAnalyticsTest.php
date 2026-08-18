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
use App\Support\FinancialAnalytics;
use App\Support\FinancialReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinancialAnalyticsTest extends TestCase
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
            'stock_quantity' => 100_000,
        ]);
    }

    private function sell(int $quantity, string $soldAt): Invoice
    {
        $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            [
                'sold_at' => $soldAt,
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

    private function spend(int $amount, string $on, string $category = 'rent'): void
    {
        Expense::factory()->create([
            'team_id' => $this->team->id,
            'spent_on' => $on,
            'amount' => $amount,
            'category' => $category,
        ]);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function report(array $query = []): TestResponse
    {
        return $this->actingAs($this->user)->get(route('reports.index', [
            'current_team' => $this->team->slug,
            ...$query,
        ]));
    }

    public function test_it_buckets_a_year_by_month()
    {
        $this->sell(10, '2026-02-14');   // 1,000
        $this->sell(5, '2026-05-02');    // 500
        $this->spend(300, '2026-02-20');

        $this->report(['analytics_year' => 2026])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('company/finance/reports/index')
                ->where('analytics.granularity', 'monthly')
                ->has('analytics.buckets', 12)
                ->where('analytics.buckets.1.label', 'Feb')
                ->where('analytics.buckets.1.revenue', 1_000)
                ->where('analytics.buckets.1.expenses', 300)
                ->where('analytics.buckets.1.net', 700)
                ->where('analytics.buckets.4.revenue', 500)
                ->where('analytics.totals.revenue', 1_500)
                ->where('analytics.totals.net', 1_200),
            );
    }

    /**
     * A month with no trade is part of the shape of the year — dropping it
     * would draw January straight to March and call that a trend.
     */
    public function test_months_with_no_activity_are_still_buckets()
    {
        $this->sell(10, '2026-03-01');

        $this->report(['analytics_year' => 2026])->assertInertia(fn (Assert $page) => $page
            ->has('analytics.buckets', 12)
            ->where('analytics.buckets.0.revenue', 0)
            ->where('analytics.buckets.0.label', 'Jan'),
        );
    }

    /**
     * The one thing that must never drift: a month on the chart and the same
     * month in the report below it are the same number.
     */
    public function test_a_bucket_agrees_with_the_report_for_the_same_month()
    {
        $this->sell(12, '2026-04-03');
        $this->sell(8, '2026-04-19');
        $this->spend(450, '2026-04-11');

        $analytics = FinancialAnalytics::build(
            $this->team,
            'monthly',
            Carbon::create(2026, 1, 1),
            Carbon::create(2026, 12, 31),
        );

        $report = FinancialReport::build(
            $this->team,
            Carbon::create(2026, 4, 1),
            Carbon::create(2026, 4, 30),
        );

        $april = collect($analytics['buckets'])->firstWhere('key', '2026-04');

        $this->assertSame($report['sales']['net'], $april['revenue']);
        $this->assertSame(
            $report['money']['expenses'] + $report['money']['vendorBilled'],
            $april['expenses'],
        );
    }

    public function test_a_year_view_puts_one_bucket_on_each_year()
    {
        $this->sell(10, '2025-06-01');
        $this->sell(20, '2026-06-01');

        $this->report(['granularity' => 'yearly'])->assertInertia(function (Assert $page) {
            $page->where('analytics.granularity', 'yearly');

            /** @var list<array<string, mixed>> $buckets */
            $buckets = $page->toArray()['props']['analytics']['buckets'];
            $byYear = collect($buckets)->keyBy('key');

            $this->assertSame(1_000, $byYear['2025']['revenue']);
            $this->assertSame(2_000, $byYear['2026']['revenue']);
        });
    }

    /**
     * A void sale is not revenue here either — one answer, given by
     * `DeliveryStatus::isLive()`, wherever the question is asked.
     */
    public function test_cancelled_invoices_are_not_revenue()
    {
        $invoice = $this->sell(10, '2026-02-14');

        $this->actingAs($this->user)->patch(
            route('invoices.status.update', [
                'current_team' => $this->team->slug,
                'invoice' => $invoice->id,
            ]),
            ['delivery_status' => DeliveryStatus::Cancelled->value],
        )->assertSessionHasNoErrors();

        $this->report(['analytics_year' => 2026])->assertInertia(fn (Assert $page) => $page
            ->where('analytics.totals.revenue', 0),
        );
    }

    public function test_returns_come_off_the_month_the_goods_came_back()
    {
        $this->sell(10, '2026-02-14');

        $this->actingAs($this->user)->post(
            route('returns.store', ['current_team' => $this->team->slug]),
            [
                'distributor_id' => $this->distributor->id,
                'returned_on' => '2026-03-02',
                'restock' => true,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 3, 'unit_price' => 100],
                ],
            ],
        )->assertSessionHasNoErrors();

        $this->report(['analytics_year' => 2026])->assertInertia(fn (Assert $page) => $page
            ->where('analytics.buckets.1.revenue', 1_000)
            ->where('analytics.buckets.2.revenue', -300)
            ->where('analytics.totals.revenue', 700),
        );
    }

    public function test_vendor_bills_count_as_expenses()
    {
        $vendor = Vendor::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->user)->post(
            route('bills.store', ['current_team' => $this->team->slug]),
            [
                'vendor_id' => $vendor->id,
                'billed_on' => '2026-02-10',
                'amount' => 5_000,
                'reference' => 'BILL-1',
            ],
        )->assertSessionHasNoErrors();

        $this->spend(1_000, '2026-02-11');

        $this->report(['analytics_year' => 2026])->assertInertia(fn (Assert $page) => $page
            ->where('analytics.buckets.1.expenses', 6_000)
            ->where('analytics.buckets.1.net', -6_000),
        );
    }

    /**
     * A ring stops being readable past about six slices, so the tail is summed
     * rather than drawn — and summed, not dropped, so the ring still totals
     * what was spent.
     */
    public function test_the_expense_breakdown_folds_its_tail_into_one_slice()
    {
        foreach ([
            'rent' => 900,
            'salary' => 800,
            'utilities' => 700,
            'transport' => 600,
            'marketing' => 500,
            'maintenance' => 400,
            'office' => 300,
            'other' => 200,
        ] as $category => $amount) {
            $this->spend($amount, '2026-02-10', $category);
        }

        $this->report(['analytics_year' => 2026])->assertInertia(function (Assert $page) {
            /** @var list<array<string, mixed>> $breakdown */
            $breakdown = $page->toArray()['props']['analytics']['expenseBreakdown'];

            $this->assertCount(6, $breakdown);
            $this->assertSame(900, $breakdown[0]['amount']);
            // 400 + 300 + 200, the three smallest.
            $this->assertSame(900, $breakdown[5]['amount']);
            $this->assertSame(
                4_400,
                (int) collect($breakdown)->sum('amount'),
            );
        });
    }

    public function test_the_year_filter_is_bounded()
    {
        $this->report(['analytics_year' => 1990])->assertSessionHasErrors('analytics_year');
    }

    /**
     * The two periods on this screen are independent, and neither control may
     * quietly reset the other.
     */
    public function test_the_report_period_and_the_analytics_period_are_separate()
    {
        $this->sell(10, '2026-02-14');

        $this->report([
            'analytics_year' => 2026,
            'from' => '2026-02-01',
            'to' => '2026-02-28',
        ])->assertInertia(fn (Assert $page) => $page
            ->where('report.period.from', '2026-02-01')
            ->where('report.period.to', '2026-02-28')
            ->where('analytics.period.from', '2026-01-01')
            ->where('analytics.period.to', '2026-12-31'),
        );
    }
}
