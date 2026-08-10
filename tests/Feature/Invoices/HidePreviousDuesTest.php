<?php

namespace Tests\Feature\Invoices;

use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Support\DistributorLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Handing someone a clean invoice must not change what they owe.
 *
 * `hide_previous_dues` is presentation: it decides whether the running account
 * is printed on this one sheet of paper. The balance, the statement and every
 * later invoice's opening figure carry on exactly as if it were off — which is
 * the whole difference between hiding and overriding.
 */
class HidePreviousDuesTest extends TestCase
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
            'stock_quantity' => 1000,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function create(int $quantity, array $overrides = []): Invoice
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
                ...$overrides,
            ],
        )->assertSessionHasNoErrors();

        return Invoice::latest('id')->firstOrFail();
    }

    public function test_hiding_records_the_dues_anyway()
    {
        $this->create(10);
        $second = $this->create(5, ['hide_previous_dues' => true]);

        $this->assertTrue($second->hide_previous_dues);

        // The account is still on the invoice — it is simply not printed.
        $this->assertSame(1000, $second->previous_dues);
        $this->assertSame(1500, $second->total_amount);
    }

    public function test_the_balance_is_the_same_whether_or_not_it_is_hidden()
    {
        $this->create(10);
        $this->create(5, ['hide_previous_dues' => true]);

        $this->assertSame(1500, $this->distributor->refresh()->balance);
    }

    public function test_the_statement_is_unchanged_and_gains_no_adjustment()
    {
        $this->create(10);
        $this->create(5, ['hide_previous_dues' => true]);

        $entries = DistributorLedger::entries($this->distributor->refresh());

        $this->assertSame(
            ['invoice', 'invoice'],
            array_map(fn ($entry) => $entry->type, $entries),
        );
        $this->assertSame(1000, $entries[0]->balanceAfter);
        $this->assertSame(1500, $entries[1]->balanceAfter);
    }

    public function test_a_later_invoice_still_carries_the_full_figure_forward()
    {
        $this->create(10);
        $this->create(5, ['hide_previous_dues' => true]);
        $third = $this->create(2);

        // Hiding on the second changed nothing for the third.
        $this->assertSame(1500, $third->previous_dues);
        $this->assertSame(1700, $third->total_amount);
    }

    public function test_the_invoice_screen_is_told_to_print_it_clean()
    {
        $this->create(10);
        $second = $this->create(5, ['hide_previous_dues' => true]);

        $this->actingAs($this->user)
            ->get(route('invoices.show', [
                'current_team' => $this->team->slug,
                'invoice' => $second->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoice.hidePreviousDues', true)
                // What the printed total falls back to: this invoice alone.
                ->where('invoice.netAmount', 500)
                ->where('invoice.previousDues', 1000)
                ->where('invoice.totalAmount', 1500),
            );
    }

    public function test_it_defaults_to_showing_the_account()
    {
        $invoice = $this->create(10);

        $this->assertFalse($invoice->hide_previous_dues);
    }

    public function test_editing_can_turn_it_on_without_touching_the_account()
    {
        $this->create(10);
        $second = $this->create(5);

        $this->actingAs($this->user)->put(
            route('invoices.update', [
                'current_team' => $this->team->slug,
                'invoice' => $second->id,
            ]),
            [
                'sold_at' => now()->toDateString(),
                'distributor_id' => $this->distributor->id,
                'hide_previous_dues' => true,
                'items' => [[
                    'product_id' => $this->product->id,
                    'total_quantity' => 5,
                    'unit_price' => 100,
                ]],
            ],
        )->assertSessionHasNoErrors();

        $second->refresh();

        $this->assertTrue($second->hide_previous_dues);
        $this->assertNull($second->previous_dues_override);
        $this->assertSame(1000, $second->previous_dues);
        $this->assertSame(1500, $this->distributor->refresh()->balance);
    }
}
