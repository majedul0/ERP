<?php

namespace Tests\Feature\Platform;

use App\Enums\SuspensionMode;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * What a suspended company's staff actually experience.
 *
 * Walked end to end — sign in, land somewhere, read something that explains it
 * — rather than asserting the middleware in isolation, because the middleware
 * being right is not the same as the person understanding what happened.
 */
class SuspensionFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Team $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->owner->forceFill(['password' => Hash::make('a-long-enough-password')])->save();
        $this->owner = $this->owner->fresh();

        $this->company = $this->owner->currentTeam;
    }

    private function signIn()
    {
        return $this->post(route('login.store'), [
            'email' => $this->owner->email,
            'password' => 'a-long-enough-password',
        ]);
    }

    /**
     * Signing in still works — it is the company that is closed, not the
     * account — and every company page sends them to the explanation.
     */
    public function test_signing_in_to_a_suspended_company_explains_why()
    {
        $this->company->update(['suspended_at' => now()]);

        $this->signIn()->assertRedirect("/{$this->company->slug}/dashboard");

        $this->actingAs($this->owner)
            ->get(route('dashboard', ['current_team' => $this->company->slug]))
            ->assertRedirect(route('company.suspended'));

        $this->actingAs($this->owner)
            ->get(route('company.suspended'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('suspended')
                ->where('company.name', $this->company->name),
            );
    }

    /**
     * Not just the dashboard: every door into the company is closed.
     */
    public function test_no_company_screen_is_reachable_while_suspended()
    {
        $this->company->update(['suspended_at' => now()]);

        foreach (['sales/invoices', 'products', 'distributors', 'finance/reports'] as $path) {
            $this->actingAs($this->owner)
                ->get("/{$this->company->slug}/{$path}")
                ->assertRedirect(route('company.suspended'));
        }
    }

    /**
     * Nobody should be stranded on the explanation once it no longer applies.
     */
    public function test_the_suspended_page_sends_an_active_company_back_to_work()
    {
        $this->actingAs($this->owner)
            ->get(route('company.suspended'))
            ->assertRedirect("/{$this->company->slug}/dashboard");
    }

    public function test_lifting_the_suspension_restores_everything()
    {
        $this->company->update(['suspended_at' => now()]);

        $this->actingAs($this->owner)
            ->get(route('dashboard', ['current_team' => $this->company->slug]))
            ->assertRedirect(route('company.suspended'));

        $this->company->update(['suspended_at' => null]);

        // Straight back to work, with everything where they left it.
        $this->actingAs($this->owner)
            ->get(route('dashboard', ['current_team' => $this->company->slug]))
            ->assertOk();
    }

    /**
     * The opaque modes: the company meets a broken-looking page and is told
     * nothing about why.
     */
    public function test_the_not_found_mode_answers_404()
    {
        $this->company->update([
            'suspended_at' => now(),
            'suspension_mode' => SuspensionMode::NotFound,
        ]);

        $this->actingAs($this->owner)
            ->get(route('dashboard', ['current_team' => $this->company->slug]))
            ->assertNotFound();
    }

    public function test_the_error_mode_answers_500()
    {
        $this->company->update([
            'suspended_at' => now(),
            'suspension_mode' => SuspensionMode::Error,
        ]);

        $this->actingAs($this->owner)
            ->get(route('dashboard', ['current_team' => $this->company->slug]))
            ->assertStatus(500);
    }

    public function test_the_mode_is_chosen_from_the_panel()
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->patch(
            route('platform.companies.suspend', ['team' => $this->company->slug]),
            ['suspend' => true, 'mode' => SuspensionMode::NotFound->value],
        )->assertRedirect();

        $this->assertSame(SuspensionMode::NotFound, $this->company->fresh()->suspension_mode);
    }

    /**
     * Lifting a suspension must not quietly reset the choice — re-suspending
     * should behave the way it did last time unless told otherwise.
     */
    public function test_reinstating_keeps_the_mode_for_next_time()
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->patch(
            route('platform.companies.suspend', ['team' => $this->company->slug]),
            ['suspend' => true, 'mode' => SuspensionMode::Error->value],
        );

        $this->actingAs($admin)->patch(
            route('platform.companies.suspend', ['team' => $this->company->slug]),
            ['suspend' => false],
        );

        $this->assertNull($this->company->fresh()->suspended_at);
        $this->assertSame(SuspensionMode::Error, $this->company->fresh()->suspension_mode);
    }

    public function test_an_unknown_mode_is_refused()
    {
        $this->actingAs($this->platformAdmin())->patch(
            route('platform.companies.suspend', ['team' => $this->company->slug]),
            ['suspend' => true, 'mode' => 'delete-everything'],
        )->assertSessionHasErrors('mode');
    }

    public function test_a_json_caller_still_gets_the_status_code()
    {
        $this->company->update(['suspended_at' => now()]);

        $this->actingAs($this->owner)
            ->getJson("/{$this->company->slug}/sales/stock-version")
            ->assertForbidden();
    }

    private function platformAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->currentTeam?->forceDelete();
        $admin->teamMemberships()->delete();
        $admin->forceFill(['is_super_admin' => true, 'current_team_id' => null])->save();

        return $admin->fresh();
    }

    public function test_the_panel_toggle_actually_persists()
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin->fresh())->patch(
            route('platform.companies.suspend', ['team' => $this->company->slug]),
            ['suspend' => true],
        )->assertRedirect();

        $this->assertNotNull($this->company->fresh()->suspended_at);

        $this->actingAs($admin->fresh())->patch(
            route('platform.companies.suspend', ['team' => $this->company->slug]),
            ['suspend' => false],
        )->assertRedirect();

        $this->assertNull($this->company->fresh()->suspended_at);
    }
}
