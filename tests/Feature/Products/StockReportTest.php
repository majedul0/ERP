<?php

namespace Tests\Feature\Products;

use App\Enums\DeliveryStatus;
use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    private Distributor $distributor;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Every test travels forward from here, so the product's opening stock
        // is dated before the months being reported on — which is what a real
        // one looks like by the time anybody asks for a month's sheet.
        $this->travelTo('2026-06-15');

        $this->user = User::factory()->create();
        $this->team = $this->user->currentTeam;
        $this->distributor = Distributor::factory()->create(['team_id' => $this->team->id]);

        // Registered through the action, not the factory, so the product opens
        // with a recorded movement the way a real one does.
        $this->actingAs($this->user)->post(
            route('products.store', ['current_team' => $this->team->slug]),
            [
                'name' => 'OHO 100ml',
                'sku' => 'OHO-100',
                'carton_size' => 12,
                'distributor_price' => 100,
                'trade_price' => 110,
                'mrp' => 120,
                'stock_quantity' => 500,
            ],
        )->assertSessionHasNoErrors();

        $this->product = Product::firstOrFail();
    }

    private function addStock(int $quantity, string $on, string $reason = 'production'): TestResponse
    {
        return $this->actingAs($this->user)->post(
            route('stock-movements.store', [
                'current_team' => $this->team->slug,
                'product' => $this->product->id,
            ]),
            [
                'direction' => 'add',
                'quantity' => $quantity,
                'occurred_on' => $on,
                'reason' => $reason,
            ],
        );
    }

    private function reduceStock(int $quantity, string $on, string $reason = 'damage'): TestResponse
    {
        return $this->actingAs($this->user)->post(
            route('stock-movements.store', [
                'current_team' => $this->team->slug,
                'product' => $this->product->id,
            ]),
            [
                'direction' => 'reduce',
                'quantity' => $quantity,
                'occurred_on' => $on,
                'reason' => $reason,
            ],
        );
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

    private function returnGoods(int $quantity, string $on, bool $restock): TestResponse
    {
        return $this->actingAs($this->user)->post(
            route('returns.store', ['current_team' => $this->team->slug]),
            [
                'distributor_id' => $this->distributor->id,
                'returned_on' => $on,
                'restock' => $restock,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_price' => 100],
                ],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function report(array $query = []): TestResponse
    {
        return $this->actingAs($this->user)->get(route('stock-reports.index', [
            'current_team' => $this->team->slug,
            ...$query,
        ]));
    }

    public function test_it_reports_the_month_a_product_opened_made_sold_and_lost()
    {
        $this->travelTo('2026-08-05');

        $this->addStock(1_000, '2026-08-02')->assertSessionHasNoErrors();
        $this->sell(300, '2026-08-03');
        $this->reduceStock(20, '2026-08-04')->assertSessionHasNoErrors();

        $this->report(['month' => 8, 'year' => 2026])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('company/products/stock-report')
                ->where('report.period.label', 'August, 2026')
                ->where('report.rows.0.opening', 500)
                ->where('report.rows.0.productions', 1_000)
                ->where('report.rows.0.total', 1_500)
                ->where('report.rows.0.sales', 300)
                ->where('report.rows.0.salesValue', 30_000)
                ->where('report.rows.0.damaged', 20)
                ->where('report.rows.0.closing', 1_180)
                ->where('report.rows.0.closingValue', 118_000)
                ->where('report.rows.0.balance', 0),
            );

        // What the report says is on the shelf is what is on the shelf.
        $this->assertSame(1_180, $this->product->fresh()->stock_quantity);
    }

    /**
     * The row has to add up on paper, because somebody checks it against the
     * warehouse: Closing = Opening + Productions − Sales − Damaged.
     */
    public function test_the_columns_add_up()
    {
        $this->travelTo('2026-08-20');

        $this->addStock(400, '2026-08-01');
        $this->sell(120, '2026-08-05');
        $this->returnGoods(20, '2026-08-06', restock: true)->assertSessionHasNoErrors();
        $this->returnGoods(10, '2026-08-07', restock: false)->assertSessionHasNoErrors();
        $this->reduceStock(5, '2026-08-08');

        $this->report(['month' => 8, 'year' => 2026])->assertInertia(function (Assert $page) {
            /** @var array<string, int> $row */
            $row = $page->toArray()['props']['report']['rows'][0];

            $this->assertSame(
                $row['closing'],
                $row['opening'] + $row['productions'] - $row['sales'] - $row['damaged'],
            );

            // Sales is net of everything sent back; fresh goods are also
            // counted in their own column, and damaged ones under Damaged.
            $this->assertSame(90, $row['sales']);
            $this->assertSame(20, $row['freshReturns']);
            $this->assertSame(15, $row['damaged']);
        });
    }

    /**
     * The closing figure is today's shelf walked backwards, so a month that has
     * already been closed keeps reporting the same figures however much stock
     * moves afterwards.
     */
    public function test_a_closed_month_is_not_disturbed_by_later_movements()
    {
        $this->travelTo('2026-07-15');
        $this->addStock(200, '2026-07-10');
        $this->sell(50, '2026-07-12');

        $this->travelTo('2026-08-15');
        $this->addStock(900, '2026-08-01');
        $this->sell(400, '2026-08-10');

        $this->report(['month' => 7, 'year' => 2026])->assertInertia(fn (Assert $page) => $page
            ->where('report.rows.0.opening', 500)
            ->where('report.rows.0.productions', 200)
            ->where('report.rows.0.sales', 50)
            ->where('report.rows.0.closing', 650),
        );

        $this->report(['month' => 8, 'year' => 2026])->assertInertia(fn (Assert $page) => $page
            ->where('report.rows.0.opening', 650)
            ->where('report.rows.0.closing', 1_150),
        );
    }

    /**
     * A void sale never left the warehouse — the same question
     * `DeliveryStatus::isLive()` answers for stock and for the ledger.
     */
    public function test_cancelled_invoices_are_not_sales()
    {
        $this->travelTo('2026-08-20');

        $invoice = $this->sell(100, '2026-08-02');

        $this->actingAs($this->user)->patch(
            route('invoices.status.update', [
                'current_team' => $this->team->slug,
                'invoice' => $invoice->id,
            ]),
            ['delivery_status' => DeliveryStatus::Cancelled->value],
        )->assertSessionHasNoErrors();

        $this->report(['month' => 8, 'year' => 2026])->assertInertia(fn (Assert $page) => $page
            ->where('report.rows.0.sales', 0)
            ->where('report.rows.0.salesValue', 0)
            ->where('report.rows.0.closing', 500)
            ->where('report.rows.0.balance', 0),
        );
    }

    /**
     * A recount typed on the edit form is a dated movement like any other, or
     * the report would walk backwards past it into a month that never happened.
     */
    public function test_a_recount_on_the_product_form_is_recorded_as_a_movement()
    {
        $this->travelTo('2026-08-20');

        $this->actingAs($this->user)->put(
            route('products.update', [
                'current_team' => $this->team->slug,
                'product' => $this->product->id,
            ]),
            [
                'name' => 'OHO 100ml',
                'sku' => 'OHO-100',
                'carton_size' => 12,
                'distributor_price' => 100,
                'trade_price' => 110,
                'mrp' => 120,
                'stock_quantity' => 480,
            ],
        )->assertSessionHasNoErrors();

        $this->report(['month' => 8, 'year' => 2026])->assertInertia(fn (Assert $page) => $page
            ->where('report.rows.0.opening', 500)
            ->where('report.rows.0.damaged', 20)
            ->where('report.rows.0.closing', 480)
            ->where('report.rows.0.balance', 0),
        );
    }

    public function test_stock_cannot_be_reduced_below_what_is_on_the_shelf()
    {
        $this->reduceStock(501, now()->toDateString())->assertSessionHasErrors('quantity');

        $this->assertSame(500, $this->product->fresh()->stock_quantity);
    }

    public function test_a_movement_cannot_reach_another_companys_product()
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->post(
            route('stock-movements.store', [
                'current_team' => $this->team->slug,
                'product' => $this->product->id,
            ]),
            [
                'direction' => 'add',
                'quantity' => 10,
                'occurred_on' => now()->toDateString(),
                'reason' => 'production',
            ],
        )->assertForbidden();

        $this->assertSame(500, $this->product->fresh()->stock_quantity);
    }

    public function test_the_report_can_be_downloaded_as_a_spreadsheet()
    {
        $response = $this->actingAs($this->user)->get(route('stock-reports.excel', [
            'current_team' => $this->team->slug,
            'month' => 8,
            'year' => 2026,
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('OHO 100ml', $response->streamedContent());
    }
}
