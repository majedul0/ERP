<?php

namespace Tests\Feature\Invoices;

use App\Actions\Invoices\CreateInvoice;
use App\Enums\DeliveryStatus;
use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CreateInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    private Distributor $distributor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = $this->user->currentTeam;
        $this->distributor = Distributor::factory()->create(['team_id' => $this->team->id]);
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create([
            'team_id' => $this->team->id,
            'distributor_price' => 90,
            'stock_quantity' => 100,
            'carton_size' => 12,
            ...$attributes,
        ]);
    }

    private function payload(array $items, array $overrides = []): array
    {
        return [
            'sold_at' => now()->toDateString(),
            'distributor_id' => $this->distributor->id,
            'items' => $items,
            ...$overrides,
        ];
    }

    public function test_an_invoice_is_written_with_its_lines_and_totals()
    {
        $first = $this->product(['distributor_price' => 90]);
        $second = $this->product(['distributor_price' => 100]);

        $response = $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            $this->payload([
                ['product_id' => $first->id, 'total_quantity' => 12, 'unit_price' => 90],
                ['product_id' => $second->id, 'total_quantity' => 24, 'unit_price' => 100],
            ]),
        );

        $invoice = Invoice::firstOrFail();

        $response->assertRedirect(route('invoices.show', [
            'current_team' => $this->team->slug,
            'invoice' => $invoice->id,
        ]));

        // 12 x 90 + 24 x 100 = 3480, whole units end to end.
        $this->assertSame(3480, $invoice->invoice_total);
        $this->assertSame(3480, $invoice->total_amount);
        $this->assertSame(0, $invoice->previous_dues);
        $this->assertCount(2, $invoice->items);
        $this->assertSame(DeliveryStatus::Pending, $invoice->delivery_status);
    }

    public function test_stock_is_reduced_by_the_quantity_sold()
    {
        $product = $this->product(['stock_quantity' => 100]);

        $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            $this->payload([
                ['product_id' => $product->id, 'total_quantity' => 30, 'unit_price' => 90],
            ]),
        );

        $this->assertSame(70, $product->fresh()->stock_quantity);
    }

    public function test_the_server_prices_the_line_and_ignores_the_amount_the_browser_sent()
    {
        $product = $this->product(['distributor_price' => 90]);

        $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            $this->payload([
                [
                    'product_id' => $product->id,
                    'total_quantity' => 10,
                    'unit_price' => 90,
                    // A hand-edited request claiming the line is worth nothing.
                    'amount' => 0,
                    'discount' => 0,
                ],
            ]),
        );

        $this->assertSame(900, Invoice::firstOrFail()->items->first()->amount);
    }

    public function test_an_invoice_cannot_sell_more_than_is_in_stock()
    {
        $product = $this->product(['stock_quantity' => 5]);

        $response = $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            $this->payload([
                ['product_id' => $product->id, 'total_quantity' => 6, 'unit_price' => 90],
            ]),
        );

        $response->assertSessionHasErrors('items');
        $this->assertSame(0, Invoice::count());
        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_two_lines_of_the_same_product_are_summed_against_stock()
    {
        $product = $this->product(['stock_quantity' => 10]);

        // Six fits. Six again does not — together they are twelve.
        $response = $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            $this->payload([
                ['product_id' => $product->id, 'total_quantity' => 6, 'unit_price' => 90],
                ['product_id' => $product->id, 'total_quantity' => 6, 'unit_price' => 90],
            ]),
        );

        $response->assertSessionHasErrors('items');
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_a_failed_invoice_leaves_no_partial_write()
    {
        $good = $this->product(['stock_quantity' => 100]);
        $bad = $this->product(['stock_quantity' => 1]);

        $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            $this->payload([
                ['product_id' => $good->id, 'total_quantity' => 5, 'unit_price' => 90],
                ['product_id' => $bad->id, 'total_quantity' => 50, 'unit_price' => 90],
            ]),
        );

        $this->assertSame(0, Invoice::count());
        $this->assertDatabaseCount('invoice_items', 0);
        $this->assertSame(100, $good->fresh()->stock_quantity);
        $this->assertSame(0, $this->distributor->fresh()->balance);
    }

    public function test_previous_dues_carry_forward_onto_the_next_invoice()
    {
        $product = $this->product(['stock_quantity' => 100, 'distributor_price' => 100]);
        $url = route('invoices.store', ['current_team' => $this->team->slug]);

        $this->actingAs($this->user)->post($url, $this->payload([
            ['product_id' => $product->id, 'total_quantity' => 10, 'unit_price' => 100],
        ]));

        $this->assertSame(1000, $this->distributor->fresh()->balance);

        $this->actingAs($this->user)->post($url, $this->payload([
            ['product_id' => $product->id, 'total_quantity' => 5, 'unit_price' => 100],
        ]));

        $second = Invoice::latest('id')->firstOrFail();

        $this->assertSame(1000, $second->previous_dues);
        $this->assertSame(500, $second->invoice_total);
        $this->assertSame(1500, $second->total_amount);
        $this->assertSame(1500, $this->distributor->fresh()->balance);
    }

    public function test_discounts_and_scheme_amounts_come_off_the_total()
    {
        $product = $this->product(['stock_quantity' => 100]);

        $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            $this->payload(
                [[
                    'product_id' => $product->id,
                    'total_quantity' => 10,
                    'unit_price' => 100,
                    'discount' => 50,
                ]],
                ['scheme_amount' => 25, 'scheme_description' => 'Eid offer'],
            ),
        );

        $invoice = Invoice::firstOrFail();

        $this->assertSame(1000, $invoice->invoice_total);
        $this->assertSame(50, $invoice->discount_total);
        $this->assertSame(25, $invoice->scheme_amount);
        $this->assertSame(925, $invoice->total_amount);
    }

    public function test_a_line_falls_back_to_the_product_price_when_none_is_given()
    {
        $product = $this->product(['distributor_price' => 123]);

        $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            $this->payload([
                ['product_id' => $product->id, 'total_quantity' => 2],
            ]),
        );

        $this->assertSame(123, Invoice::firstOrFail()->items->first()->unit_price);
    }

    public function test_line_items_keep_the_name_the_product_had_when_it_sold()
    {
        $product = $this->product(['name' => 'OFC 15gm']);

        $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            $this->payload([
                ['product_id' => $product->id, 'total_quantity' => 1, 'unit_price' => 90],
            ]),
        );

        $product->update(['name' => 'OFC 15gm (discontinued)']);

        $this->assertSame(
            'OFC 15gm',
            Invoice::firstOrFail()->items->first()->product_name,
        );
    }

    public function test_another_companys_product_cannot_be_sold()
    {
        $foreign = Product::factory()->create(['stock_quantity' => 100]);

        $response = $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            $this->payload([
                ['product_id' => $foreign->id, 'total_quantity' => 1, 'unit_price' => 90],
            ]),
        );

        $response->assertSessionHasErrors('items.0.product_id');
        $this->assertSame(0, Invoice::count());
    }

    public function test_another_companys_distributor_cannot_be_invoiced()
    {
        $product = $this->product();
        $foreign = Distributor::factory()->create();

        $response = $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            $this->payload(
                [['product_id' => $product->id, 'total_quantity' => 1, 'unit_price' => 90]],
                ['distributor_id' => $foreign->id],
            ),
        );

        $response->assertSessionHasErrors('distributor_id');
        $this->assertSame(0, Invoice::count());
    }

    public function test_an_invoice_needs_at_least_one_line()
    {
        $response = $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            $this->payload([]),
        );

        $response->assertSessionHasErrors('items');
    }

    public function test_the_action_reports_stock_shortfalls_by_name()
    {
        $product = $this->product(['name' => 'OSC 15gm', 'stock_quantity' => 2]);

        try {
            app(CreateInvoice::class)->handle($this->team, $this->user, [
                'distributor_id' => $this->distributor->id,
                'sold_at' => now()->toDateString(),
                'items' => [
                    ['product_id' => $product->id, 'total_quantity' => 3],
                ],
            ]);

            $this->fail('Expected the invoice to be rejected.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('OSC 15gm', $e->getMessage());
            $this->assertStringContainsString('2 in stock', $e->getMessage());
        }
    }

    public function test_the_create_screen_lists_the_companys_products_and_distributors()
    {
        $this->product(['name' => 'OFC 15gm']);
        Product::factory()->create(['name' => 'Someone Elses Product']);

        $response = $this->actingAs($this->user)->get(
            route('invoices.create', ['current_team' => $this->team->slug]),
        );

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('company/invoices/create')
            ->has('products', 1)
            ->where('products.0.name', 'OFC 15gm')
            ->has('distributors', 1)
            ->has('nextInvoiceNumber'),
        );
    }
}
