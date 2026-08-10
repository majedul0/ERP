<?php

namespace Tests\Feature\Invoices;

use App\Models\Bank;
use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A distributor who has paid more than they owe is in credit, and the next
 * invoice draws that credit down.
 *
 * This is the case that broke: the invoice form always submitted the balance
 * it was displaying, so a form opened before a payment landed sent a stale
 * figure, the server could not tell it from a deliberate opening balance, and
 * the replay turned it into an adjustment that erased the advance.
 */
class AdvanceBalanceTest extends TestCase
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
     * @param  array<string, mixed>  $overrides
     */
    private function invoice(int $amount, string $soldAt = '-3 days', array $overrides = [])
    {
        return $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            [
                'distributor_id' => $this->distributor->id,
                'sold_at' => now()->modify($soldAt)->toDateString(),
                'items' => [[
                    'product_id' => $this->product->id,
                    'total_quantity' => $amount,
                    'unit_price' => 1,
                ]],
                ...$overrides,
            ],
        );
    }

    /**
     * Dates matter here: the ledger orders by document date, and on a shared
     * day invoices come before payments. Spacing these out models the real
     * sequence — sell, get paid, sell again — rather than three documents
     * landing on one day in an order nobody chose.
     */
    private function pay(int $amount, string $paidOn = '-2 days'): void
    {
        $this->actingAs($this->user)->post(
            route('payments.store', ['current_team' => $this->team->slug]),
            [
                'distributor_id' => $this->distributor->id,
                'bank_id' => Bank::factory()->create(['team_id' => $this->team->id])->id,
                'amount' => $amount,
                'paid_on' => now()->modify($paidOn)->toDateString(),
            ],
        )->assertSessionHasNoErrors();
    }

    public function test_an_overpayment_leaves_the_distributor_in_credit()
    {
        $this->invoice(50_000)->assertSessionHasNoErrors();
        $this->pay(90_000);

        $this->assertSame(-40_000, $this->distributor->refresh()->balance);
    }

    public function test_the_next_invoice_draws_the_credit_down()
    {
        $this->invoice(50_000);
        $this->pay(90_000);

        // Previous dues omitted, exactly as an untouched form now submits.
        $this->invoice(10_260, 'now')->assertSessionHasNoErrors();

        // 40,000 in credit, less a 10,260 invoice, is 29,740 still in credit.
        $this->assertSame(-29_740, $this->distributor->refresh()->balance);

        $second = Invoice::orderByDesc('id')->firstOrFail();
        $this->assertSame(-40_000, $second->previous_dues);
        $this->assertSame(-29_740, $second->total_amount);
    }

    /**
     * The case that caused the bug: a form opened while the balance was 0,
     * submitted after a payment has moved it. That figure is stale, not a
     * decision, so it must not become an override.
     */
    public function test_a_stale_previous_dues_figure_is_not_treated_as_an_override()
    {
        $this->invoice(50_000);
        $this->pay(90_000);

        $this->invoice(10_260, 'now', ['previous_dues' => null])->assertSessionHasNoErrors();

        $second = Invoice::orderByDesc('id')->firstOrFail();

        $this->assertNull($second->previous_dues_override);
        $this->assertSame(-29_740, $this->distributor->refresh()->balance);
    }

    /**
     * The defence that does not depend on the browser being up to date: a
     * client sending a figure *without* the opt-in cannot pin anything.
     *
     * An old cached bundle kept submitting the number it was displaying, which
     * pinned invoice after invoice. The server now ignores a figure nobody
     * explicitly asked to apply.
     */
    public function test_a_figure_without_the_opt_in_flag_is_ignored()
    {
        $this->invoice(50_000);
        $this->pay(90_000);

        // Exactly what the stale client sent: a bare figure, no opt-in.
        $this->invoice(10_260, 'now', ['previous_dues' => 0])->assertSessionHasNoErrors();

        $second = Invoice::orderByDesc('id')->firstOrFail();

        $this->assertNull($second->previous_dues_override);
        $this->assertSame(-40_000, $second->previous_dues);
        $this->assertSame(-29_740, $this->distributor->refresh()->balance);
    }

    /**
     * Opting in explicitly changes what the invoice prints — and only that.
     *
     * Printing "Previous Dues 0" hands the distributor a clean bill for these
     * goods. It does not forgive the advance they already paid, so the account
     * carries on as if the field had never been touched.
     */
    public function test_a_typed_opening_balance_prints_but_does_not_move_the_balance()
    {
        $this->invoice(50_000);
        $this->pay(90_000);

        $this->invoice(10_260, 'now', [
            'previous_dues_override' => true,
            'previous_dues' => 0,
        ])->assertSessionHasNoErrors();

        $second = Invoice::orderByDesc('id')->firstOrFail();

        // Printed as zero dues, so the paper totals just the goods.
        $this->assertSame(0, $second->previous_dues_override);
        $this->assertSame(10_260, $second->total_amount);

        // The account is untouched: 40,000 credit, less this invoice.
        $this->assertSame(-40_000, $second->previous_dues);
        $this->assertSame(-29_740, $this->distributor->refresh()->balance);
    }

    /**
     * Editing an invoice that was only following the account must not pin it.
     */
    public function test_editing_an_invoice_does_not_pin_its_dues()
    {
        $this->invoice(50_000);
        $this->pay(90_000);
        $this->invoice(10_260, 'now');

        $second = Invoice::orderByDesc('id')->firstOrFail();

        $this->actingAs($this->user)->put(
            route('invoices.update', [
                'current_team' => $this->team->slug,
                'invoice' => $second->id,
            ]),
            [
                'distributor_id' => $this->distributor->id,
                'sold_at' => now()->toDateString(),
                'previous_dues' => null,
                'items' => [[
                    'product_id' => $this->product->id,
                    'total_quantity' => 20_000,
                    'unit_price' => 1,
                ]],
            ],
        )->assertSessionHasNoErrors();

        $this->assertNull($second->refresh()->previous_dues_override);
        $this->assertSame(-20_000, $this->distributor->refresh()->balance);
    }
}
