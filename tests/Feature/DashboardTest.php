<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Enums\TeamRole;
use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $response = $this
            ->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
    }

    public function test_a_company_with_no_invoices_sees_zeros_and_an_empty_table()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('stats.sales', 0)
            ->where('stats.total', 0)
            ->has('todaysSales', 0),
        );
    }

    public function test_todays_sales_lists_invoices_sold_today_only()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $distributor = Distributor::factory()->create(['team_id' => $team->id]);

        $today = Invoice::create([
            'team_id' => $team->id,
            'distributor_id' => $distributor->id,
            'invoice_number' => 'INV1',
            'sequence_number' => 1,
            'sold_at' => now(),
            'invoice_total' => 4560,
            'total_amount' => 4560,
        ]);

        Invoice::create([
            'team_id' => $team->id,
            'distributor_id' => $distributor->id,
            'invoice_number' => 'INV2',
            'sequence_number' => 2,
            'sold_at' => now()->subDay(),
            'invoice_total' => 1000,
            'total_amount' => 1000,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('todaysSales', 1)
            ->where('todaysSales.0.invoiceNumber', 'INV1')
            ->where('todaysSales.0.amount', 4560)
            ->where('todaysSales.0.detailUrl', "/{$team->slug}/sales/invoices/{$today->id}")
            ->where('stats.sales', 4560),
        );
    }

    public function test_another_companys_invoices_never_appear()
    {
        $user = User::factory()->create();
        $outsider = User::factory()->create();

        Invoice::create([
            'team_id' => $outsider->currentTeam->id,
            'distributor_id' => Distributor::factory()->create([
                'team_id' => $outsider->currentTeam->id,
            ])->id,
            'invoice_number' => 'INV1',
            'sequence_number' => 1,
            'sold_at' => now(),
            'invoice_total' => 9999,
            'total_amount' => 9999,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['current_team' => $user->currentTeam->slug]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('todaysSales', 0)
                ->where('stats.sales', 0),
            );
    }

    /**
     * Sales is what was billed today; Total is what was actually collected.
     * They are expected to differ — an invoice raised today may not be paid
     * for a fortnight — which is why the banner shows both.
     */
    public function test_todays_payments_drive_the_total_and_the_payments_figure()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $distributor = Distributor::factory()->create(['team_id' => $team->id]);

        Invoice::create([
            'team_id' => $team->id,
            'distributor_id' => $distributor->id,
            'invoice_number' => 'INV1',
            'sequence_number' => 1,
            'sold_at' => now(),
            'invoice_total' => 60000,
            'total_amount' => 60000,
        ]);

        Payment::factory()->create([
            'team_id' => $team->id,
            'distributor_id' => $distributor->id,
            'paid_on' => now()->toDateString(),
            'amount' => 25000,
        ]);

        // Yesterday's money is not today's takings.
        Payment::factory()->create([
            'team_id' => $team->id,
            'distributor_id' => $distributor->id,
            'paid_on' => now()->subDay()->toDateString(),
            'amount' => 9000,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.sales', 60000)
                ->where('stats.distributorPayments', 25000)
                ->where('stats.total', 25000)
                ->where('stats.expenses', 0),
            );
    }

    public function test_scheme_amounts_are_reported_as_promotions()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        Invoice::create([
            'team_id' => $team->id,
            'distributor_id' => Distributor::factory()->create(['team_id' => $team->id])->id,
            'invoice_number' => 'INV1',
            'sequence_number' => 1,
            'sold_at' => now(),
            'invoice_total' => 10000,
            'scheme_amount' => 500,
            'total_amount' => 9500,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.promotions', 500)
                // Sales is net of the scheme it gave away.
                ->where('stats.sales', 9500),
            );
    }

    public function test_cancelled_invoices_are_left_out_of_the_sales_figure()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        Invoice::create([
            'team_id' => $team->id,
            'distributor_id' => Distributor::factory()->create(['team_id' => $team->id])->id,
            'invoice_number' => 'INV1',
            'sequence_number' => 1,
            'sold_at' => now(),
            'delivery_status' => DeliveryStatus::Cancelled,
            'invoice_total' => 4560,
            'total_amount' => 4560,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('stats.sales', 0));
    }

    public function test_dashboard_includes_the_company_brand_as_a_shared_prop()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('companyBrand.name', $user->currentTeam->name)
            ->where('companyBrand.currencySymbol', config('company.currency_symbol')),
        );
    }

    public function test_dashboard_includes_pending_invitations_for_the_authenticated_user()
    {
        $owner = User::factory()->create(['name' => 'Taylor Otwell']);
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create(['name' => 'Laravel Team']);

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('pendingInvitations', 1)
            ->where('pendingInvitations.0.code', $invitation->code)
            ->where('pendingInvitations.0.inviterName', 'Taylor Otwell')
            ->where('pendingInvitations.0.team.name', 'Laravel Team')
            ->where('pendingInvitations.0.team.slug', $team->slug)
            ->missing('pendingInvitations.0.teamName'),
        );
    }

    public function test_dashboard_does_not_include_accepted_invitations()
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        TeamInvitation::factory()->accepted()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('pendingInvitations', 0),
        );
    }

    public function test_dashboard_excludes_expired_invitations_without_deleting_them()
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->expired()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('pendingInvitations', 0),
        );

        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitation->id,
        ]);
    }

    public function test_dashboard_does_not_include_or_delete_other_users_invitations()
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->expired()->create([
            'team_id' => $team->id,
            'email' => 'someone@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('pendingInvitations', 0),
        );

        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitation->id,
        ]);
    }
}
