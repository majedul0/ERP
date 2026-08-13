<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Signing in must land somewhere, for every shape of account.
 *
 * The company login builds a team-prefixed URL, so an account with no company —
 * the platform administrator, or a member who was removed — has to be handled
 * rather than blowing up while generating the redirect.
 */
class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $email): TestResponse
    {
        return $this->post(route('login.store'), [
            'email' => $email,
            'password' => 'a-long-enough-password',
        ]);
    }

    private function account(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->forceFill(['password' => Hash::make('a-long-enough-password')])->save();

        return $user->fresh();
    }

    public function test_a_company_owner_lands_in_their_company()
    {
        $user = $this->account();

        $this->signIn($user->email)
            ->assertRedirect("/{$user->currentTeam->slug}/dashboard");
    }

    /**
     * The platform administrator belongs to no company. Signing in at the
     * company login must not 500 while trying to build a team URL.
     */
    public function test_a_platform_admin_signing_in_at_the_company_login()
    {
        $user = $this->account();
        $user->currentTeam?->forceDelete();
        $user->teamMemberships()->delete();
        $user->forceFill(['is_super_admin' => true, 'current_team_id' => null])->save();

        $response = $this->signIn($user->email);

        $this->assertNotSame(500, $response->getStatusCode());
    }

    /**
     * The crash this test was written for: already signed in at the platform
     * panel, then opening the company login. The `guest` middleware redirects
     * an authenticated visitor to `route('dashboard')`, which lives under
     * `{current_team}` — and a platform admin has no company to fill it with.
     */
    public function test_a_signed_in_platform_admin_opening_the_company_login()
    {
        $user = $this->account();
        $user->currentTeam?->forceDelete();
        $user->teamMemberships()->delete();
        $user->forceFill(['is_super_admin' => true, 'current_team_id' => null])->save();

        $this->actingAs($user->fresh())
            ->get(route('login'))
            ->assertRedirect(route('platform.index'));
    }

    public function test_a_signed_in_company_user_opening_the_login_page()
    {
        $user = $this->account();

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect("/{$user->currentTeam->slug}/dashboard");
    }

    public function test_a_signed_in_user_with_no_company_still_gets_a_redirect()
    {
        $user = $this->account();
        $user->currentTeam?->forceDelete();
        $user->teamMemberships()->delete();
        $user->forceFill(['current_team_id' => null])->save();

        $this->actingAs($user->fresh())
            ->get(route('login'))
            ->assertRedirect('/');
    }

    public function test_a_user_with_no_company_at_all()
    {
        $user = $this->account();
        $user->currentTeam?->forceDelete();
        $user->teamMemberships()->delete();
        $user->forceFill(['current_team_id' => null])->save();

        $response = $this->signIn($user->email);

        $this->assertNotSame(500, $response->getStatusCode());
    }
}
