<?php

namespace Tests\Feature\Platform;

use App\Enums\BillingPeriod;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\Team;
use App\Models\User;
use App\Support\SubscriptionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * What a company has paid the platform, and until when.
 *
 * `teams.paid_through` is derived from the payments, never incremented — the
 * same rule the sales ledger already paid for. Correcting a payment must fix
 * the date, and deleting one must give it back.
 */
class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Team $company;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->currentTeam?->forceDelete();
        $this->admin->teamMemberships()->delete();
        $this->admin->forceFill(['is_super_admin' => true, 'current_team_id' => null])->save();
        $this->admin = $this->admin->fresh();

        $owner = User::factory()->create();
        $this->company = $owner->currentTeam;

        $this->plan = Plan::create([
            'name' => 'Standard',
            'price' => 3000,
            'period' => BillingPeriod::Monthly,
            'is_active' => true,
        ]);

        $this->company->update(['plan_id' => $this->plan->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function pay(array $overrides = [])
    {
        return $this->actingAs($this->admin)->post(
            route('platform.payments.store', ['team' => $this->company->slug]),
            [
                'plan_id' => $this->plan->id,
                'amount' => 3000,
                'paid_on' => '2026-08-01',
                'method' => 'bKash',
                ...$overrides,
            ],
        );
    }

    public function test_a_payment_buys_exactly_one_period()
    {
        $this->pay()->assertRedirect(route('platform.index'));

        $this->assertSame('2026-09-01', $this->company->fresh()->paid_through->toDateString());
    }

    public function test_a_second_payment_stacks_from_the_date_already_paid_for()
    {
        $this->pay();

        // Paid a fortnight early — the month must be added to what they have,
        // not started again from today.
        $this->pay(['paid_on' => '2026-08-20']);

        $this->assertSame('2026-10-01', $this->company->fresh()->paid_through->toDateString());
    }

    /**
     * Paying two months late must not hand back the months they went without.
     */
    public function test_paying_late_starts_from_the_payment_not_the_lapsed_date()
    {
        $this->pay(['paid_on' => '2026-01-01']);
        $this->assertSame('2026-02-01', $this->company->fresh()->paid_through->toDateString());

        $this->pay(['paid_on' => '2026-06-01']);

        $this->assertSame('2026-07-01', $this->company->fresh()->paid_through->toDateString());
    }

    public function test_correcting_a_payment_replays_the_date()
    {
        $this->pay();
        $payment = SubscriptionPayment::firstOrFail();

        $this->actingAs($this->admin)->put(
            route('platform.payments.update', ['payment' => $payment->id]),
            [
                'plan_id' => $this->plan->id,
                'amount' => 3000,
                'paid_on' => '2026-07-01',
            ],
        )->assertRedirect();

        // Corrected to a month earlier, and the date follows — it is not
        // stacked on top of the coverage the original payment granted.
        $this->assertSame('2026-08-01', $this->company->fresh()->paid_through->toDateString());
        $this->assertSame(1, SubscriptionPayment::count());
    }

    public function test_deleting_a_payment_takes_the_period_back()
    {
        $this->pay();
        $payment = SubscriptionPayment::firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('platform.payments.destroy', ['payment' => $payment->id]))
            ->assertRedirect();

        $this->assertSoftDeleted($payment);
        $this->assertNull($this->company->fresh()->paid_through);
    }

    public function test_a_yearly_plan_buys_a_year()
    {
        $yearly = Plan::create([
            'name' => 'Annual',
            'price' => 30000,
            'period' => BillingPeriod::Yearly,
            'is_active' => true,
        ]);

        $this->pay(['plan_id' => $yearly->id, 'amount' => 30000]);

        $this->assertSame('2027-08-01', $this->company->fresh()->paid_through->toDateString());
    }

    public function test_a_fractional_amount_is_refused_rather_than_rounded()
    {
        $this->pay(['amount' => 3000.5])->assertSessionHasErrors('amount');

        $this->assertSame(0, SubscriptionPayment::count());
        $this->assertNull($this->company->fresh()->paid_through);
    }

    /**
     * The last day somebody bought is a day they get.
     */
    public function test_paid_through_today_still_counts_as_active()
    {
        $this->company->update(['paid_through' => today()]);

        $status = SubscriptionStatus::for($this->company->fresh());

        $this->assertSame('active', $status['status']);
        $this->assertFalse($status['isOverdue']);
        $this->assertSame(0, $status['daysRemaining']);
    }

    public function test_yesterday_is_overdue_by_one_day()
    {
        $this->company->update(['paid_through' => today()->subDay()]);

        $status = SubscriptionStatus::for($this->company->fresh());

        $this->assertSame('overdue', $status['status']);
        $this->assertTrue($status['isOverdue']);
        $this->assertSame(1, $status['daysOverdue']);
    }

    public function test_a_company_with_no_plan_is_simply_not_subscribed()
    {
        $this->company->update(['plan_id' => null, 'paid_through' => null]);

        $status = SubscriptionStatus::for($this->company->fresh());

        $this->assertSame('none', $status['status']);
        $this->assertFalse($status['needsAttention']);
    }

    public function test_the_panel_shows_the_plan_and_the_date()
    {
        $this->pay();

        $this->actingAs($this->admin)
            ->get(route('platform.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('companies.0.plan.name', 'Standard')
                ->where('companies.0.subscription.paidThrough', '2026-09-01')
                ->where('totals.collectedThisMonth', 3000)
                ->where('totals.monthlyValue', 3000),
            );
    }

    public function test_a_plan_can_be_assigned_without_taking_money()
    {
        $other = Plan::create([
            'name' => 'Basic',
            'price' => 1000,
            'period' => BillingPeriod::Monthly,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)->patch(
            route('platform.companies.plan', ['team' => $this->company->slug]),
            ['plan_id' => $other->id],
        )->assertRedirect();

        $this->assertSame($other->id, $this->company->fresh()->plan_id);
        $this->assertNull($this->company->fresh()->paid_through);
    }

    public function test_a_company_user_cannot_touch_any_of_it()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('platform.payments.store', ['team' => $this->company->slug]), [
                'amount' => 1,
                'paid_on' => '2026-08-01',
            ])
            ->assertNotFound();

        $this->actingAs($user)->get(route('platform.plans.index'))->assertNotFound();

        $this->assertSame(0, SubscriptionPayment::count());
    }
}
