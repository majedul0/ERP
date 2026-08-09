<?php

namespace Tests\Feature\Payments;

use App\Enums\DeliveryStatus;
use App\Models\Bank;
use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaymentTest extends TestCase
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

    private function invoice(int $quantity, ?string $soldAt = null): Invoice
    {
        $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            [
                'sold_at' => $soldAt ?? now()->toDateString(),
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

    private function pay(int $amount, ?string $paidOn = null, array $overrides = [])
    {
        return $this->actingAs($this->user)->post(
            route('payments.store', ['current_team' => $this->team->slug]),
            [
                'distributor_id' => $this->distributor->id,
                'paid_on' => $paidOn ?? now()->toDateString(),
                'amount' => $amount,
                ...$overrides,
            ],
        );
    }

    public function test_a_payment_reduces_what_the_distributor_owes()
    {
        $this->invoice(quantity: 10);

        $this->assertSame(1000, $this->distributor->fresh()->balance);

        $response = $this->pay(400);

        $response->assertRedirect(route('distributors.show', [
            'current_team' => $this->team->slug,
            'distributor' => $this->distributor->id,
        ]));

        $this->assertSame(600, $this->distributor->fresh()->balance);
        $this->assertSame(400, Payment::firstOrFail()->amount);
    }

    public function test_paying_in_full_clears_the_account()
    {
        $this->invoice(quantity: 10);

        $this->pay(1000);

        $this->assertSame(0, $this->distributor->fresh()->balance);
    }

    public function test_overpaying_leaves_a_credit()
    {
        $this->invoice(quantity: 10);

        $this->pay(1500);

        // Negative is money the company holds on their behalf, which the next
        // invoice carries forward as a reduced Previous Dues.
        $this->assertSame(-500, $this->distributor->fresh()->balance);
    }

    /**
     * The whole point of the running account: an invoice raised after a
     * payment shows the reduced figure as its Previous Dues.
     */
    public function test_a_later_invoice_carries_forward_the_reduced_dues()
    {
        $this->invoice(quantity: 10, soldAt: now()->subDays(2)->toDateString());
        $this->pay(600, paidOn: now()->subDay()->toDateString());

        $second = $this->invoice(quantity: 5);

        $this->assertSame(400, $second->previous_dues);
        $this->assertSame(900, $second->total_amount);
        $this->assertSame(900, $this->distributor->fresh()->balance);
    }

    /**
     * A payment recorded before an existing invoice's date has to rewrite that
     * invoice's carried-forward figure — the same replay an edit triggers.
     */
    public function test_a_backdated_payment_rewrites_later_invoices()
    {
        $first = $this->invoice(quantity: 10, soldAt: now()->subDays(3)->toDateString());
        $second = $this->invoice(quantity: 5, soldAt: now()->toDateString());

        $this->assertSame(1000, $second->previous_dues);

        $this->pay(700, paidOn: now()->subDays(2)->toDateString());

        $this->assertSame(1000, $first->fresh()->total_amount);
        $this->assertSame(300, $second->fresh()->previous_dues);
        $this->assertSame(800, $second->fresh()->total_amount);
        $this->assertSame(800, $this->distributor->fresh()->balance);
    }

    public function test_a_payment_can_name_the_bank_it_landed_in()
    {
        $bank = Bank::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Dutch Bangla Bank Limited',
        ]);

        $this->invoice(quantity: 10);
        $this->pay(500, overrides: ['bank_id' => $bank->id, 'comment' => 'Cheque']);

        $payment = Payment::firstOrFail();

        $this->assertSame($bank->id, $payment->bank_id);
        $this->assertSame('Cheque', $payment->comment);
    }

    public function test_a_bank_from_another_company_is_rejected()
    {
        $this->invoice(quantity: 10);

        $this->pay(500, overrides: ['bank_id' => Bank::factory()->create()->id])
            ->assertSessionHasErrors('bank_id');

        $this->assertSame(0, Payment::count());
    }

    public function test_a_distributor_from_another_company_is_rejected()
    {
        $this->actingAs($this->user)->post(
            route('payments.store', ['current_team' => $this->team->slug]),
            [
                'distributor_id' => Distributor::factory()->create()->id,
                'paid_on' => now()->toDateString(),
                'amount' => 100,
            ],
        )->assertSessionHasErrors('distributor_id');

        $this->assertSame(0, Payment::count());
    }

    public function test_fractional_and_zero_amounts_are_refused()
    {
        $this->pay(0)->assertSessionHasErrors('amount');

        $this->actingAs($this->user)->post(
            route('payments.store', ['current_team' => $this->team->slug]),
            [
                'distributor_id' => $this->distributor->id,
                'paid_on' => now()->toDateString(),
                'amount' => 99.5,
            ],
        )->assertSessionHasErrors('amount');

        $this->assertSame(0, Payment::count());
    }

    public function test_the_statement_reconciles_to_the_due_amount()
    {
        $this->invoice(quantity: 10, soldAt: now()->subDays(2)->toDateString());
        $this->pay(300, paidOn: now()->subDay()->toDateString());
        $this->invoice(quantity: 4);

        $response = $this->actingAs($this->user)->get(route('distributors.show', [
            'current_team' => $this->team->slug,
            'distributor' => $this->distributor->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('company/distributors/show')
            ->has('statement', 3)
            // Invoice 1000, payment 300, invoice 400.
            ->where('statement.0.debit', 1000)
            ->where('statement.0.balanceAfter', 1000)
            ->where('statement.1.credit', 300)
            ->where('statement.1.balanceAfter', 700)
            ->where('statement.2.debit', 400)
            ->where('statement.2.balanceAfter', 1100)
            ->where('totals.charged', 1400)
            ->where('totals.paid', 300)
            ->where('totals.due', 1100),
        );

        $this->assertSame(1100, $this->distributor->fresh()->balance);
    }

    /**
     * On a shared day the charge is listed before the settlement, so a payment
     * made the day an invoice is raised reads as settling it.
     */
    public function test_a_same_day_payment_is_listed_after_the_invoice()
    {
        $this->invoice(quantity: 10);
        $this->pay(1000);

        $this->actingAs($this->user)
            ->get(route('distributors.show', [
                'current_team' => $this->team->slug,
                'distributor' => $this->distributor->id,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('statement.0.type', 'invoice')
                ->where('statement.1.type', 'payment')
                ->where('statement.1.balanceAfter', 0),
            );
    }

    public function test_a_cancelled_invoice_stays_on_the_statement_charging_nothing()
    {
        $invoice = $this->invoice(quantity: 10);

        $this->actingAs($this->user)->patch(
            route('invoices.status.update', [
                'current_team' => $this->team->slug,
                'invoice' => $invoice->id,
            ]),
            ['delivery_status' => DeliveryStatus::Cancelled->value],
        );

        $this->actingAs($this->user)
            ->get(route('distributors.show', [
                'current_team' => $this->team->slug,
                'distributor' => $this->distributor->id,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('statement', 1)
                ->where('statement.0.debit', 0)
                ->where('statement.0.balanceAfter', 0)
                ->where('totals.due', 0),
            );
    }

    public function test_the_invoice_notes_the_payment_that_settled_it()
    {
        $bank = Bank::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Dutch Bangla Bank Limited',
        ]);

        $invoice = $this->invoice(quantity: 10);
        $this->pay(1000, overrides: ['bank_id' => $bank->id]);

        $this->actingAs($this->user)
            ->get(route('invoices.show', [
                'current_team' => $this->team->slug,
                'invoice' => $invoice->id,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('invoice.payments', 1)
                ->where('invoice.payments.0.amount', 1000)
                ->where('invoice.payments.0.bankName', 'Dutch Bangla Bank Limited'),
            );
    }

    public function test_a_payment_after_the_next_invoice_is_not_noted_on_the_earlier_one()
    {
        $first = $this->invoice(quantity: 10, soldAt: now()->subDays(3)->toDateString());
        $this->invoice(quantity: 5, soldAt: now()->subDay()->toDateString());

        // Paid today — after the second invoice, so it settles that one.
        $this->pay(500);

        $this->actingAs($this->user)
            ->get(route('invoices.show', [
                'current_team' => $this->team->slug,
                'invoice' => $first->id,
            ]))
            ->assertInertia(fn (Assert $page) => $page->has('invoice.payments', 0));
    }

    public function test_another_companys_distributor_account_is_not_reachable()
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('distributors.show', [
            'current_team' => $outsider->currentTeam->slug,
            'distributor' => $this->distributor->id,
        ]))->assertNotFound();
    }
}
