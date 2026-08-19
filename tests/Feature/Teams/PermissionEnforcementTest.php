<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The UI hides what a member cannot do; this is the part that actually stops
 * them.
 *
 * A hidden button is still a reachable URL, so every one of these asks for the
 * address directly and expects a 403.
 */
class PermissionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Team $team;

    private Product $product;

    private Distributor $distributor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->team = $this->owner->currentTeam;
        $this->product = Product::factory()->create([
            'team_id' => $this->team->id,
            'distributor_price' => 100,
            'stock_quantity' => 500,
        ]);
        $this->distributor = Distributor::factory()->create(['team_id' => $this->team->id]);
    }

    /**
     * @param  array<int, TeamPermission>|null  $permissions
     */
    private function member(?array $permissions = null, TeamRole $role = TeamRole::Member): User
    {
        $user = User::factory()->create();

        $this->team->memberships()->create([
            'user_id' => $user->id,
            'role' => $role,
            'permissions' => $permissions === null
                ? null
                : array_map(fn (TeamPermission $p) => $p->value, $permissions),
        ]);

        $user->switchTeam($this->team);

        return $user;
    }

    private function url(string $path): string
    {
        return "/{$this->team->slug}/{$path}";
    }

    public function test_a_member_can_see_invoices_by_default()
    {
        $this->actingAs($this->member())
            ->get($this->url('sales/invoices'))
            ->assertOk();
    }

    /**
     * The case that prompted this: an ordinary employee raises invoices but
     * must not be able to rewrite one after the fact.
     */
    public function test_a_member_cannot_edit_an_invoice_by_default()
    {
        $member = $this->member();

        $invoice = $this->owner->currentTeam->invoices()->create([
            'team_id' => $this->team->id,
            'distributor_id' => $this->distributor->id,
            'created_by' => $this->owner->id,
            'invoice_number' => 'INV1',
            'sequence_number' => 1,
            'sold_at' => now(),
            'delivery_status' => 'pending',
            'invoice_total' => 100,
            'discount_total' => 0,
            'scheme_amount' => 0,
            'previous_dues' => 0,
            'total_amount' => 100,
        ]);

        $this->actingAs($member)
            ->get($this->url("sales/invoices/{$invoice->id}/edit"))
            ->assertForbidden();

        $this->actingAs($member)
            ->delete($this->url("sales/invoices/{$invoice->id}"))
            ->assertForbidden();
    }

    public function test_a_member_can_still_create_invoices()
    {
        $this->actingAs($this->member())
            ->get($this->url('sales/invoices/create'))
            ->assertOk();
    }

    public function test_a_member_cannot_reach_finance_by_default()
    {
        $member = $this->member();

        $this->actingAs($member)->get($this->url('finance/expenses'))->assertForbidden();
        $this->actingAs($member)->get($this->url('finance/reports'))->assertForbidden();
        $this->actingAs($member)->get($this->url('vendors'))->assertForbidden();
        $this->actingAs($member)->get($this->url('raw-materials'))->assertForbidden();
    }

    /**
     * A salesperson has no business reading a colleague's record, so none of
     * the People screens are in the Member role's list.
     */
    public function test_a_member_cannot_reach_the_people_screens_by_default()
    {
        $member = $this->member();

        $this->actingAs($member)->get($this->url('hr/employees'))->assertForbidden();
        $this->actingAs($member)->get($this->url('hr/departments'))->assertForbidden();
        $this->actingAs($member)->get($this->url('hr/employees/create'))->assertForbidden();
        $this->actingAs($member)->get($this->url('hr/attendance'))->assertForbidden();
        $this->actingAs($member)->get($this->url('hr/holidays'))->assertForbidden();
        $this->actingAs($member)->get($this->url('hr/payroll'))->assertForbidden();
        $this->actingAs($member)->get($this->url('hr/salary-payments'))->assertForbidden();
    }

    /**
     * Attendance is the one People permission a supervisor plausibly gets on
     * its own, and it must not carry the registry — or a salary — with it.
     */
    public function test_attendance_permission_does_not_open_the_employee_registry()
    {
        $member = $this->member([TeamPermission::ViewAttendance]);

        $this->actingAs($member)->get($this->url('hr/attendance'))->assertOk();

        $this->actingAs($member)->get($this->url('hr/employees'))->assertForbidden();
        $this->actingAs($member)->get($this->url('hr/employees/create'))->assertForbidden();
        // And above all, not the money.
        $this->actingAs($member)->get($this->url('hr/payroll'))->assertForbidden();
    }

    public function test_a_tailored_permission_grants_exactly_that_screen()
    {
        $member = $this->member([TeamPermission::ViewReports]);

        $this->actingAs($member)->get($this->url('finance/reports'))->assertOk();

        // And nothing else came with it — not even the invoices a plain member
        // would have seen, because a tailored list replaces the role's.
        $this->actingAs($member)->get($this->url('sales/invoices'))->assertForbidden();
    }

    /**
     * An empty list is a deliberate "nothing", not "fall back to the role".
     */
    public function test_an_empty_permission_list_grants_nothing()
    {
        $member = $this->member([]);

        $this->actingAs($member)->get($this->url('sales/invoices'))->assertForbidden();
        $this->actingAs($member)->get($this->url('products'))->assertForbidden();
    }

    public function test_viewing_products_does_not_allow_repricing_them()
    {
        $member = $this->member([TeamPermission::ViewProducts]);

        $this->actingAs($member)->get($this->url('products'))->assertOk();
        $this->actingAs($member)
            ->get($this->url("products/{$this->product->id}/edit"))
            ->assertForbidden();
    }

    public function test_an_admin_can_reach_everything_except_deleting_the_company()
    {
        $admin = $this->member(role: TeamRole::Admin);

        $this->actingAs($admin)->get($this->url('finance/reports'))->assertOk();
        $this->actingAs($admin)->get($this->url('vendors'))->assertOk();
        $this->actingAs($admin)->get($this->url('finance/expenses'))->assertOk();
        // Including the People screens: an admin inherits every case, which is
        // the decision recorded against `payroll:view` — see TeamRole.
        $this->actingAs($admin)->get($this->url('hr/employees'))->assertOk();

        $this->assertFalse($admin->hasTeamPermission($this->team, TeamPermission::DeleteTeam));
    }

    public function test_the_owner_keeps_every_permission()
    {
        foreach (TeamPermission::cases() as $permission) {
            $this->assertTrue(
                $this->owner->hasTeamPermission($this->team, $permission),
                "The owner should have {$permission->value}.",
            );
        }
    }

    /**
     * The map React reads to hide menus must agree with what the server does.
     */
    public function test_the_permission_map_is_shared_with_the_front_end()
    {
        $this->actingAs($this->member([TeamPermission::ViewReports]))
            ->get($this->url('finance/reports'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.report:view', true)
                ->where('can.invoice:update', false)
                ->where('can.expense:manage', false),
            );
    }

    public function test_permissions_can_be_tailored_from_settings()
    {
        $member = $this->member();

        $this->actingAs($this->owner)->patch(
            route('teams.members.update', ['team' => $this->team->slug, 'user' => $member->id]),
            [
                'role' => TeamRole::Member->value,
                'permissions' => [
                    TeamPermission::ViewInvoices->value,
                    TeamPermission::UpdateInvoice->value,
                ],
            ],
        )->assertRedirect();

        $this->assertTrue($member->hasTeamPermission($this->team, TeamPermission::UpdateInvoice));
        $this->assertFalse($member->hasTeamPermission($this->team, TeamPermission::CreateInvoice));
    }

    /**
     * Submitting without the key means "follow the role" again.
     */
    public function test_omitting_permissions_resets_a_member_to_their_role()
    {
        $member = $this->member([TeamPermission::ViewReports]);

        $this->actingAs($this->owner)->patch(
            route('teams.members.update', ['team' => $this->team->slug, 'user' => $member->id]),
            ['role' => TeamRole::Member->value],
        )->assertRedirect();

        $this->assertFalse($member->hasTeamPermission($this->team, TeamPermission::ViewReports));
        $this->assertTrue($member->hasTeamPermission($this->team, TeamPermission::CreateInvoice));
    }

    public function test_a_member_cannot_change_their_own_permissions()
    {
        $member = $this->member();

        $this->actingAs($member)->patch(
            route('teams.members.update', ['team' => $this->team->slug, 'user' => $member->id]),
            [
                'role' => TeamRole::Member->value,
                'permissions' => [TeamPermission::DeleteInvoice->value],
            ],
        )->assertForbidden();

        $this->assertFalse($member->hasTeamPermission($this->team, TeamPermission::DeleteInvoice));
    }

    /**
     * Someone has to be able to hand access back after a mistake.
     */
    public function test_the_owners_access_cannot_be_taken_away()
    {
        $this->actingAs($this->owner)->patch(
            route('teams.members.update', ['team' => $this->team->slug, 'user' => $this->owner->id]),
            ['role' => TeamRole::Member->value, 'permissions' => []],
        )->assertForbidden();

        $this->assertTrue(
            $this->owner->hasTeamPermission($this->team, TeamPermission::DeleteTeam),
        );
    }

    public function test_an_unknown_permission_in_the_request_is_refused()
    {
        $member = $this->member();

        $this->actingAs($this->owner)->patch(
            route('teams.members.update', ['team' => $this->team->slug, 'user' => $member->id]),
            ['role' => TeamRole::Member->value, 'permissions' => ['invoice:destroy-everything']],
        )->assertSessionHasErrors('permissions.0');
    }
}
