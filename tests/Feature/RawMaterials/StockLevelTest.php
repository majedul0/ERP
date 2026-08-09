<?php

namespace Tests\Feature\RawMaterials;

use App\Models\RawMaterial;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockLevelTest extends TestCase
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

    private function visit()
    {
        return $this->actingAs($this->user)->get(
            route('stock-levels.index', ['current_team' => $this->team->slug]),
        );
    }

    public function test_low_materials_sort_above_healthy_ones()
    {
        RawMaterial::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Aaa Healthy',
            'stock_quantity' => 900,
            'reorder_level' => 100,
        ]);
        RawMaterial::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Zzz Low',
            'stock_quantity' => 10,
            'reorder_level' => 100,
        ]);

        // Alphabetically "Aaa" comes first; the screen is ordered by urgency.
        $this->visit()
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('company/raw-materials/stock-levels')
                ->where('levels.0.name', 'Zzz Low')
                ->where('levels.0.shortfall', 90)
                ->where('levels.1.name', 'Aaa Healthy')
                ->where('levels.1.shortfall', 0),
            );
    }

    public function test_the_summary_counts_low_and_empty_materials()
    {
        RawMaterial::factory()->create([
            'team_id' => $this->team->id,
            'stock_quantity' => 0,
            'reorder_level' => 50,
            'unit_cost' => 10,
        ]);
        RawMaterial::factory()->create([
            'team_id' => $this->team->id,
            'stock_quantity' => 200,
            'reorder_level' => 50,
            'unit_cost' => 3,
        ]);

        $this->visit()->assertInertia(fn (Assert $page) => $page
            ->where('summary.materialCount', 2)
            ->where('summary.lowCount', 1)
            ->where('summary.outOfStockCount', 1)
            ->where('summary.totalValue', 600),
        );
    }

    public function test_it_shows_only_the_current_companys_materials()
    {
        RawMaterial::factory()->create(['team_id' => $this->team->id, 'name' => 'Ours']);
        RawMaterial::factory()->create(['name' => 'Theirs']);

        $this->visit()->assertInertia(fn (Assert $page) => $page
            ->has('levels', 1)
            ->where('levels.0.name', 'Ours'),
        );
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->get(route('stock-levels.index', ['current_team' => $this->team->slug]))
            ->assertRedirect(route('login'));
    }
}
