<?php

namespace Tests\Feature\Products;

use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductTest extends TestCase
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
            'name' => 'OFC 15gm',
            'sku' => 'OFC-15',
            'carton_size' => 12,
            'distributor_price' => 90,
            'trade_price' => 95,
            'mrp' => 110,
            'stock_quantity' => 500,
            ...$overrides,
        ];
    }

    private function store(array $overrides = [])
    {
        return $this->actingAs($this->user)->post(
            route('products.store', ['current_team' => $this->team->slug]),
            $this->payload($overrides),
        );
    }

    public function test_a_product_is_registered_against_the_current_company()
    {
        $response = $this->store();

        $response->assertRedirect(route('products.index', ['current_team' => $this->team->slug]));

        $product = Product::firstOrFail();

        $this->assertSame($this->team->id, $product->team_id);
        $this->assertSame('OFC 15gm', $product->name);
        $this->assertSame(12, $product->carton_size);
        $this->assertSame(500, $product->stock_quantity);
    }

    public function test_prices_are_stored_as_exact_minor_units()
    {
        $this->store([
            'distributor_price' => 90.55,
            'trade_price' => 95.05,
            'mrp' => 110.99,
        ]);

        $product = Product::firstOrFail();

        // Integers, so a hundred lines of these still add up exactly.
        $this->assertSame(9055, $product->distributor_price);
        $this->assertSame(9505, $product->trade_price);
        $this->assertSame(11099, $product->mrp);
    }

    public function test_a_photo_is_stored_under_the_company_and_named_for_the_sku()
    {
        Storage::fake('public');

        $this->store(['photo' => UploadedFile::fake()->image('whatever i named it.png')]);

        $path = Product::firstOrFail()->photo_path;

        $this->assertSame("products/{$this->team->id}/product-ofc-15-1.png", $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_two_products_photos_do_not_overwrite_each_other()
    {
        Storage::fake('public');

        $this->store(['sku' => 'OFC-15', 'photo' => UploadedFile::fake()->image('a.png')]);
        $this->store(['sku' => 'OBC-15', 'photo' => UploadedFile::fake()->image('b.png')]);

        $paths = Product::pluck('photo_path');

        $this->assertCount(2, $paths->unique());
        foreach ($paths as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_a_sku_cannot_repeat_within_a_company()
    {
        $this->store();

        $this->store()->assertSessionHasErrors('sku');

        $this->assertSame(1, Product::count());
    }

    public function test_two_companies_may_use_the_same_sku()
    {
        $this->store();

        $other = User::factory()->create();

        $this->actingAs($other)->post(
            route('products.store', ['current_team' => $other->currentTeam->slug]),
            $this->payload(),
        )->assertSessionHasNoErrors();

        $this->assertSame(2, Product::count());
    }

    public function test_required_fields_are_enforced()
    {
        $this->actingAs($this->user)->post(
            route('products.store', ['current_team' => $this->team->slug]),
            [],
        )->assertSessionHasErrors([
            'name',
            'sku',
            'carton_size',
            'distributor_price',
            'trade_price',
            'mrp',
            'stock_quantity',
        ]);
    }

    public function test_a_failed_registration_stores_no_photo()
    {
        Storage::fake('public');

        $this->store(['carton_size' => 0, 'photo' => UploadedFile::fake()->image('a.png')]);

        $this->assertSame(0, Product::count());
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_the_list_shows_only_the_current_companys_products()
    {
        Product::factory()->create(['team_id' => $this->team->id, 'name' => 'Ours']);
        Product::factory()->create(['name' => 'Theirs']);

        $response = $this->actingAs($this->user)->get(
            route('products.index', ['current_team' => $this->team->slug]),
        );

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('company/products/index')
            ->has('products', 1)
            ->where('products.0.name', 'Ours'),
        );
    }

    public function test_guests_cannot_register_products()
    {
        $this->post(route('products.store', ['current_team' => $this->team->slug]), $this->payload())
            ->assertRedirect(route('login'));

        $this->assertSame(0, Product::count());
    }
}
