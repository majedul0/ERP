<?php

namespace Tests\Feature\Security;

use App\Enums\TeamRole;
use App\Models\Distributor;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who can reach what, asked directly of every address.
 *
 * The UI hides what somebody cannot do, but hiding is a courtesy — these tests
 * never press a button. They request the URL, which is what an attacker, a
 * bookmark or a stale tab does.
 *
 * Three boundaries are checked:
 *
 * 1. **Signed out** — company pages send you to the login; the platform panel
 *    sends you to *its* login, not the company one.
 * 2. **Signed in, wrong role** — a company user must not learn the platform
 *    panel exists (404, not 403).
 * 3. **Signed in, wrong company** — another tenant's data must not resolve.
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Team $company;

    private User $outsider;

    private Team $otherCompany;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->company = $this->owner->currentTeam;

        $this->outsider = User::factory()->create();
        $this->otherCompany = $this->outsider->currentTeam;
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->currentTeam?->forceDelete();
        $user->teamMemberships()->delete();
        $user->forceFill(['is_super_admin' => true, 'current_team_id' => null])->save();

        return $user->fresh();
    }

    /**
     * Every platform address, as a list, so a new one added without protection
     * shows up here rather than in production.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function platformRoutes(): array
    {
        $slug = $this->company->slug;

        return [
            ['get', '/majedul/companies'],
            ['post', '/majedul/companies'],
            ['patch', "/majedul/companies/{$slug}/suspension"],
            ['patch', "/majedul/companies/{$slug}/plan"],
            ['post', "/majedul/companies/{$slug}/payments"],
            ['get', '/majedul/plans'],
            ['post', '/majedul/plans'],
            ['patch', '/majedul/password'],
            ['post', '/majedul/logout'],
        ];
    }

    // ---------------------------------------------------------------- signed out

    public function test_a_visitor_is_sent_to_the_platform_login_not_the_company_one()
    {
        foreach ($this->platformRoutes() as [$method, $url]) {
            $this->{$method}($url)->assertRedirect(route('platform.login'));
        }
    }

    public function test_a_visitor_cannot_reach_any_company_screen()
    {
        $slug = $this->company->slug;

        foreach ([
            "/{$slug}/dashboard",
            "/{$slug}/sales/invoices",
            "/{$slug}/products",
            "/{$slug}/distributors",
            "/{$slug}/finance/reports",
            "/{$slug}/vendors",
        ] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    /**
     * An unauthenticated API-style call gets a status, not a redirect — there
     * is nobody to read a login page.
     */
    public function test_an_unauthenticated_json_call_is_401()
    {
        $this->getJson("/{$this->company->slug}/sales/stock-version")
            ->assertUnauthorized();
    }

    // -------------------------------------------------------- wrong kind of user

    /**
     * 404 rather than 403: a company user must not learn the panel exists.
     */
    public function test_a_company_user_gets_nothing_from_the_platform_panel()
    {
        foreach ($this->platformRoutes() as [$method, $url]) {
            $this->actingAs($this->owner)
                ->{$method}($url)
                ->assertNotFound();
        }
    }

    public function test_a_company_user_cannot_sign_in_at_the_platform_login()
    {
        $this->post('/majedul', [
            'email' => $this->owner->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_company_cannot_create_a_company()
    {
        $this->actingAs($this->owner)
            ->post(route('teams.store'), ['name' => 'Sneaky Ltd'])
            ->assertForbidden();
    }

    // ------------------------------------------------------------ wrong company

    public function test_another_companys_screens_are_closed()
    {
        $slug = $this->otherCompany->slug;

        foreach ([
            "/{$slug}/dashboard",
            "/{$slug}/sales/invoices",
            "/{$slug}/products",
            "/{$slug}/finance/reports",
        ] as $url) {
            $this->actingAs($this->owner)->get($url)->assertForbidden();
        }
    }

    /**
     * The dangerous case: a valid URL for *my* company, but somebody else's
     * record id in it. Membership passes; the record must not.
     */
    public function test_another_companys_records_do_not_resolve_through_my_own_url()
    {
        $theirProduct = Product::factory()->create(['team_id' => $this->otherCompany->id]);
        $theirDistributor = Distributor::factory()->create(['team_id' => $this->otherCompany->id]);

        $slug = $this->company->slug;

        $this->actingAs($this->owner)
            ->get("/{$slug}/products/{$theirProduct->id}/edit")
            ->assertNotFound();

        $this->actingAs($this->owner)
            ->get("/{$slug}/distributors/{$theirDistributor->id}")
            ->assertNotFound();
    }

    public function test_a_payment_cannot_be_pushed_onto_another_companys_distributor()
    {
        $theirs = Distributor::factory()->create(['team_id' => $this->otherCompany->id]);

        $this->actingAs($this->owner)->post(
            route('payments.store', ['current_team' => $this->company->slug]),
            [
                'distributor_id' => $theirs->id,
                'amount' => 500,
                'paid_on' => now()->toDateString(),
            ],
        )->assertSessionHasErrors('distributor_id');
    }

    // ------------------------------------------------------------- permissions

    public function test_an_ordinary_member_is_refused_the_screens_their_role_excludes()
    {
        $member = User::factory()->create();
        $this->company->memberships()->create([
            'user_id' => $member->id,
            'role' => TeamRole::Member,
        ]);
        $member->switchTeam($this->company);

        $slug = $this->company->slug;

        // A member sells; they do not run the company's money.
        foreach ([
            "/{$slug}/finance/reports",
            "/{$slug}/finance/expenses",
            "/{$slug}/vendors",
            "/{$slug}/raw-materials",
        ] as $url) {
            $this->actingAs($member)->get($url)->assertForbidden();
        }

        // But the job they were hired for still works.
        $this->actingAs($member)->get("/{$slug}/sales/invoices")->assertOk();
        $this->actingAs($member)->get("/{$slug}/sales/invoices/create")->assertOk();
    }

    public function test_a_member_cannot_grant_themselves_more_access()
    {
        $member = User::factory()->create();
        $this->company->memberships()->create([
            'user_id' => $member->id,
            'role' => TeamRole::Member,
        ]);
        $member->switchTeam($this->company);

        $this->actingAs($member)->patch(
            route('teams.members.update', [
                'team' => $this->company->slug,
                'user' => $member->id,
            ]),
            ['role' => TeamRole::Admin->value],
        )->assertForbidden();

        $this->assertSame(TeamRole::Member, $member->fresh()->teamRole($this->company));
    }

    // ------------------------------------------------- the platform admin proper

    public function test_a_platform_admin_can_reach_the_panel()
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get('/majedul/companies')->assertOk();
        $this->actingAs($admin)->get('/majedul/plans')->assertOk();
    }

    /**
     * The panel is for running the platform, not for reading customers' books.
     * A platform admin belongs to no company, so company URLs stay closed.
     */
    public function test_a_platform_admin_does_not_get_into_a_companys_books()
    {
        $this->actingAs($this->superAdmin())
            ->get("/{$this->company->slug}/sales/invoices")
            ->assertForbidden();
    }

    public function test_the_platform_login_is_rate_limited()
    {
        foreach (range(1, 7) as $attempt) {
            $response = $this->post('/majedul', [
                'email' => 'nobody@example.com',
                'password' => 'wrong',
            ]);
        }

        // Six attempts a minute; the seventh is refused outright.
        $response->assertStatus(429);
    }

    public function test_plans_cannot_be_created_by_a_company()
    {
        $this->actingAs($this->owner)
            ->post('/majedul/plans', [
                'name' => 'Free forever',
                'price' => 0,
                'period' => 'monthly',
                'is_active' => true,
            ])
            ->assertNotFound();

        $this->assertSame(0, Plan::count());
    }
}
