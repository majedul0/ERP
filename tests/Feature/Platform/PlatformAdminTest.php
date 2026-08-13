<?php

namespace Tests\Feature\Platform;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The platform panel: opening companies, closing them, and seeing what they
 * use.
 *
 * The system is sold to companies, so a company creating more companies for
 * itself would make the thing being sold meaningless.
 */
class PlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A platform admin belongs to no company — they run the system rather than
     * use it, which is what `app:create-super-admin` produces. The factory
     * hands out a personal team, so it is removed here.
     */
    private function superAdmin(): User
    {
        $user = User::factory()->create(['email' => 'majedul@example.com']);

        $user->currentTeam?->forceDelete();
        $user->teamMemberships()->delete();

        $user->forceFill(['is_super_admin' => true, 'current_team_id' => null])->save();

        return $user->fresh();
    }

    public function test_the_login_page_is_reachable_without_signing_in()
    {
        $this->get('/majedul')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('platform/login'));
    }

    public function test_a_platform_admin_can_sign_in()
    {
        $admin = $this->superAdmin();
        $admin->forceFill(['password' => Hash::make('a-long-enough-password')])->save();

        $this->post('/majedul', [
            'email' => $admin->email,
            'password' => 'a-long-enough-password',
        ])->assertRedirect(route('platform.index'));

        $this->assertAuthenticatedAs($admin);
    }

    /**
     * An ordinary company user must not be able to sign in here, and must not
     * learn that this address means anything.
     */
    public function test_a_company_user_cannot_sign_in_to_the_platform()
    {
        $user = User::factory()->create();
        $user->forceFill(['password' => Hash::make('a-long-enough-password')])->save();

        $this->post('/majedul', [
            'email' => $user->email,
            'password' => 'a-long-enough-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_signed_in_company_user_gets_nothing_from_the_panel()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('platform.index'))
            ->assertNotFound();
    }

    public function test_a_visitor_is_sent_to_the_platform_login()
    {
        $this->get(route('platform.index'))->assertRedirect(route('platform.login'));
    }

    public function test_companies_are_listed_with_what_they_use()
    {
        $owner = User::factory()->create();
        $owner->currentTeam->update(['name' => 'Galaxy Consumer']);

        $this->actingAs($this->superAdmin())
            ->get(route('platform.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/index')
                ->where('companies.0.name', 'Galaxy Consumer')
                ->where('companies.0.counts.members', 1)
                ->where('companies.0.isSuspended', false)
                ->where('totals.companies', 1),
            );
    }

    public function test_a_company_and_its_owner_are_created_together()
    {
        $this->actingAs($this->superAdmin())->post(route('platform.companies.store'), [
            'company' => 'Padma Traders',
            'owner_name' => 'Karim',
            'owner_email' => 'karim@example.com',
            'owner_password' => 'a-long-enough-password',
        ])->assertRedirect(route('platform.index'));

        $team = Team::firstWhere('name', 'Padma Traders');
        $owner = User::firstWhere('email', 'karim@example.com');

        $this->assertNotNull($team);
        $this->assertNotNull($owner);
        $this->assertTrue($owner->ownsTeam($team));
        $this->assertTrue($owner->isCurrentTeam($team));

        // They can sign in immediately — a company nobody can reach is no sale.
        $this->post(route('login.store'), [
            'email' => 'karim@example.com',
            'password' => 'a-long-enough-password',
        ])->assertRedirect();
    }

    public function test_a_company_cannot_create_a_company_of_its_own()
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->post(route('teams.store'), ['name' => 'A Second Company'])
            ->assertForbidden();

        $this->assertSame(1, Team::count());
    }

    public function test_suspending_closes_the_company_to_everyone_in_it()
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;

        $this->actingAs($this->superAdmin())->patch(
            route('platform.companies.suspend', ['team' => $team->slug]),
            ['suspend' => true],
        )->assertRedirect();

        $this->assertNotNull($team->fresh()->suspended_at);

        // Closed, but to a page that explains why rather than a bare 403.
        $this->actingAs($owner)
            ->get(route('dashboard', ['current_team' => $team->slug]))
            ->assertRedirect(route('company.suspended'));
    }

    public function test_reinstating_gives_the_company_back_untouched()
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;
        $team->update(['suspended_at' => now()]);

        $this->actingAs($this->superAdmin())->patch(
            route('platform.companies.suspend', ['team' => $team->slug]),
            ['suspend' => false],
        )->assertRedirect();

        $this->assertNull($team->fresh()->suspended_at);

        $this->actingAs($owner)
            ->get(route('dashboard', ['current_team' => $team->slug]))
            ->assertOk();
    }

    public function test_only_a_platform_admin_can_suspend()
    {
        $owner = User::factory()->create();
        $team = $owner->currentTeam;

        $this->actingAs($owner)->patch(
            route('platform.companies.suspend', ['team' => $team->slug]),
            ['suspend' => true],
        )->assertNotFound();

        $this->assertNull($team->fresh()->suspended_at);
    }

    public function test_the_password_can_be_changed_from_the_dashboard()
    {
        $admin = $this->superAdmin();
        $admin->forceFill(['password' => Hash::make('the-old-password')])->save();

        $this->actingAs($admin->fresh())->patch(route('platform.password.update'), [
            'current_password' => 'the-old-password',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertRedirect(route('platform.index'));

        $this->assertTrue(Hash::check('a-brand-new-password', $admin->fresh()->password));
    }

    public function test_changing_the_password_requires_the_current_one()
    {
        $admin = $this->superAdmin();
        $admin->forceFill(['password' => Hash::make('the-old-password')])->save();

        $this->actingAs($admin->fresh())->patch(route('platform.password.update'), [
            'current_password' => 'not-the-old-password',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('the-old-password', $admin->fresh()->password));
    }

    public function test_a_company_user_cannot_change_the_platform_password()
    {
        $this->actingAs(User::factory()->create())
            ->patch(route('platform.password.update'), [
                'current_password' => 'whatever',
                'password' => 'a-brand-new-password',
                'password_confirmation' => 'a-brand-new-password',
            ])
            ->assertNotFound();
    }

    public function test_the_create_super_admin_command_promotes_rather_than_duplicates()
    {
        $this->artisan('app:create-super-admin', [
            '--email' => 'majedul@example.com',
            '--password' => 'a-long-enough-password',
        ])->assertSuccessful();

        $this->artisan('app:create-super-admin', [
            '--email' => 'majedul@example.com',
            '--password' => 'another-long-password',
        ])->assertSuccessful();

        $this->assertSame(1, User::where('email', 'majedul@example.com')->count());
        $this->assertTrue(User::firstWhere('email', 'majedul@example.com')->is_super_admin);
    }
}
