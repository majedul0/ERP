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

    public function test_a_typed_opening_balance_is_used_and_kept()
    {
        $invoice = $this->create(5, ['previous_dues' => 2500]);

        $this->assertSame(2500, $invoice->previous_dues);
        $this->assertSame(2500, $invoice->previous_dues_override);
        $this->assertSame(3000, $invoice->total_amount);
        $this->assertSame(3000, $this->distributor->fresh()->balance);
    }

    /**
     * Leaving the field alone must not pin the invoice — otherwise every
     * invoice would freeze its opening figure and later corrections would stop
     * flowing through.
     */
    public function test_submitting_the_computed_figure_records_no_override()
    {
        $this->create(10);

        $second = $this->create(5, ['previous_dues' => 1000]);

        $this->assertNull($second->previous_dues_override);
        $this->assertSame(1000, $second->previous_dues);
    }

    public function test_an_override_can_be_negative_for_money_held_on_account()
    {
        $invoice = $this->create(5, ['previous_dues' => -400]);

        $this->assertSame(-400, $invoice->previous_dues);
        $this->assertSame(100, $invoice->total_amount);
    }

    /**
     * The point of keeping the ledger intact: an override moves the running
     * balance without erasing anything above it.
     */
    public function test_nothing_is_deleted_when_an_opening_balance_is_typed()
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

        $second = $this->create(5, ['previous_dues' => 5000]);

        // Everything above the override is still on file.
        $this->assertSame(3, Invoice::count() + Payment::count());
        $this->assertNotNull($first->fresh());
        $this->assertSame(1000, $first->fresh()->invoice_total);
        $this->assertSame(200, Payment::firstOrFail()->amount);

        $this->assertSame(5000, $second->previous_dues);
        $this->assertSame(5500, $second->total_amount);
    }

    /**
     * The statement must still add up top to bottom, so the gap the override
     * introduces appears as its own line rather than the balance jumping.
     */
    public function test_the_statement_shows_the_adjustment_and_still_reconciles()
    {
        $this->create(10);
        $this->create(5, ['previous_dues' => 5000]);

        $response = $this->actingAs($this->user)->get(route('distributors.show', [
            'current_team' => $this->team->slug,
            'distributor' => $this->distributor->id,
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            // Invoice 1000, adjustment +4000, invoice 500.
            ->has('statement', 3)
            ->where('statement.0.type', 'invoice')
            ->where('statement.0.balanceAfter', 1000)
            ->where('statement.1.type', 'adjustment')
            ->where('statement.1.debit', 4000)
            ->where('statement.1.balanceAfter', 5000)
            ->where('statement.2.type', 'invoice')
            ->where('statement.2.balanceAfter', 5500)
            ->where('totals.due', 5500),
        );

        // Every line's balance is the one before it plus its own movement.
        $balance = 0;

        foreach (DistributorLedger::entries($this->distributor->fresh()) as $entry) {
            $balance = $balance + $entry->debit - $entry->credit;
            $this->assertSame($balance, $entry->balanceAfter);
        }

        $this->assertSame($balance, $this->distributor->fresh()->balance);
    }

    public function test_an_override_survives_a_later_payment()
    {
        $invoice = $this->create(5, ['previous_dues' => 5000]);

        $this->actingAs($this->user)->post(
            route('payments.store', ['current_team' => $this->team->slug]),
            [
                'distributor_id' => $this->distributor->id,
                'paid_on' => now()->toDateString(),
                'amount' => 1500,
            ],
        );

        $this->assertSame(5000, $invoice->fresh()->previous_dues);
        $this->assertSame(4000, $this->distributor->fresh()->balance);
    }

    public function test_an_override_can_be_set_when_editing()
    {
        $this->create(10);
        $second = $this->create(5);

        $this->assertSame(1000, $second->previous_dues);

        $this->actingAs($this->user)->put(
            route('invoices.update', [
                'current_team' => $this->team->slug,
                'invoice' => $second->id,
            ]),
            [
                'sold_at' => now()->toDateString(),
                'distributor_id' => $this->distributor->id,
                'previous_dues' => 250,
                'items' => [[
                    'product_id' => $this->product->id,
                    'total_quantity' => 5,
                    'unit_price' => 100,
                ]],
            ],
        );

        $this->assertSame(250, $second->fresh()->previous_dues);
        $this->assertSame(250, $second->fresh()->previous_dues_override);
        $this->assertSame(750, $second->fresh()->total_amount);
    }

    public function test_editing_back_to_the_computed_figure_removes_the_override()
    {
        $this->create(10);
        $second = $this->create(5, ['previous_dues' => 250]);

        $this->assertSame(250, $second->previous_dues_override);

        $this->actingAs($this->user)->put(
            route('invoices.update', [
                'current_team' => $this->team->slug,
                'invoice' => $second->id,
            ]),
            [
                'sold_at' => now()->toDateString(),
                'distributor_id' => $this->distributor->id,
                // 1000 is what the account says on its own.
                'previous_dues' => 1000,
                'items' => [[
                    'product_id' => $this->product->id,
                    'total_quantity' => 5,
                    'unit_price' => 100,
                ]],
            ],
        );

        $this->assertNull($second->fresh()->previous_dues_override);
        $this->assertSame(1000, $second->fresh()->previous_dues);
    }

    public function test_a_fractional_opening_balance_is_refused()
    {
        $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            [
                'sold_at' => now()->toDateString(),
                'distributor_id' => $this->distributor->id,
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
