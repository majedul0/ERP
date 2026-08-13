<?php

namespace Tests\Feature\Platform;

use App\Enums\BillingPeriod;
use App\Models\Plan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Companies are warned before their subscription runs out — and never locked
 * out by it.
 *
 * Losing access is a deliberate act by the platform owner (`suspended_at`).
 * A date must not do it on its own, so every test here that checks a warning
 * also checks the page still loads.
 */
class SubscriptionBannerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Team $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->company = $this->owner->currentTeam;

        $this->company->update([
            'plan_id' => Plan::create([
                'name' => 'Standard',
                'price' => 3000,
                'period' => BillingPeriod::Monthly,
                'is_active' => true,
            ])->id,
        ]);
    }

    private function dashboard()
    {
        return $this->actingAs($this->owner)
            ->get(route('dashboard', ['current_team' => $this->company->slug]));
    }

    public function test_no_warning_when_the_date_is_comfortably_ahead()
    {
        $this->company->update(['paid_through' => today()->addMonth()]);

        $this->dashboard()
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('subscription.needsAttention', false)
                ->where('subscription.status', 'active'),
            );
    }

    public function test_a_warning_appears_within_a_week_of_the_date()
    {
        $this->company->update(['paid_through' => today()->addDays(3)]);

        $this->dashboard()
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('subscription.needsAttention', true)
                ->where('subscription.isOverdue', false)
                ->where('subscription.daysRemaining', 3),
            );
    }

    public function test_an_expired_subscription_warns_but_does_not_block()
    {
        $this->company->update(['paid_through' => today()->subDays(5)]);

        // Still 200: the company keeps working until somebody suspends them.
        $this->dashboard()
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('subscription.isOverdue', true)
                ->where('subscription.daysOverdue', 5),
            );
    }

    /**
     * Most companies will never be on a plan; a permanent notice about a
     * subscription nobody sold them is noise.
     */
    public function test_a_company_with_no_plan_is_never_warned()
    {
        $this->company->update(['plan_id' => null, 'paid_through' => null]);

        $this->dashboard()
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('subscription.status', 'none')
                ->where('subscription.needsAttention', false),
            );
    }

    /**
     * Suspension is the only thing that closes a company — and it still does.
     */
    public function test_suspension_still_blocks_regardless_of_the_subscription()
    {
        $this->company->update([
            'paid_through' => today()->addYear(),
            'suspended_at' => now(),
        ]);

        $this->dashboard()->assertRedirect(route('company.suspended'));
    }
}
