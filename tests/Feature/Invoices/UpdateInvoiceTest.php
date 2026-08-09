<?php

namespace Tests\Feature\Invoices;

use App\Enums\DeliveryStatus;
use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UpdateInvoiceTest extends TestCase
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
            'name' => 'ZHO 200ml',
            'distributor_price' => 100,
            'stock_quantity' => 100,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function create(array $items, array $overrides = []): Invoice
    {
        $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            [
                'sold_at' => now()->toDateString(),
                'distributor_id' => $this->distributor->id,
                'items' => $items,
                ...$overrides,
            ],
        );

        return Invoice::latest('id')->firstOrFail();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function update(Invoice $invoice, array $items, array $overrides = [])
    {
        return $this->actingAs($this->user)->put(
            route('invoices.update', [
                'current_team' => $this->team->slug,
                'invoice' => $invoice->id,
            ]),
            [
                'sold_at' => now()->toDateString(),
                'distributor_id' => $this->distributor->id,
                'items' => $items,
                ...$overrides,
            ],
        );
    }

    public function test_lines_and_totals_are_rewritten()
    {
        $invoice = $this->create([
            ['product_id' => $this->product->id, 'total_quantity' => 10, 'unit_price' => 100],
        ]);

        $response = $this->update($invoice, [
            ['product_id' => $this->product->id, 'total_quantity' => 4, 'unit_price' => 150],
        ]);

        $response->assertRedirect(route('invoices.show', [
            'current_team' => $this->team->slug,
            'invoice' => $invoice->id,
        ]));

        $invoice->refresh();

        $this->assertSame(600, $invoice->invoice_total);
        $this->assertSame(600, $invoice->total_amount);
        $this->assertCount(1, $invoice->items);
        $this->assertSame(4, $invoice->items->first()->total_quantity);
    }

    public function test_the_invoice_number_never_changes()
    {
        $invoice = $this->create([
            ['product_id' => $this->product->id, 'total_quantity' => 5, 'unit_price' => 100],
        ]);

        $number = $invoice->invoice_number;

        $this->update($invoice, [
            ['product_id' => $this->product->id, 'total_quantity' => 6, 'unit_price' => 100],
        ]);

        $this->assertSame($number, $invoice->fresh()->invoice_number);
        $this->assertSame(1, Invoice::count());
    }

    public function test_stock_is_returned_and_retaken_for_the_new_quantity()
    {
        $invoice = $this->create([
            ['product_id' => $this->product->id, 'total_quantity' => 30, 'unit_price' => 100],
        ]);

        $this->assertSame(70, $this->product->fresh()->stock_quantity);

        $this->update($invoice, [
            ['product_id' => $this->product->id, 'total_quantity' => 10, 'unit_price' => 100],
        ]);

        $this->assertSame(90, $this->product->fresh()->stock_quantity);
    }

    /**
     * The old quantity goes back before the new one is checked, so an edit is
     * measured against stock that already includes what it was holding.
     */
    public function test_an_edit_may_use_the_stock_the_invoice_already_held()
    {
        $product = Product::factory()->create([
            'team_id' => $this->team->id,
            'distributor_price' => 100,
            'stock_quantity' => 10,
        ]);

        $invoice = $this->create([
            ['product_id' => $product->id, 'total_quantity' => 10, 'unit_price' => 100],
        ]);

        $this->assertSame(0, $product->fresh()->stock_quantity);

        // Nothing is on the shelf, yet raising this line to the full 10 again
        // must work — it is the same goods.
        $this->update($invoice, [
            ['product_id' => $product->id, 'total_quantity' => 10, 'unit_price' => 120],
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertSame(1200, $invoice->fresh()->invoice_total);
    }

    public function test_an_edit_beyond_available_stock_is_refused_and_changes_nothing()
    {
        $product = Product::factory()->create([
            'team_id' => $this->team->id,
            'distributor_price' => 100,
            'stock_quantity' => 10,
        ]);

        $invoice = $this->create([
            ['product_id' => $product->id, 'total_quantity' => 5, 'unit_price' => 100],
        ]);

        $this->update($invoice, [
            ['product_id' => $product->id, 'total_quantity' => 11, 'unit_price' => 100],
        ])->assertSessionHasErrors('items');

        $this->assertSame(500, $invoice->fresh()->invoice_total);
        $this->assertSame(5, $product->fresh()->stock_quantity);
        $this->assertSame(5, $invoice->fresh()->items->first()->total_quantity);
    }

    /**
     * Every later invoice carries this one's balance forward, so editing an
     * early invoice has to replay the chain rather than patch one row.
     */
    public function test_editing_an_earlier_invoice_rewrites_the_whole_dues_chain()
    {
        $first = $this->create([
            ['product_id' => $this->product->id, 'total_quantity' => 10, 'unit_price' => 100],
        ]);
        $second = $this->create([
            ['product_id' => $this->product->id, 'total_quantity' => 5, 'unit_price' => 100],
        ]);
        $third = $this->create([
            ['product_id' => $this->product->id, 'total_quantity' => 2, 'unit_price' => 100],
        ]);

        $this->assertSame(1700, $this->distributor->fresh()->balance);

        // 1000 becomes 400: every figure after it moves by 600.
        $this->update($first, [
            ['product_id' => $this->product->id, 'total_quantity' => 4, 'unit_price' => 100],
        ]);

        $this->assertSame(0, $first->fresh()->previous_dues);
        $this->assertSame(400, $first->fresh()->total_amount);

        $this->assertSame(400, $second->fresh()->previous_dues);
        $this->assertSame(900, $second->fresh()->total_amount);

        $this->assertSame(900, $third->fresh()->previous_dues);
        $this->assertSame(1100, $third->fresh()->total_amount);

        $this->assertSame(1100, $this->distributor->fresh()->balance);
    }

    public function test_moving_an_invoice_to_another_distributor_rebuilds_both_ledgers()
    {
        $other = Distributor::factory()->create(['team_id' => $this->team->id]);

        $invoice = $this->create([
            ['product_id' => $this->product->id, 'total_quantity' => 10, 'unit_price' => 100],
        ]);

        $this->assertSame(1000, $this->distributor->fresh()->balance);

        $this->update(
            $invoice,
            [['product_id' => $this->product->id, 'total_quantity' => 10, 'unit_price' => 100]],
            ['distributor_id' => $other->id],
        );

        $this->assertSame(0, $this->distributor->fresh()->balance);
        $this->assertSame(1000, $other->fresh()->balance);
    }

    public function test_a_voided_invoice_can_be_edited_without_touching_stock()
    {
        $invoice = $this->create([
            ['product_id' => $this->product->id, 'total_quantity' => 10, 'unit_price' => 100],
        ]);

        $this->actingAs($this->user)->patch(
            route('invoices.status.update', [
                'current_team' => $this->team->slug,
                'invoice' => $invoice->id,
            ]),
            ['delivery_status' => DeliveryStatus::Cancelled->value],
        );

        $this->assertSame(100, $this->product->fresh()->stock_quantity);

        $this->update($invoice, [
            ['product_id' => $this->product->id, 'total_quantity' => 3, 'unit_price' => 100],
        ])->assertSessionHasNoErrors();

        // Still cancelled, so the goods stay on the shelf and nothing is owed.
        $this->assertSame(100, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, $this->distributor->fresh()->balance);
        $this->assertSame(300, $invoice->fresh()->invoice_total);
    }

    public function test_the_edit_screen_is_prefilled_with_the_current_invoice()
    {
        $invoice = $this->create([
            ['product_id' => $this->product->id, 'total_quantity' => 7, 'unit_price' => 111],
        ]);

        $response = $this->actingAs($this->user)->get(route('invoices.edit', [
            'current_team' => $this->team->slug,
            'invoice' => $invoice->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('company/invoices/edit')
            ->where('invoice.invoiceNumber', $invoice->invoice_number)
            ->where('invoice.items.0.totalQuantity', 7)
            ->where('invoice.items.0.unitPrice', 111)
            ->has('distributors')
            ->has('products'),
        );
    }

    /**
     * The challan is rendered from the invoice, never stored, so an edit shows
     * up on it with nothing to regenerate.
     */
    public function test_the_challan_reflects_the_edit_immediately()
    {
        $invoice = $this->create([
            ['product_id' => $this->product->id, 'total_quantity' => 12, 'unit_price' => 100],
        ]);

        $second = Product::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'BHCO 200ml',
            'distributor_price' => 150,
            'stock_quantity' => 100,
        ]);

        $this->update($invoice, [
            ['product_id' => $this->product->id, 'total_quantity' => 20, 'unit_price' => 100],
            ['product_id' => $second->id, 'total_quantity' => 5, 'unit_price' => 150, 'remarks' => 'New line'],
        ]);

        $this->actingAs($this->user)
            ->get(route('invoices.challan', [
                'current_team' => $this->team->slug,
                'invoice' => $invoice->id,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('challan.items', 2)
                ->where('challan.items.0.totalQuantity', 20)
                ->where('challan.items.1.productName', 'BHCO 200ml')
                ->where('challan.items.1.remarks', 'New line'),
            );
    }

    /**
     * The payload is entirely valid *for the outsider's own company* — their
     * own distributor, their own product — and only the invoice id belongs to
     * someone else. That is the request the tenancy guard exists to stop; a
     * payload full of foreign ids never reaches it, because validation scopes
     * every id to the current company first.
     */
    public function test_another_companys_invoice_cannot_be_edited()
    {
        $invoice = $this->create([
            ['product_id' => $this->product->id, 'total_quantity' => 5, 'unit_price' => 100],
        ]);

        $outsider = User::factory()->create();
        $theirTeam = $outsider->currentTeam;

        $this->actingAs($outsider)->put(
            route('invoices.update', [
                'current_team' => $theirTeam->slug,
                'invoice' => $invoice->id,
            ]),
            [
                'sold_at' => now()->toDateString(),
                'distributor_id' => Distributor::factory()->create([
                    'team_id' => $theirTeam->id,
                ])->id,
                'items' => [[
                    'product_id' => Product::factory()->create([
                        'team_id' => $theirTeam->id,
                        'stock_quantity' => 100,
                    ])->id,
                    'total_quantity' => 1,
                ]],
            ],
        )->assertNotFound();

        $this->assertSame(500, $invoice->fresh()->invoice_total);
        $this->assertSame(5, $invoice->fresh()->items->first()->total_quantity);
    }

    public function test_ids_from_another_company_are_rejected_by_validation()
    {
        $invoice = $this->create([
            ['product_id' => $this->product->id, 'total_quantity' => 5, 'unit_price' => 100],
        ]);

        $foreign = Product::factory()->create(['stock_quantity' => 100]);

        $this->update($invoice, [
            ['product_id' => $foreign->id, 'total_quantity' => 1],
        ])->assertSessionHasErrors('items.0.product_id');

        $this->assertSame(500, $invoice->fresh()->invoice_total);
    }

    public function test_an_edit_still_needs_at_least_one_line()
    {
        $invoice = $this->create([
            ['product_id' => $this->product->id, 'total_quantity' => 5, 'unit_price' => 100],
        ]);

        $this->update($invoice, [])->assertSessionHasErrors('items');

        $this->assertCount(1, $invoice->fresh()->items);
    }
}
