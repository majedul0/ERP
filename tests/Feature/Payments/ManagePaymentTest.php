<?php

namespace Tests\Feature\Payments;

use App\Models\Bank;
use App\Models\Distributor;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManagePaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    private Distributor $distributor;

    private Bank $bank;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = $this->user->currentTeam;
        $this->distributor = Distributor::factory()->create(['team_id' => $this->team->id]);
        $this->bank = Bank::factory()->create(['team_id' => $this->team->id]);
        $this->product = Product::factory()->create([
            'team_id' => $this->team->id,
            'distributor_price' => 100,
            'stock_quantity' => 500,
        ]);
    }

    private function sell(int $quantity = 10): void
    {
        $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            [
                'distributor_id' => $this->distributor->id,
                'sold_at' => now()->subDays(3)->toDateString(),
                'items' => [[
                    'product_id' => $this->product->id,
                    'total_quantity' => $quantity,
                    'unit_price' => 100,
                ]],
            ],
        )->assertSessionHasNoErrors();
    }

    private function pay(int $amount = 400): Payment
    {
        $this->actingAs($this->user)->post(
            route('payments.store', ['current_team' => $this->team->slug]),
            [
                'distributor_id' => $this->distributor->id,
                'bank_id' => $this->bank->id,
                'amount' => $amount,
                'paid_on' => now()->subDays(2)->toDateString(),
            ],
        )->assertSessionHasNoErrors();

        return Payment::orderByDesc('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function updatePayment(Payment $payment, array $overrides = [])
    {
        return $this->actingAs($this->user)->put(
            route('payments.update', [
                'current_team' => $this->team->slug,
                'payment' => $payment->id,
            ]),
            [
                'distributor_id' => $this->distributor->id,
                'bank_id' => $this->bank->id,
                'amount' => $payment->amount,
                'paid_on' => $payment->paid_on->toDateString(),
                ...$overrides,
            ],
        );
    }

    public function test_the_edit_screen_loads_the_payment()
    {
        $this->sell();
        $payment = $this->pay(400);

        $this->actingAs($this->user)
            ->get(route('payments.edit', [
                'current_team' => $this->team->slug,
                'payment' => $payment->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('company/payments/edit')
                ->where('payment.amount', 400)
                ->has('distributors', 1)
                ->has('banks', 1),
            );
    }

    public function test_correcting_the_amount_replays_the_account()
    {
        $this->sell(10);          // 1000 charged
        $payment = $this->pay(400); // 600 due

        $this->assertSame(600, $this->distributor->refresh()->balance);

        $this->updatePayment($payment, ['amount' => 900])
            ->assertSessionHasNoErrors();

        $this->assertSame(900, $payment->refresh()->amount);
        $this->assertSame(100, $this->distributor->refresh()->balance);
    }

    public function test_a_fractional_amount_is_rejected()
    {
        $this->sell();
        $payment = $this->pay(400);

        $this->updatePayment($payment, ['amount' => 400.5])
            ->assertSessionHasErrors('amount');

        $this->assertSame(400, $payment->refresh()->amount);
    }

    public function test_moving_a_payment_to_another_distributor_replays_both_accounts()
    {
        $this->sell(10);
        $payment = $this->pay(400);

        $other = Distributor::factory()->create(['team_id' => $this->team->id]);

        $this->updatePayment($payment, ['distributor_id' => $other->id])
            ->assertSessionHasNoErrors();

        // The money leaves the first account and lands on the second.
        $this->assertSame(1000, $this->distributor->refresh()->balance);
        $this->assertSame(-400, $other->refresh()->balance);
    }

    public function test_a_payment_cannot_be_moved_to_another_companys_distributor()
    {
        $this->sell();
        $payment = $this->pay(400);

        $theirs = Distributor::factory()->create();

        $this->updatePayment($payment, ['distributor_id' => $theirs->id])
            ->assertSessionHasErrors('distributor_id');

        $this->assertSame($this->distributor->id, $payment->refresh()->distributor_id);
    }

    public function test_deleting_a_payment_puts_the_debt_back()
    {
        $this->sell(10);
        $payment = $this->pay(400);

        $this->assertSame(600, $this->distributor->refresh()->balance);

        $this->actingAs($this->user)->delete(route('payments.destroy', [
            'current_team' => $this->team->slug,
            'payment' => $payment->id,
        ]))->assertRedirect(route('distributors.show', [
            'current_team' => $this->team->slug,
            'distributor' => $this->distributor->id,
        ]));

        $this->assertSoftDeleted($payment);
        $this->assertSame(1000, $this->distributor->refresh()->balance);
    }

    public function test_a_deleted_payment_leaves_the_statement()
    {
        $this->sell();
        $payment = $this->pay(400);

        $this->actingAs($this->user)->delete(route('payments.destroy', [
            'current_team' => $this->team->slug,
            'payment' => $payment->id,
        ]));

        $this->actingAs($this->user)
            ->get(route('distributors.show', [
                'current_team' => $this->team->slug,
                'distributor' => $this->distributor->id,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('statement', 1)
                ->where('statement.0.type', 'invoice'),
            );
    }

    public function test_another_companys_payment_cannot_be_touched()
    {
        $other = User::factory()->create();
        $theirs = Payment::create([
            'team_id' => $other->currentTeam->id,
            'distributor_id' => Distributor::factory()->create([
                'team_id' => $other->currentTeam->id,
            ])->id,
            'created_by' => $other->id,
            'amount' => 500,
            'paid_on' => now()->toDateString(),
        ]);

        $this->actingAs($this->user)->get(route('payments.edit', [
            'current_team' => $this->team->slug,
            'payment' => $theirs->id,
        ]))->assertNotFound();

        $this->actingAs($this->user)->delete(route('payments.destroy', [
            'current_team' => $this->team->slug,
            'payment' => $theirs->id,
        ]))->assertNotFound();

        $this->assertNotSoftDeleted($theirs);
    }

    public function test_guests_cannot_edit_or_delete_payments()
    {
        $this->sell();
        $payment = $this->pay(400);

        $this->post('/logout');

        $this->delete(route('payments.destroy', [
            'current_team' => $this->team->slug,
            'payment' => $payment->id,
        ]))->assertRedirect(route('login'));

        $this->assertNotSoftDeleted($payment);
    }
}
