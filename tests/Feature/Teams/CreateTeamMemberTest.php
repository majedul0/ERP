<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Companies set their staff up directly: an email, a password, a role, and the
 * permissions they should have. Invitations remain for somebody who already has
 * an account on the platform.
 */
class CreateTeamMemberTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->team = $this->owner->currentTeam;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function create(array $overrides = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->owner)->post(
            route('teams.members.store', ['team' => $this->team->slug]),
            [
                'name' => 'Rahim Uddin',
                'email' => 'rahim@example.com',
                'password' => 'correct-horse-battery-staple',
                'role' => TeamRole::Member->value,
                ...$overrides,
            ],
        );
    }

    public function test_an_account_is_created_and_added_to_the_company()
    {
        $this->create()->assertRedirect(route('teams.edit', ['team' => $this->team->slug]));

        $user = User::firstWhere('email', 'rahim@example.com');

        $this->assertNotNull($user);
        $this->assertSame('Rahim Uddin', $user->name);
        $this->assertTrue($user->belongsToTeam($this->team));
        $this->assertSame(TeamRole::Member, $user->teamRole($this->team));
    }

    public function test_they_can_sign_in_with_the_password_that_was_set()
    {
        $this->create();

        $this->assertTrue(Hash::check(
            'correct-horse-battery-staple',
            User::firstWhere('email', 'rahim@example.com')->password,
        ));

        $this->post(route('login.store'), [
            'email' => 'rahim@example.com',
            'password' => 'correct-horse-battery-staple',
        ])->assertRedirect();

        $this->assertAuthenticated();
    }

    /**
     * There is nobody to send a verification link to, and the dashboard sits
     * behind the `verified` middleware.
     */
    public function test_the_account_is_verified_so_they_can_reach_the_dashboard()
    {
        $this->create();

        $this->assertNotNull(User::firstWhere('email', 'rahim@example.com')->email_verified_at);
    }

    public function test_they_start_in_the_company_that_created_them()
    {
        $this->create();

        $user = User::firstWhere('email', 'rahim@example.com');

        $this->assertTrue($user->isCurrentTeam($this->team));
    }

    public function test_permissions_can_be_chosen_at_creation()
    {
        $this->create([
            'permissions' => [
                TeamPermission::ViewInvoices->value,
                TeamPermission::ViewReports->value,
            ],
        ]);

        $user = User::firstWhere('email', 'rahim@example.com');

        $this->assertTrue($user->hasTeamPermission($this->team, TeamPermission::ViewReports));
        // Not granted, even though the Member role would normally include it.
        $this->assertFalse($user->hasTeamPermission($this->team, TeamPermission::CreateInvoice));
    }

    public function test_omitting_permissions_follows_the_role()
    {
        $this->create();

        $user = User::firstWhere('email', 'rahim@example.com');

        $this->assertTrue($user->hasTeamPermission($this->team, TeamPermission::CreateInvoice));
        $this->assertFalse($user->hasTeamPermission($this->team, TeamPermission::UpdateInvoice));
    }

    public function test_a_duplicate_email_is_refused_without_creating_anything()
    {
        User::factory()->create(['email' => 'rahim@example.com']);

        $this->create()->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', 'rahim@example.com')->count());
    }

    public function test_a_weak_password_is_refused()
    {
        $this->create(['password' => 'short'])->assertSessionHasErrors('password');

        $this->assertNull(User::firstWhere('email', 'rahim@example.com'));
    }

    public function test_an_unknown_permission_is_refused()
    {
        $this->create(['permissions' => ['invoice:destroy-everything']])
            ->assertSessionHasErrors('permissions.0');

        $this->assertNull(User::firstWhere('email', 'rahim@example.com'));
    }

    public function test_nobody_can_be_created_as_the_owner()
    {
        $this->create(['role' => TeamRole::Owner->value])
            ->assertSessionHasErrors('role');
    }

    public function test_an_admin_can_add_members()
    {
        $admin = User::factory()->create();
        $this->team->memberships()->create([
            'user_id' => $admin->id,
            'role' => TeamRole::Admin,
        ]);
        $admin->switchTeam($this->team);

        $this->create(as: $admin)->assertRedirect();

        $this->assertNotNull(User::firstWhere('email', 'rahim@example.com'));
    }

    public function test_an_ordinary_member_cannot_add_members()
    {
        $member = User::factory()->create();
        $this->team->memberships()->create([
            'user_id' => $member->id,
            'role' => TeamRole::Member,
        ]);
        $member->switchTeam($this->team);

        $this->create(as: $member)->assertForbidden();

        $this->assertNull(User::firstWhere('email', 'rahim@example.com'));
    }
}
