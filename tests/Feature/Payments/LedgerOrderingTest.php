<?php

namespace Tests\Feature\Payments;

use App\Models\Bank;
use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Support\DistributorLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * On a shared date the account follows the order things were entered.
 *
 * Money paid in advance and then invoiced against the same day is the ordinary
 * way this trade works, and the previous rule — invoices always before payments
 * on a shared day — reordered it into nonsense.
 */
class LedgerOrderingTest extends TestCase
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
            'distributor_price' => 1,
            'stock_quantity' => 1_000_000,
        ]);
    }

    /**
     * Timestamps are stored to the second, so the clock is advanced between
     * entries to model people working at human speed rather than three
     * documents landing in the same second.
     */
    private function sell(int $amount): void
    {
        $this->travel(1)->minutes();

        $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            [
                'distributor_id' => $this->distributor->id,
                'sold_at' => now()->toDateString(),
                'items' => [[
                    'product_id' => $this->product->id,
                    'total_quantity' => $amount,
                    'unit_price' => 1,
                ]],
            ],
        )->assertSessionHasNoErrors();
    }

    private function pay(int $amount): void
    {
        $this->travel(1)->minutes();

        $this->actingAs($this->user)->post(
            route('payments.store', ['current_team' => $this->team->slug]),
            [
                'distributor_id' => $this->distributor->id,
                'bank_id' => Bank::factory()->create(['team_id' => $this->team->id])->id,
                'amount' => $amount,
                'paid_on' => now()->toDateString(),
            ],
        )->assertSessionHasNoErrors();
    }

    public function test_an_advance_paid_first_sorts_before_the_invoices_it_covers()
    {
        $this->pay(80_000);
        $this->sell(10_800);
        $this->sell(21_600);

        $entries = DistributorLedger::entries($this->distributor->refresh());

        $this->assertSame(
            ['payment', 'invoice', 'invoice'],
            array_map(fn ($entry) => $entry->type, $entries),
        );

        // 80,000 in credit, drawn down by 10,800 and then 21,600.
        $this->assertSame(-80_000, $entries[0]->balanceAfter);
        $this->assertSame(-69_200, $entries[1]->balanceAfter);
        $this->assertSame(-47_600, $entries[2]->balanceAfter);

        $this->assertSame(-47_600, $this->distributor->refresh()->balance);
    }

    public function test_no_adjustment_line_appears_when_nobody_typed_an_opening_balance()
    {
        $this->pay(80_000);
        $this->sell(10_800);
        $this->sell(21_600);

        $types = array_map(
            fn ($entry) => $entry->type,
            DistributorLedger::entries($this->distributor->refresh()),
        );

        $this->assertNotContains('adjustment', $types);
    }

    public function test_each_invoice_carries_the_running_figure_forward()
    {
        $this->pay(80_000);
        $this->sell(10_800);
        $this->sell(21_600);

        [$first, $second] = Invoice::orderBy('id')->get()->all();

        $this->assertSame(-80_000, $first->previous_dues);
        $this->assertSame(-69_200, $first->total_amount);
        $this->assertSame(-69_200, $second->previous_dues);
        $this->assertSame(-47_600, $second->total_amount);
    }

    /**
     * The case the old rule was written for still behaves: settle the day's
     * invoice and the payment lands after it.
     */
    public function test_a_payment_entered_after_an_invoice_still_follows_it()
    {
        $this->sell(10_000);
        $this->pay(4_000);

        $entries = DistributorLedger::entries($this->distributor->refresh());

        $this->assertSame(
            ['invoice', 'payment'],
            array_map(fn ($entry) => $entry->type, $entries),
        );

        $this->assertSame(10_000, $entries[0]->balanceAfter);
        $this->assertSame(6_000, $entries[1]->balanceAfter);
    }

    /**
     * A backdated document sorts by its own date, not by when it was typed.
     */
    public function test_the_document_date_still_wins_over_entry_time()
    {
        $this->sell(10_000);

        // Entered now, but dated a week ago.
        $this->travel(1)->minutes();

        $this->actingAs($this->user)->post(
            route('payments.store', ['current_team' => $this->team->slug]),
            [
                'distributor_id' => $this->distributor->id,
                'amount' => 4_000,
                'paid_on' => now()->subWeek()->toDateString(),
            ],
        )->assertSessionHasNoErrors();

        $entries = DistributorLedger::entries($this->distributor->refresh());

        $this->assertSame(
            ['payment', 'invoice'],
            array_map(fn ($entry) => $entry->type, $entries),
        );
    }
}
