<?php

namespace Tests\Feature\Invoices;

use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChallanTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = $this->user->currentTeam;

        $product = Product::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'ZHO 200ml',
            'carton_size' => 42,
            'distributor_price' => 200,
            'stock_quantity' => 500,
        ]);

        $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            [
                'sold_at' => now()->toDateString(),
                'distributor_id' => Distributor::factory()->create([
                    'team_id' => $this->team->id,
                    'name' => 'Bismillah Treders-Atrai',
                ])->id,
                'items' => [[
                    'product_id' => $product->id,
                    'carton_quantity' => 1,
                    'total_quantity' => 42,
                    'unit_price' => 200,
                    'remarks' => 'Handle with care',
                ]],
            ],
        );

        $this->invoice = Invoice::firstOrFail();
    }

    private function get_challan()
    {
        return $this->actingAs($this->user)->get(route('invoices.challan', [
            'current_team' => $this->team->slug,
            'invoice' => $this->invoice->id,
        ]));
    }

    public function test_the_challan_shows_the_invoice_number_and_quantities()
    {
        $response = $this->get_challan();

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('company/invoices/challan')
            ->where('challan.invoiceNumber', $this->invoice->invoice_number)
            ->where('challan.distributor.name', 'Bismillah Treders-Atrai')
            ->has('challan.items', 1)
            ->where('challan.items.0.productName', 'ZHO 200ml')
            ->where('challan.items.0.cartonQuantity', 1)
            ->where('challan.items.0.totalQuantity', 42)
            ->where('challan.items.0.remarks', 'Handle with care'),
        );
    }

    /**
     * A challan travels with the goods and is handed to whoever signs for
     * them. Prices are absent from the payload, not merely hidden by the
     * template, so no later edit to the page can put money in front of a
     * delivery driver.
     */
    public function test_the_challan_carries_no_prices_at_all()
    {
        $response = $this->get_challan();

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('challan.items.0.unitPrice')
            ->missing('challan.items.0.amount')
            ->missing('challan.items.0.discount')
            ->missing('challan.invoiceTotal')
            ->missing('challan.discountTotal')
            ->missing('challan.previousDues')
            ->missing('challan.totalAmount')
            ->missing('challan.schemeAmount')
            ->missing('challan.distributor.balance'),
        );

        // Belt and braces: the rendered payload contains no amount either.
        $response->assertDontSee('8400', escape: false);
    }

    public function test_a_challan_for_another_companys_invoice_is_not_reachable()
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('invoices.challan', [
            'current_team' => $outsider->currentTeam->slug,
            'invoice' => $this->invoice->id,
        ]))->assertNotFound();
    }

    public function test_guests_cannot_see_a_challan()
    {
        // setUp() signed a user in to create the invoice; drop that first or
        // this request is not a guest's at all.
        auth()->logout();

        $this->get(route('invoices.challan', [
            'current_team' => $this->team->slug,
            'invoice' => $this->invoice->id,
        ]))->assertRedirect(route('login'));
    }

    public function test_the_invoice_screen_still_shows_the_money()
    {
        $response = $this->actingAs($this->user)->get(route('invoices.show', [
            'current_team' => $this->team->slug,
            'invoice' => $this->invoice->id,
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('invoice.invoiceTotal', 8400)
            ->where('invoice.totalAmount', 8400)
            ->where('invoice.items.0.unitPrice', 200)
            ->where('invoice.items.0.amount', 8400),
        );
    }
}
