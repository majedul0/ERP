<?php

namespace Tests\Feature\Vendors;

use App\Models\Bank;
use App\Models\Team;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorPayment;
use App\Support\VendorLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A vendor's account is the mirror of a distributor's: bills are what they
 * charge, payments are what was sent, and the balance is what is still owed.
 *
 * A plain running total, with no override and no adjustment — the property that
 * keeps a statement reconcilable, learned the hard way on the sales side.
 */
class VendorAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = $this->user->currentTeam;
        $this->vendor = Vendor::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Padma Traders',
        ]);
    }

    private function bill(int $amount, string $billedOn = '-3 days'): VendorBill
    {
        $this->travel(1)->minutes();

        $this->actingAs($this->user)->post(
            route('bills.store', ['current_team' => $this->team->slug]),
            [
                'vendor_id' => $this->vendor->id,
                'billed_on' => now()->modify($billedOn)->toDateString(),
                'amount' => $amount,
                'reference' => 'BILL-1',
            ],
        )->assertSessionHasNoErrors();

        return VendorBill::latest('id')->firstOrFail();
    }

    private function pay(int $amount, string $paidOn = '-1 day'): VendorPayment
    {
        $this->travel(1)->minutes();

        $this->actingAs($this->user)->post(
            route('vendor-payments.store', ['current_team' => $this->team->slug]),
            [
                'vendor_id' => $this->vendor->id,
                'bank_id' => Bank::factory()->create(['team_id' => $this->team->id])->id,
                'paid_on' => now()->modify($paidOn)->toDateString(),
                'amount' => $amount,
            ],
        )->assertSessionHasNoErrors();

        return VendorPayment::latest('id')->firstOrFail();
    }

    public function test_a_bill_increases_what_is_owed()
    {
        $this->bill(50_000);

        $this->assertSame(50_000, $this->vendor->refresh()->balance);
    }

    public function test_a_payment_reduces_what_is_owed()
    {
        $this->bill(50_000);
        $this->pay(20_000);

        $this->assertSame(30_000, $this->vendor->refresh()->balance);
    }

    public function test_paying_more_than_owed_leaves_the_vendor_holding_an_advance()
    {
        $this->bill(50_000);
        $this->pay(80_000);

        $this->assertSame(-30_000, $this->vendor->refresh()->balance);
    }

    public function test_the_statement_is_a_plain_running_total()
    {
        $this->bill(50_000);
        $this->pay(20_000);
        $this->bill(10_000, 'now');

        $entries = VendorLedger::entries($this->vendor->refresh());

        $this->assertSame(
            ['bill', 'payment', 'bill'],
            array_map(fn ($entry) => $entry->type, $entries),
        );
        $this->assertSame(50_000, $entries[0]->balanceAfter);
        $this->assertSame(30_000, $entries[1]->balanceAfter);
        $this->assertSame(40_000, $entries[2]->balanceAfter);

        $this->assertSame(40_000, $this->vendor->refresh()->balance);
    }

    /**
     * An advance paid before the bill it covers must sort first, the same fix
     * the distributor ledger needed.
     */
    public function test_an_advance_paid_first_sorts_before_the_bill()
    {
        $this->pay(80_000, 'now');
        $this->bill(50_000, 'now');

        $entries = VendorLedger::entries($this->vendor->refresh());

        $this->assertSame(
            ['payment', 'bill'],
            array_map(fn ($entry) => $entry->type, $entries),
        );
        $this->assertSame(-30_000, $this->vendor->refresh()->balance);
    }

    public function test_correcting_a_bill_replays_the_account()
    {
        $bill = $this->bill(50_000);
        $this->pay(20_000);

        $this->actingAs($this->user)->put(
            route('bills.update', [
                'current_team' => $this->team->slug,
                'bill' => $bill->id,
            ]),
            [
                'vendor_id' => $this->vendor->id,
                'billed_on' => $bill->billed_on->toDateString(),
                'amount' => 60_000,
            ],
        )->assertSessionHasNoErrors();

        $this->assertSame(40_000, $this->vendor->refresh()->balance);
    }

    public function test_moving_a_bill_to_another_vendor_replays_both_accounts()
    {
        $bill = $this->bill(50_000);
        $other = Vendor::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->user)->put(
            route('bills.update', [
                'current_team' => $this->team->slug,
                'bill' => $bill->id,
            ]),
            [
                'vendor_id' => $other->id,
                'billed_on' => $bill->billed_on->toDateString(),
                'amount' => 50_000,
            ],
        )->assertSessionHasNoErrors();

        $this->assertSame(0, $this->vendor->refresh()->balance);
        $this->assertSame(50_000, $other->refresh()->balance);
    }

    public function test_deleting_a_bill_puts_the_account_back()
    {
        $bill = $this->bill(50_000);

        $this->actingAs($this->user)->delete(route('bills.destroy', [
            'current_team' => $this->team->slug,
            'bill' => $bill->id,
        ]))->assertRedirect();

        $this->assertSoftDeleted($bill);
        $this->assertSame(0, $this->vendor->refresh()->balance);
    }

    public function test_deleting_a_payment_means_the_vendor_is_owed_it_again()
    {
        $this->bill(50_000);
        $payment = $this->pay(20_000);

        $this->actingAs($this->user)->delete(route('vendor-payments.destroy', [
            'current_team' => $this->team->slug,
            'payment' => $payment->id,
        ]))->assertRedirect();

        $this->assertSame(50_000, $this->vendor->refresh()->balance);
    }

    public function test_a_fractional_amount_is_rejected()
    {
        $this->actingAs($this->user)->post(
            route('bills.store', ['current_team' => $this->team->slug]),
            [
                'vendor_id' => $this->vendor->id,
                'billed_on' => now()->toDateString(),
                'amount' => 500.5,
            ],
        )->assertSessionHasErrors('amount');

        $this->assertSame(0, VendorBill::count());
    }

    public function test_another_companys_vendor_cannot_be_billed()
    {
        $theirs = Vendor::factory()->create();

        $this->actingAs($this->user)->post(
            route('bills.store', ['current_team' => $this->team->slug]),
            [
                'vendor_id' => $theirs->id,
                'billed_on' => now()->toDateString(),
                'amount' => 500,
            ],
        )->assertSessionHasErrors('vendor_id');

        $this->assertSame(0, VendorBill::count());
    }

    public function test_the_vendor_screen_shows_the_account()
    {
        $this->bill(50_000);
        $this->pay(20_000);

        $this->actingAs($this->user)
            ->get(route('vendors.show', [
                'current_team' => $this->team->slug,
                'vendor' => $this->vendor->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('company/vendors/show')
                ->where('vendor.name', 'Padma Traders')
                ->has('statement', 2)
                ->where('totals.charged', 50_000)
                ->where('totals.paid', 20_000)
                ->where('totals.due', 30_000),
            );
    }

    public function test_the_list_shows_only_the_current_companys_vendors()
    {
        Vendor::factory()->create(['name' => 'Theirs']);

        $this->actingAs($this->user)
            ->get(route('vendors.index', ['current_team' => $this->team->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('vendors', 1)
                ->where('vendors.0.name', 'Padma Traders'),
            );
    }

    public function test_a_vendor_with_history_cannot_be_deleted()
    {
        $this->bill(50_000);

        $this->actingAs($this->user)->delete(route('vendors.destroy', [
            'current_team' => $this->team->slug,
            'vendor' => $this->vendor->id,
        ]))->assertSessionHasErrors('vendor');

        $this->assertNotSoftDeleted($this->vendor);
    }

    public function test_a_vendor_with_no_history_can_be_deleted()
    {
        $this->actingAs($this->user)->delete(route('vendors.destroy', [
            'current_team' => $this->team->slug,
            'vendor' => $this->vendor->id,
        ]))->assertRedirect();

        $this->assertSoftDeleted($this->vendor);
    }

    public function test_guests_are_turned_away()
    {
        $this->get(route('vendors.index', ['current_team' => $this->team->slug]))
            ->assertRedirect(route('login'));
    }
}
