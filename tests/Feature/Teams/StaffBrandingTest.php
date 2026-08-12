<?php

namespace Tests\Feature\Teams;

use App\Actions\Teams\CreateTeamMember;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Staff see the company they work for, not a blank header.
 *
 * An employee created by their employer has no personal team, so anything that
 * fell back to one left them with no company name and no logo — which reads as
 * a broken application rather than a missing setting.
 */
class StaffBrandingTest extends TestCase
{
    use RefreshDatabase;

    private Team $company;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->create();
        $this->company = $owner->currentTeam;
        $this->company->update(['name' => 'Galaxy Consumer']);
    }

    private function employee(): User
    {
        return app(CreateTeamMember::class)->handle($this->company, [
            'name' => 'Rahim Uddin',
            'email' => 'rahim@example.com',
            'password' => 'correct-horse-battery-staple',
            'role' => TeamRole::Member->value,
        ]);
    }

    public function test_an_employee_belongs_only_to_the_company_that_created_them()
    {
        $employee = $this->employee();

        $this->assertTrue($employee->belongsToTeam($this->company));
        $this->assertCount(1, $employee->teams);
        $this->assertTrue($employee->isCurrentTeam($this->company));
    }

    public function test_an_employee_sees_the_company_branding()
    {
        $employee = $this->employee();

        $this->actingAs($employee)
            ->get(route('dashboard', ['current_team' => $this->company->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('companyBrand.name', 'Galaxy Consumer')
                ->where('currentTeam.name', 'Galaxy Consumer'),
            );
    }

    /**
     * The case that produced a blank header: a member whose current team was
     * never set. The branding must survive it, and signing in must repair it.
     */
    public function test_branding_survives_a_member_with_no_current_team()
    {
        $employee = $this->employee();
        $employee->forceFill(['current_team_id' => null])->save();

        $this->actingAs($employee->fresh())
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('companyBrand.name', 'Galaxy Consumer'),
            );
    }

    public function test_signing_in_repairs_a_missing_current_team()
    {
        $employee = $this->employee();
        $employee->forceFill(['current_team_id' => null])->save();

        $this->post(route('login.store'), [
            'email' => 'rahim@example.com',
            'password' => 'correct-horse-battery-staple',
        ])->assertRedirect();

        // Persisted, not just used for the redirect — everything downstream
        // reads current_team_id.
        $this->assertTrue($employee->fresh()->isCurrentTeam($this->company));
    }
}
