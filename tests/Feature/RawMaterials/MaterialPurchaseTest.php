<?php

namespace Tests\Feature\RawMaterials;

use App\Models\MaterialPurchase;
use App\Models\RawMaterial;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MaterialPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    private RawMaterial $sugar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = $this->user->currentTeam;

        $this->sugar = RawMaterial::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Refined Sugar',
            'code' => 'SUG-01',
            'stock_quantity' => 100,
            'unit_cost' => 110,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'supplier_name' => 'Padma Traders',
            'reference' => 'BILL-9001',
            'purchased_at' => '2026-08-01',
            'note' => null,
            'items' => [
                ['raw_material_id' => $this->sugar->id, 'quantity' => 50, 'unit_cost' => 120],
            ],
            ...$overrides,
        ];
    }

    private function store(array $overrides = [])
    {
        return $this->actingAs($this->user)->post(
            route('purchases.store', ['current_team' => $this->team->slug]),
            $this->payload($overrides),
        );
    }

    public function test_a_purchase_is_recorded_against_the_current_company()
    {
        $this->store()->assertRedirect();

        $purchase = MaterialPurchase::firstOrFail();

        $this->assertSame($this->team->id, $purchase->team_id);
        $this->assertSame($this->user->id, $purchase->created_by);
        $this->assertSame('Padma Traders', $purchase->supplier_name);
        $this->assertSame('BILL-9001', $purchase->reference);
        $this->assertCount(1, $purchase->items);
    }

    public function test_the_server_computes_the_totals_not_the_browser()
    {
        // The request carries no totals at all; they can only come from the
        // quantities and costs, recomputed server-side.
        $this->store([
            'items' => [
                ['raw_material_id' => $this->sugar->id, 'quantity' => 50, 'unit_cost' => 120],
                ['raw_material_id' => $this->sugar->id, 'quantity' => 10, 'unit_cost' => 130],
            ],
        ]);

        $purchase = MaterialPurchase::firstOrFail();

        $this->assertSame(50 * 120 + 10 * 130, $purchase->total_amount);
        $this->assertSame(6000, $purchase->items[0]->line_total);
        $this->assertSame(1300, $purchase->items[1]->line_total);
    }

    public function test_stock_goes_up_by_what_was_delivered()
    {
        $this->store(['items' => [
            ['raw_material_id' => $this->sugar->id, 'quantity' => 50, 'unit_cost' => 120],
        ]]);

        $this->assertSame(150, $this->sugar->refresh()->stock_quantity);
    }

    public function test_the_same_material_on_two_lines_lands_once_with_both_quantities()
    {
        $this->store([
            'items' => [
                ['raw_material_id' => $this->sugar->id, 'quantity' => 50, 'unit_cost' => 120],
                ['raw_material_id' => $this->sugar->id, 'quantity' => 25, 'unit_cost' => 130],
            ],
        ]);

        $this->assertSame(175, $this->sugar->refresh()->stock_quantity);
    }

    public function test_the_last_price_paid_becomes_the_materials_unit_cost()
    {
        $this->store(['items' => [
            ['raw_material_id' => $this->sugar->id, 'quantity' => 50, 'unit_cost' => 120],
        ]]);

        $this->assertSame(120, $this->sugar->refresh()->unit_cost);
    }

    public function test_purchase_lines_keep_the_price_they_were_bought_at()
    {
        $this->store(['items' => [
            ['raw_material_id' => $this->sugar->id, 'quantity' => 50, 'unit_cost' => 120],
        ]]);

        $this->sugar->update(['unit_cost' => 400, 'name' => 'Renamed Sugar']);

        $item = MaterialPurchase::firstOrFail()->items->first();

        // Repricing and renaming reach forward only — the delivery note stands.
        $this->assertSame(120, $item->unit_cost);
        $this->assertSame('Refined Sugar', $item->material_name);
        $this->assertSame('SUG-01', $item->material_code);
    }

    public function test_a_fractional_cost_is_rejected_and_no_stock_moves()
    {
        $this->store(['items' => [
            ['raw_material_id' => $this->sugar->id, 'quantity' => 50, 'unit_cost' => 120.5],
        ]])->assertSessionHasErrors('items.0.unit_cost');

        $this->assertSame(0, MaterialPurchase::count());
        $this->assertSame(100, $this->sugar->refresh()->stock_quantity);
    }

    public function test_a_purchase_needs_at_least_one_line()
    {
        $this->store(['items' => []])->assertSessionHasErrors('items');

        $this->assertSame(0, MaterialPurchase::count());
    }

    public function test_another_companys_material_cannot_be_purchased_into()
    {
        $theirs = RawMaterial::factory()->create(['stock_quantity' => 5]);

        $this->store(['items' => [
            ['raw_material_id' => $theirs->id, 'quantity' => 50, 'unit_cost' => 120],
        ]])->assertSessionHasErrors('items.0.raw_material_id');

        $this->assertSame(0, MaterialPurchase::count());
        $this->assertSame(5, $theirs->refresh()->stock_quantity);
    }

    public function test_the_list_shows_only_the_current_companys_purchases()
    {
        MaterialPurchase::factory()->create([
            'team_id' => $this->team->id,
            'created_by' => $this->user->id,
            'supplier_name' => 'Ours',
        ]);
        MaterialPurchase::factory()->create(['supplier_name' => 'Theirs']);

        $this->actingAs($this->user)
            ->get(route('purchases.index', ['current_team' => $this->team->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('company/raw-materials/purchases/index')
                ->has('purchases', 1)
                ->where('purchases.0.supplierName', 'Ours'),
            );
    }

    public function test_a_purchase_from_another_company_cannot_be_viewed()
    {
        $theirs = MaterialPurchase::factory()->create();

        $this->actingAs($this->user)->get(route('purchases.show', [
            'current_team' => $this->team->slug,
            'purchase' => $theirs->id,
        ]))->assertNotFound();
    }

    public function test_guests_cannot_record_purchases()
    {
        $this->post(
            route('purchases.store', ['current_team' => $this->team->slug]),
            $this->payload(),
        )->assertRedirect(route('login'));

        $this->assertSame(0, MaterialPurchase::count());
    }
}
