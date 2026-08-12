<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Reading the team screens must not change which company you are working in.
 *
 * The header's name and logo come from the current team, so a stray switch
 * silently rebrands the whole application — and, worse, points every
 * subsequent write at a different company.
 */
class CurrentTeamStabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $company;

    private Team $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = $this->user->currentTeam;
        $this->company->update(['name' => 'Galaxy Consumer']);

        $this->other = Team::factory()->create(['name' => 'Some Other Team']);
        $this->other->memberships()->create([
            'user_id' => $this->user->id,
            'role' => TeamRole::Owner,
        ]);
    }

    public function test_listing_teams_does_not_switch_company()
    {
        $this->actingAs($this->user)->get(route('teams.index'))->assertOk();

        $this->assertTrue($this->user->fresh()->isCurrentTeam($this->company));
    }

    public function test_opening_another_teams_settings_does_not_switch_company()
    {
        $this->actingAs($this->user)
            ->get(route('teams.edit', ['team' => $this->other->slug]))
            ->assertOk();

        $this->assertTrue($this->user->fresh()->isCurrentTeam($this->company));
    }

    /**
     * The header keeps showing the company you are actually working in.
     */
    public function test_the_brand_stays_put_while_reading_team_settings()
    {
        $this->actingAs($this->user)
            ->get(route('teams.edit', ['team' => $this->other->slug]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('companyBrand.name', 'Galaxy Consumer')
                ->where('currentTeam.name', 'Galaxy Consumer'),
            );
    }

    /**
     * Switching is a deliberate act with its own button.
     */
    public function test_the_switch_route_still_switches()
    {
        $this->actingAs($this->user)
            ->post(route('teams.switch', ['team' => $this->other->slug]))
            ->assertRedirect();

        $this->assertTrue($this->user->fresh()->isCurrentTeam($this->other));
    }
}
