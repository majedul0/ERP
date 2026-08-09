<?php

namespace Tests\Feature\RawMaterials;

use App\Enums\MaterialUnit;
use App\Models\RawMaterial;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RawMaterialTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = $this->user->currentTeam;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Refined Sugar',
            'code' => 'SUG-01',
            'unit' => 'kg',
            'stock_quantity' => 800,
            'reorder_level' => 100,
            'unit_cost' => 120,
            'note' => null,
            ...$overrides,
        ];
    }

    private function store(array $overrides = [])
    {
        return $this->actingAs($this->user)->post(
            route('materials.store', ['current_team' => $this->team->slug]),
            $this->payload($overrides),
        );
    }

    public function test_a_material_is_registered_against_the_current_company()
    {
        $this->store()->assertRedirect(
            route('materials.index', ['current_team' => $this->team->slug]),
        );

        $material = RawMaterial::firstOrFail();

        $this->assertSame($this->team->id, $material->team_id);
        $this->assertSame('Refined Sugar', $material->name);
        $this->assertSame(MaterialUnit::Kilogram, $material->unit);
        $this->assertSame(800, $material->stock_quantity);
        $this->assertSame(100, $material->reorder_level);
        $this->assertSame(120, $material->unit_cost);
    }

    public function test_a_fractional_cost_is_rejected_rather_than_rounded()
    {
        $this->store(['unit_cost' => 120.55])
            ->assertSessionHasErrors([
                'unit_cost' => 'Costs must be whole amounts, with no decimals.',
            ]);

        $this->assertSame(0, RawMaterial::count());
    }

    public function test_a_fractional_quantity_is_rejected()
    {
        $this->store(['stock_quantity' => 2.5])
            ->assertSessionHasErrors('stock_quantity');

        $this->assertSame(0, RawMaterial::count());
    }

    public function test_an_unknown_unit_is_rejected()
    {
        $this->store(['unit' => 'barrels'])->assertSessionHasErrors('unit');

        $this->assertSame(0, RawMaterial::count());
    }

    public function test_a_code_cannot_repeat_within_a_company()
    {
        $this->store();
        $this->store()->assertSessionHasErrors('code');

        $this->assertSame(1, RawMaterial::count());
    }

    public function test_two_companies_may_use_the_same_code()
    {
        $this->store();

        $other = User::factory()->create();

        $this->actingAs($other)->post(
            route('materials.store', ['current_team' => $other->currentTeam->slug]),
            $this->payload(),
        )->assertSessionHasNoErrors();

        $this->assertSame(2, RawMaterial::count());
    }

    public function test_required_fields_are_enforced()
    {
        $this->actingAs($this->user)->post(
            route('materials.store', ['current_team' => $this->team->slug]),
            [],
        )->assertSessionHasErrors([
            'name',
            'code',
            'unit',
            'stock_quantity',
            'reorder_level',
            'unit_cost',
        ]);
    }

    public function test_the_list_shows_only_the_current_companys_materials()
    {
        RawMaterial::factory()->create(['team_id' => $this->team->id, 'name' => 'Ours']);
        RawMaterial::factory()->create(['name' => 'Theirs']);

        $this->actingAs($this->user)
            ->get(route('materials.index', ['current_team' => $this->team->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('company/raw-materials/index')
                ->has('materials', 1)
                ->where('materials.0.name', 'Ours'),
            );
    }

    public function test_the_list_carries_stock_value_and_the_low_flag()
    {
        RawMaterial::factory()->create([
            'team_id' => $this->team->id,
            'stock_quantity' => 40,
            'reorder_level' => 100,
            'unit_cost' => 25,
        ]);

        $this->actingAs($this->user)
            ->get(route('materials.index', ['current_team' => $this->team->slug]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('materials.0.stockValue', 1000)
                ->where('materials.0.isLow', true),
            );
    }

    public function test_a_reorder_level_of_zero_never_reads_as_low()
    {
        $material = RawMaterial::factory()->create([
            'team_id' => $this->team->id,
            'stock_quantity' => 0,
            'reorder_level' => 0,
        ]);

        $this->assertFalse($material->isLow());
    }

    public function test_a_material_can_be_updated()
    {
        $material = RawMaterial::factory()->create([
            'team_id' => $this->team->id,
            'stock_quantity' => 100,
            'unit_cost' => 50,
        ]);

        $this->actingAs($this->user)->put(
            route('materials.update', [
                'current_team' => $this->team->slug,
                'material' => $material->id,
            ]),
            $this->payload(['code' => $material->code, 'stock_quantity' => 250, 'unit_cost' => 60]),
        )->assertRedirect(route('materials.index', ['current_team' => $this->team->slug]));

        $material->refresh();

        // An absolute recount, not a delta added to what was there.
        $this->assertSame(250, $material->stock_quantity);
        $this->assertSame(60, $material->unit_cost);
    }

    public function test_a_material_cannot_be_edited_from_another_company()
    {
        $theirs = RawMaterial::factory()->create();

        $this->actingAs($this->user)->get(
            route('materials.edit', [
                'current_team' => $this->team->slug,
                'material' => $theirs->id,
            ]),
        )->assertNotFound();
    }

    public function test_a_material_cannot_be_updated_from_another_company()
    {
        $theirs = RawMaterial::factory()->create(['name' => 'Theirs']);

        $this->actingAs($this->user)->put(
            route('materials.update', [
                'current_team' => $this->team->slug,
                'material' => $theirs->id,
            ]),
            $this->payload(),
        )->assertNotFound();

        $this->assertSame('Theirs', $theirs->refresh()->name);
    }

    public function test_guests_cannot_register_materials()
    {
        $this->post(
            route('materials.store', ['current_team' => $this->team->slug]),
            $this->payload(),
        )->assertRedirect(route('login'));

        $this->assertSame(0, RawMaterial::count());
    }
}
