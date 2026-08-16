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

    /**
     * The list must agree with the paper.
     *
     * An invoice printed without its dues line totals the goods alone, so that
     * is what the Amount column shows for it. Reading `total_amount` regardless
     * made the two disagree, and read absurdly when the account was in credit:
     * a sale settled by an equal advance printed its full value and listed as
     * zero.
     */
    public function test_a_hidden_invoice_lists_the_amount_it_prints()
    {
        // A 2,000 advance, then a 500 sale against it. The account is in
        // credit, so `total_amount` for the sale is negative.
        $this->actingAs($this->user)->post(
            route('payments.store', ['current_team' => $this->team->slug]),
            [
                'distributor_id' => $this->distributor->id,
                'amount' => 2_000,
                'paid_on' => now()->subDay()->toDateString(),
            ],
        )->assertSessionHasNoErrors();

        $hidden = $this->create(5, ['hide_previous_dues' => true]);

        // The stored figures are untouched — the account still knows.
        $this->assertSame(-2_000, $hidden->previous_dues);
        $this->assertSame(-1_500, $hidden->total_amount);

        $this->actingAs($this->user)
            ->get(route('invoices.index', ['current_team' => $this->team->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // 500 — the goods, which is what the paper says.
                ->where('invoices.0.amount', 500)
                ->where('invoices.0.netAmount', 500),
            );
    }

    public function test_an_ordinary_invoice_still_lists_its_printed_total()
    {
        $this->create(10);
        $this->create(5);

        $this->actingAs($this->user)
            ->get(route('invoices.index', ['current_team' => $this->team->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // Goods plus what was already owed, exactly as printed.
                ->where('invoices.0.amount', 1_500)
                ->where('invoices.0.netAmount', 500),
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
