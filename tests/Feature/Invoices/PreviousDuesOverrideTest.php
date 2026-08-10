<?php

namespace Tests\Feature\Invoices;

use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Support\DistributorLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A figure typed into Previous Dues changes what one invoice prints. It does
 * not change what the distributor owes.
 *
 * The account is a plain running total — balance, plus what each invoice
 * charges, less what each payment settles. It used to be restated by anything
 * typed here, which meant a number entered for a customer's benefit silently
 * rewrote their balance, and an accidental one wiped out an advance they had
 * actually paid.
 */
class PreviousDuesOverrideTest extends TestCase
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
        );

        return Invoice::latest('id')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function typed(int $previousDues): array
    {
        // The opt-in a real "Set manually" submission carries. A bare
        // `previous_dues` is what a stale client sends and must be ignored.
        return [
            'previous_dues_override' => true,
            'previous_dues' => $previousDues,
        ];
    }

    public function test_a_typed_figure_is_what_the_invoice_prints()
    {
        $invoice = $this->create(5, $this->typed(2500));

        $this->assertSame(2500, $invoice->previous_dues_override);

        // 2500 printed as dues, plus 500 of goods.
        $this->assertSame(3000, $invoice->total_amount);
    }

    public function test_a_typed_figure_does_not_move_the_balance()
    {
        $invoice = $this->create(5, $this->typed(2500));

        // 500 of goods, and nothing else. The 2500 was for the paper.
        $this->assertSame(500, $this->distributor->fresh()->balance);
        $this->assertSame(0, $invoice->previous_dues);
    }

    /**
     * The case that made this print-only: a distributor in credit, handed an
     * invoice that does not mention it, still keeps the credit.
     */
    public function test_an_advance_survives_an_invoice_that_prints_zero_dues()
    {
        $this->actingAs($this->user)->post(
            route('payments.store', ['current_team' => $this->team->slug]),
            [
                'distributor_id' => $this->distributor->id,
                'paid_on' => now()->subDay()->toDateString(),
                'amount' => 80_000,
            ],
        )->assertSessionHasNoErrors();

        $this->create(100, $this->typed(0));

        // 80,000 in credit, less 10,000 of goods.
        $this->assertSame(-70_000, $this->distributor->fresh()->balance);
    }

    public function test_each_invoice_takes_its_own_total_off_the_balance()
    {
        $this->create(200, $this->typed(0));   // 20,000
        $this->create(100, $this->typed(0));   // 10,000

        // Whatever the paper said, the account just kept adding the goods.
        $this->assertSame(30_000, $this->distributor->fresh()->balance);
    }

    public function test_the_statement_never_shows_an_adjustment()
    {
        $this->create(10);
        $this->create(5, $this->typed(5000));

        $entries = DistributorLedger::entries($this->distributor->fresh());

        $this->assertSame(
            ['invoice', 'invoice'],
            array_map(fn ($entry) => $entry->type, $entries),
        );
        $this->assertSame(1000, $entries[0]->balanceAfter);
        $this->assertSame(1500, $entries[1]->balanceAfter);
    }

    public function test_the_statement_still_reconciles_line_by_line()
    {
        $this->create(10);
        $this->create(5, $this->typed(5000));

        $balance = 0;

        foreach (DistributorLedger::entries($this->distributor->fresh()) as $entry) {
            $balance = $balance + $entry->debit - $entry->credit;
            $this->assertSame($balance, $entry->balanceAfter);
        }

        $this->assertSame($balance, $this->distributor->fresh()->balance);
    }

    public function test_nothing_is_deleted_when_a_figure_is_typed()
    {
        $first = $this->create(10);

        $this->actingAs($this->user)->post(
            route('payments.store', ['current_team' => $this->team->slug]),
            [
                'distributor_id' => $this->distributor->id,
                'paid_on' => now()->toDateString(),
                'amount' => 200,
            ],
        );

        $this->create(5, $this->typed(5000));

        $this->assertSame(3, Invoice::count() + Payment::count());
        $this->assertSame(1000, $first->fresh()->invoice_total);
        $this->assertSame(200, Payment::firstOrFail()->amount);
    }

    public function test_a_later_invoice_carries_the_real_account_forward()
    {
        $this->create(10);
        $this->create(5, $this->typed(5000));
        $third = $this->create(2);

        // The 5000 printed on the second changed nothing for the third.
        $this->assertSame(1500, $third->previous_dues);
        $this->assertSame(1700, $third->total_amount);
    }

    public function test_the_invoice_screen_prints_the_typed_figure()
    {
        $this->create(10);
        $second = $this->create(5, $this->typed(5000));

        $this->actingAs($this->user)
            ->get(route('invoices.show', [
                'current_team' => $this->team->slug,
                'invoice' => $second->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoice.previousDues', 5000)
                ->where('invoice.accountPreviousDues', 1000)
                ->where('invoice.totalAmount', 5500),
            );
    }

    public function test_editing_can_set_a_figure_without_moving_the_balance()
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
                'previous_dues_override' => true,
                'previous_dues' => 250,
                'items' => [[
                    'product_id' => $this->product->id,
                    'total_quantity' => 5,
                    'unit_price' => 100,
                ]],
            ],
        )->assertSessionHasNoErrors();

        $this->assertSame(250, $second->fresh()->previous_dues_override);
        $this->assertSame(750, $second->fresh()->total_amount);

        // Two invoices of 1000 and 500, and nothing else.
        $this->assertSame(1500, $this->distributor->fresh()->balance);
    }

    public function test_editing_without_the_opt_in_drops_the_figure()
    {
        $this->create(10);
        $second = $this->create(5, $this->typed(250));

        $this->assertSame(250, $second->previous_dues_override);

        $this->actingAs($this->user)->put(
            route('invoices.update', [
                'current_team' => $this->team->slug,
                'invoice' => $second->id,
            ]),
            [
                'sold_at' => now()->toDateString(),
                'distributor_id' => $this->distributor->id,
                'items' => [[
                    'product_id' => $this->product->id,
                    'total_quantity' => 5,
                    'unit_price' => 100,
                ]],
            ],
        )->assertSessionHasNoErrors();

        $this->assertNull($second->fresh()->previous_dues_override);
        $this->assertSame(1000, $second->fresh()->previous_dues);
        $this->assertSame(1500, $second->fresh()->total_amount);
    }

    public function test_a_fractional_figure_is_refused()
    {
        $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            [
                'sold_at' => now()->toDateString(),
                'distributor_id' => $this->distributor->id,
                'previous_dues_override' => true,
                'previous_dues' => 250.5,
                'items' => [[
                    'product_id' => $this->product->id,
                    'total_quantity' => 1,
                ]],
            ],
        )->assertSessionHasErrors('previous_dues');

        $this->assertSame(0, Invoice::count());
    }
}
