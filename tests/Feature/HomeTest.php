<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_the_login_screen_on_the_home_page()
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('canResetPassword', true),
        );
    }

    public function test_authenticated_users_are_sent_to_their_company_dashboard()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('home'));

        $response->assertRedirect(
            route('dashboard', ['current_team' => $user->currentTeam->slug]),
        );
    }
}
