<?php

namespace Tests\Feature\Invoices;

use App\Enums\DeliveryStatus;
use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Support\StockVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The counter behind live stock on an open invoice form: one Redis read tells
 * a browser whether anything moved, so the product list is only refetched when
 * it actually has.
 */
class StockVersionTest extends TestCase
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
            'stock_quantity' => 100,
        ]);
    }

    private function version(): int
    {
        return StockVersion::current($this->team->id);
    }

    private function sell(int $quantity): Invoice
    {
        $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            [
                'sold_at' => now()->toDateString(),
                'distributor_id' => $this->distributor->id,
                'items' => [[
                    'product_id' => $this->product->id,
                    'total_quantity' => $quantity,
                    'unit_price' => 100,
                ]],
            ],
        );

        return Invoice::latest('id')->firstOrFail();
    }

    public function test_selling_moves_the_version()
    {
        $before = $this->version();

        $this->sell(10);

        $this->assertGreaterThan($before, $this->version());
    }

    public function test_recounting_a_shelf_moves_the_version()
    {
        $before = $this->version();

        $this->actingAs($this->user)->put(
            route('products.update', [
                'current_team' => $this->team->slug,
                'product' => $this->product->id,
            ]),
            [
                'name' => $this->product->name,
                'sku' => $this->product->sku,
                'carton_size' => $this->product->carton_size,
                'distributor_price' => $this->product->distributor_price,
                'trade_price' => $this->product->trade_price,
                'mrp' => $this->product->mrp,
                'stock_quantity' => 42,
            ],
        );

        $this->assertSame(42, $this->product->fresh()->stock_quantity);
        $this->assertGreaterThan($before, $this->version());
    }

    public function test_cancelling_an_invoice_moves_the_version()
    {
        $invoice = $this->sell(10);
        $before = $this->version();

        $this->actingAs($this->user)->patch(
            route('invoices.status.update', [
                'current_team' => $this->team->slug,
                'invoice' => $invoice->id,
            ]),
            ['delivery_status' => DeliveryStatus::Cancelled->value],
        );

        $this->assertGreaterThan($before, $this->version());
    }

    /**
     * Renaming a product leaves stock alone, so an open form has nothing to
     * refetch — the whole point of a version rather than a timer.
     */
    public function test_a_change_that_does_not_touch_stock_leaves_the_version_alone()
    {
        $this->sell(10);
        $before = $this->version();

        $this->actingAs($this->user)->put(
            route('products.update', [
                'current_team' => $this->team->slug,
                'product' => $this->product->id,
            ]),
            [
                'name' => 'Renamed only',
                'sku' => $this->product->sku,
                'carton_size' => $this->product->carton_size,
                'distributor_price' => 250,
                'trade_price' => $this->product->trade_price,
                'mrp' => $this->product->mrp,
                'stock_quantity' => $this->product->fresh()->stock_quantity,
            ],
        );

        $this->assertSame('Renamed only', $this->product->fresh()->name);
        $this->assertSame($before, $this->version());
    }

    public function test_one_companys_selling_does_not_disturb_anothers_forms()
    {
        $outsider = User::factory()->create();
        $before = StockVersion::current($outsider->currentTeam->id);

        $this->sell(10);

        $this->assertSame($before, StockVersion::current($outsider->currentTeam->id));
    }

    public function test_the_create_screen_ships_the_current_version()
    {
        $this->sell(10);

        $this->actingAs($this->user)
            ->get(route('invoices.create', ['current_team' => $this->team->slug]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stockVersion', $this->version()),
            );
    }

    public function test_the_version_endpoint_answers_with_the_current_number()
    {
        $this->sell(10);

        $this->actingAs($this->user)
            ->getJson(route('stock.version', ['current_team' => $this->team->slug]))
            ->assertOk()
            ->assertExactJson(['version' => $this->version()]);
    }

    public function test_guests_cannot_read_the_version()
    {
        $this->getJson(route('stock.version', ['current_team' => $this->team->slug]))
            ->assertUnauthorized();
    }

    /**
     * The browser refetches with a partial reload, so the response has to carry
     * the fresh products without rebuilding the rest of the page.
     */
    public function test_a_partial_reload_returns_the_updated_stock()
    {
        $this->sell(30);

        $response = $this->actingAs($this->user)->get(
            route('invoices.create', ['current_team' => $this->team->slug]),
            [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => Inertia::getVersion(),
                'X-Inertia-Partial-Component' => 'company/invoices/create',
                'X-Inertia-Partial-Data' => 'products,stockVersion',
            ],
        );

        $response->assertOk();

        $payload = $response->json('props');

        $this->assertSame(70, $payload['products'][0]['stockQuantity']);
        $this->assertArrayHasKey('stockVersion', $payload);
        $this->assertArrayNotHasKey('distributors', $payload);
    }
}
