<?php

namespace Tests\Feature\SalesReturns;

use App\Actions\Invoices\CreateInvoice;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Goods a distributor sent back.
 *
 * The rule the whole feature rests on: a return is a **credit on the running
 * account**, so it reduces what the distributor owes and every later line on
 * their statement moves with it. It is not a negative invoice, and nothing
 * about it patches a stored figure — the account is replayed.
 */
class SalesReturnTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    private Distributor $distributor;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = $this->user->currentTeam;

        $this->distributor = Distributor::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Milon Store',
        ]);

        $this->product = Product::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Ocean Soap',
            'distributor_price' => 100,
            'stock_quantity' => 500,
        ]);
    }

    /**
     * Put a real debt on an account.
     *
     * Deliberately an invoice rather than `balance => 10_000`: the balance is
     * derived from the ledger, so a figure written straight onto the column is
     * wiped by the first replay. Owing something means having been invoiced for
     * it — which is also what makes these tests exercise the ordering.
     *
     * Each unit is priced at 100, so `$units` of 100 is a debt of 10,000 and
     * takes 100 off the shelf.
     */
    private function raiseInvoice(Distributor $distributor, int $units): void
    {
        app(CreateInvoice::class)->handle($this->team, $this->user, [
            'distributor_id' => $distributor->id,
            'sold_at' => '2026-08-01',
            'items' => [[
                'product_id' => $this->product->id,
                'total_quantity' => $units,
                'unit_price' => 100,
            ]],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'distributor_id' => $this->distributor->id,
            'returned_on' => '2026-08-10',
            'comment' => 'Damage',
            'restock' => true,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 5, 'unit_price' => 100],
            ],
        ], $overrides);
    }

    private function store(): TestResponse
    {
        return $this->actingAs($this->user)->post(
            route('returns.store', ['current_team' => $this->team->slug]),
            $this->payload(),
        );
    }

    public function test_a_return_credits_the_distributors_account()
    {
        $this->raiseInvoice($this->distributor, 100);

        $this->store()->assertRedirect();

        $this->assertSame(500, SalesReturn::first()->return_total);
        // 10,000 owed, 500 of goods came back.
        $this->assertSame(9_500, $this->distributor->fresh()->balance);
    }

    /**
     * The point of the whole feature, stated as a sum: the balance falls by
     * exactly what came back, and by nothing else.
     */
    public function test_the_credit_is_the_value_of_the_goods_and_nothing_else()
    {
        $this->raiseInvoice($this->distributor, 250);

        $this->actingAs($this->user)->post(
            route('returns.store', ['current_team' => $this->team->slug]),
            $this->payload([
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 3, 'unit_price' => 250],
                    ['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 100],
                ],
            ]),
        );

        // 3 × 250 + 2 × 100 = 950.
        $this->assertSame(950, SalesReturn::first()->return_total);
        $this->assertSame(24_050, $this->distributor->fresh()->balance);
    }

    public function test_the_price_falls_back_to_the_products_own_when_none_is_given()
    {
        $this->actingAs($this->user)->post(
            route('returns.store', ['current_team' => $this->team->slug]),
            $this->payload([
                'items' => [['product_id' => $this->product->id, 'quantity' => 4]],
            ]),
        );

        // 4 × the product's 100.
        $this->assertSame(400, SalesReturn::first()->return_total);
    }

    public function test_numbers_are_sequential_per_company()
    {
        $this->store();
        $this->store();

        $numbers = SalesReturn::orderBy('id')->pluck('return_number')->all();

        $this->assertSame(['RNV1', 'RNV2'], $numbers);
    }

    public function test_returned_goods_go_back_into_stock()
    {
        $this->raiseInvoice($this->distributor, 100);
        $this->assertSame(400, $this->product->fresh()->stock_quantity);

        $this->store();

        $this->assertSame(405, $this->product->fresh()->stock_quantity);
    }

    /**
     * Damaged goods are credited but never become sellable again — the two are
     * separate decisions, which is why `restock` exists.
     */
    public function test_goods_that_are_not_restocked_still_credit_the_account()
    {
        $this->raiseInvoice($this->distributor, 100);

        $this->actingAs($this->user)->post(
            route('returns.store', ['current_team' => $this->team->slug]),
            $this->payload(['restock' => false]),
        );

        // Still 400: the goods were credited but never went back on the shelf.
        $this->assertSame(400, $this->product->fresh()->stock_quantity);
        $this->assertSame(9_500, $this->distributor->fresh()->balance);
    }

    public function test_a_fractional_price_is_rejected_rather_than_rounded()
    {
        $this->actingAs($this->user)->post(
            route('returns.store', ['current_team' => $this->team->slug]),
            $this->payload([
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 99.5],
                ],
            ]),
        )->assertSessionHasErrors('items.0.unit_price');

        $this->assertSame(0, SalesReturn::count());
    }

    /**
     * The statement is the proof the two agree: the invoice charges, the return
     * credits, and the closing balance is the distributor's due.
     */
    public function test_the_return_appears_on_the_statement_as_a_credit()
    {
        $this->raiseInvoice($this->distributor, 100);
        $this->store();

        $this->actingAs($this->user)
            ->get(route('statements.print', [
                'current_team' => $this->team->slug,
                'distributor' => $this->distributor->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('statement', 2)
                ->where('statement.0.type', 'invoice')
                ->where('statement.0.debit', 10_000)
                ->where('statement.1.type', 'return')
                ->where('statement.1.reference', 'RNV1')
                ->where('statement.1.credit', 500)
                ->where('statement.1.debit', 0)
                ->where('statement.1.balanceAfter', 9_500),
            );
    }

    public function test_editing_a_return_replays_the_account_and_the_stock()
    {
        $this->raiseInvoice($this->distributor, 100);
        $this->store();

        $return = SalesReturn::first();

        $this->actingAs($this->user)->put(
            route('returns.update', [
                'current_team' => $this->team->slug,
                'return' => $return->id,
            ]),
            $this->payload([
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 100],
                ],
            ]),
        )->assertRedirect();

        $this->assertSame(200, $return->fresh()->return_total);
        // The five that came back went off the shelf again; two went on.
        $this->assertSame(402, $this->product->fresh()->stock_quantity);
        $this->assertSame(9_800, $this->distributor->fresh()->balance);
    }

    /**
     * The number is on paper the distributor already has.
     */
    public function test_the_number_never_changes_on_edit()
    {
        $this->store();
        $return = SalesReturn::first();

        $this->actingAs($this->user)->put(
            route('returns.update', [
                'current_team' => $this->team->slug,
                'return' => $return->id,
            ]),
            $this->payload(['comment' => 'Corrected']),
        );

        $this->assertSame('RNV1', $return->fresh()->return_number);
    }

    public function test_moving_a_return_to_another_distributor_replays_both_accounts()
    {
        $other = Distributor::factory()->create(['team_id' => $this->team->id]);

        $this->raiseInvoice($this->distributor, 100);
        $this->raiseInvoice($other, 40);

        $this->store();
        $this->assertSame(9_500, $this->distributor->fresh()->balance);

        $return = SalesReturn::first();

        $this->actingAs($this->user)->put(
            route('returns.update', [
                'current_team' => $this->team->slug,
                'return' => $return->id,
            ]),
            $this->payload(['distributor_id' => $other->id]),
        );

        // The credit left the first account and landed on the second.
        $this->assertSame(10_000, $this->distributor->fresh()->balance);
        $this->assertSame(3_500, $other->fresh()->balance);
    }

    public function test_deleting_a_return_gives_back_the_debt_and_takes_back_the_stock()
    {
        $this->raiseInvoice($this->distributor, 100);
        $this->store();

        $return = SalesReturn::first();

        $this->actingAs($this->user)->delete(route('returns.destroy', [
            'current_team' => $this->team->slug,
            'return' => $return->id,
        ]))->assertRedirect();

        $this->assertSoftDeleted('sales_returns', ['id' => $return->id]);
        $this->assertSame(10_000, $this->distributor->fresh()->balance);
        $this->assertSame(400, $this->product->fresh()->stock_quantity);
    }

    /**
     * A deleted number is never handed out again — it may already name a
     * document somebody is holding.
     */
    public function test_a_deleted_number_is_not_reused()
    {
        $this->store();

        $this->actingAs($this->user)->delete(route('returns.destroy', [
            'current_team' => $this->team->slug,
            'return' => SalesReturn::first()->id,
        ]));

        $this->store();

        $this->assertSame('RNV2', SalesReturn::latest('id')->first()->return_number);
    }

    /**
     * Taking stock back off the shelf that has since been sold on would leave a
     * negative figure, which nothing else in the system can produce.
     */
    public function test_deleting_is_refused_when_the_goods_have_been_sold_on()
    {
        $this->store();

        // One left on the shelf — less than the 5 this return would have to
        // take back off it.
        $this->product->update(['stock_quantity' => 1]);

        $this->actingAs($this->user)->delete(route('returns.destroy', [
            'current_team' => $this->team->slug,
            'return' => SalesReturn::first()->id,
        ]))->assertSessionHasErrors('items');

        $this->assertSame(1, SalesReturn::count());
        $this->assertSame(1, $this->product->fresh()->stock_quantity);
    }

    public function test_the_report_nets_returns_off_sales()
    {
        $this->store();

        $this->actingAs($this->user)
            ->get(route('reports.index', ['current_team' => $this->team->slug]).'?from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.sales.returns', 500)
                ->where('report.sales.net', -500),
            );
    }

    /**
     * The printed sheet is a document handed to the distributor, so it needs
     * the same identity block a sales invoice prints — not just the name that
     * the on-screen cards show.
     */
    public function test_the_detail_screen_carries_what_the_printed_sheet_needs()
    {
        $this->distributor->update([
            'phone' => '01711111111',
            'address' => '12 Mirpur Road',
        ]);

        $this->store();

        $this->actingAs($this->user)
            ->get(route('returns.show', [
                'current_team' => $this->team->slug,
                'return' => SalesReturn::first()->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('company/sales-returns/show')
                ->where('return.distributor.phone', '01711111111')
                ->where('return.distributor.fullAddress', fn (string $address) => str_contains($address, '12 Mirpur Road'))
                ->where('return.createdBy', $this->user->name)
                ->has('return.items', 1),
            );
    }

    public function test_a_distributor_from_another_company_is_rejected()
    {
        $theirs = Distributor::factory()->create();

        $this->actingAs($this->user)->post(
            route('returns.store', ['current_team' => $this->team->slug]),
            $this->payload(['distributor_id' => $theirs->id]),
        )->assertSessionHasErrors('distributor_id');

        $this->assertSame(0, SalesReturn::count());
    }

    public function test_another_companys_return_is_not_found()
    {
        $theirs = SalesReturn::create([
            'team_id' => Team::factory()->create()->id,
            'distributor_id' => Distributor::factory()->create()->id,
            'return_number' => 'RNV1',
            'sequence_number' => 1,
            'returned_on' => '2026-08-10',
            'return_total' => 100,
        ]);

        $this->actingAs($this->user)
            ->get(route('returns.show', [
                'current_team' => $this->team->slug,
                'return' => $theirs->id,
            ]))
            ->assertNotFound();
    }

    public function test_the_list_only_shows_this_companys_returns()
    {
        $this->store();

        SalesReturn::create([
            'team_id' => Team::factory()->create()->id,
            'distributor_id' => Distributor::factory()->create()->id,
            'return_number' => 'RNV1',
            'sequence_number' => 1,
            'returned_on' => '2026-08-10',
            'return_total' => 100,
        ]);

        $this->actingAs($this->user)
            ->get(route('returns.index', ['current_team' => $this->team->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('company/sales-returns/index')
                ->has('returns', 1)
                ->where('returns.0.distributorName', 'Milon Store')
                ->where('total', 500),
            );
    }

    public function test_a_member_without_permission_cannot_record_a_return()
    {
        $member = $this->memberWith([]);

        $this->actingAs($member)->post(
            route('returns.store', ['current_team' => $this->team->slug]),
            $this->payload(),
        )->assertForbidden();

        $this->assertSame(0, SalesReturn::count());
    }

    /**
     * Viewing is not managing: somebody who may read the returns must not be
     * able to write one, and the route is what decides — not the hidden button.
     */
    public function test_a_viewer_can_read_but_not_write()
    {
        $member = $this->memberWith([TeamPermission::ViewReturns->value]);

        $this->actingAs($member)
            ->get(route('returns.index', ['current_team' => $this->team->slug]))
            ->assertOk();

        $this->actingAs($member)
            ->get(route('returns.create', ['current_team' => $this->team->slug]))
            ->assertForbidden();
    }

    public function test_guests_are_sent_to_the_login()
    {
        $this->get(route('returns.index', ['current_team' => $this->team->slug]))
            ->assertRedirect(route('login'));
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function memberWith(array $permissions): User
    {
        $member = User::factory()->create();

        $this->team->memberships()->create([
            'user_id' => $member->id,
            'role' => TeamRole::Member,
            'permissions' => $permissions,
        ]);

        $member->switchTeam($this->team);

        return $member;
    }
}
