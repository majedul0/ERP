<?php

namespace Tests\Feature\Payments;

use App\Enums\TeamRole;
use App\Models\Distributor;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Recording money in from the dashboard, where no distributor has been chosen
 * yet.
 *
 * The same `store` as the account-scoped form, so what a payment is has one
 * definition — only the way in differs.
 */
class RecordPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    private Distributor $distributor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = $this->user->currentTeam;
        $this->distributor = Distributor::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Majedul Islam',
        ]);
    }

    public function test_the_form_offers_every_distributor_with_their_balance()
    {
        $this->distributor->update(['balance' => 40_000]);

        $this->actingAs($this->user)
            ->get(route('payments.record', ['current_team' => $this->team->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('company/payments/record')
                ->has('distributors', 1)
                ->where('distributors.0.name', 'Majedul Islam')
                ->where('distributors.0.balance', 40_000),
            );
    }

    public function test_it_only_offers_the_current_companys_distributors()
    {
        Distributor::factory()->create(['name' => 'Theirs']);

        $this->actingAs($this->user)
            ->get(route('payments.record', ['current_team' => $this->team->slug]))
            ->assertInertia(fn (Assert $page) => $page->has('distributors', 1));
    }

    public function test_a_payment_recorded_this_way_lands_on_the_account()
    {
        $this->actingAs($this->user)->post(
            route('payments.store', ['current_team' => $this->team->slug]),
            [
                'distributor_id' => $this->distributor->id,
                'amount' => 5_000,
                'paid_on' => now()->toDateString(),
            ],
        )->assertRedirect();

        $this->assertSame(1, Payment::count());
        $this->assertSame(-5_000, $this->distributor->fresh()->balance);
    }

    /**
     * The screen exists to record money; somebody who may not do that must not
     * reach it.
     */
    public function test_a_member_without_payment_permission_is_refused()
    {
        $member = User::factory()->create();
        $this->team->memberships()->create([
            'user_id' => $member->id,
            'role' => TeamRole::Member,
        ]);
        $member->switchTeam($this->team);

        $this->actingAs($member)
            ->get(route('payments.record', ['current_team' => $this->team->slug]))
            ->assertForbidden();
    }

    public function test_guests_are_sent_to_the_login()
    {
        $this->get(route('payments.record', ['current_team' => $this->team->slug]))
            ->assertRedirect(route('login'));
    }
}
