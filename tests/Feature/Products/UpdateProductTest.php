<?php

namespace Tests\Feature\Products;

use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UpdateProductTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = $this->user->currentTeam;
        $this->product = Product::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'OFC 15gm',
            'sku' => 'OFC-15',
            'carton_size' => 12,
            'distributor_price' => 90,
            'trade_price' => 95,
            'mrp' => 110,
            'stock_quantity' => 500,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function update(array $overrides = [], ?Product $product = null)
    {
        $product ??= $this->product;

        return $this->actingAs($this->user)->put(
            route('products.update', [
                'current_team' => $this->team->slug,
                'product' => $product->id,
            ]),
            [
                'name' => $product->name,
                'sku' => $product->sku,
                'carton_size' => $product->carton_size,
                'distributor_price' => $product->distributor_price,
                'trade_price' => $product->trade_price,
                'mrp' => $product->mrp,
                'stock_quantity' => $product->stock_quantity,
                ...$overrides,
            ],
        );
    }

    public function test_price_and_stock_can_be_changed()
    {
        $response = $this->update([
            'distributor_price' => 120,
            'trade_price' => 125,
            'mrp' => 140,
            'stock_quantity' => 480,
        ]);

        $response->assertRedirect(route('products.index', [
            'current_team' => $this->team->slug,
        ]));

        $product = $this->product->fresh();

        $this->assertSame(120, $product->distributor_price);
        $this->assertSame(125, $product->trade_price);
        $this->assertSame(140, $product->mrp);
        $this->assertSame(480, $product->stock_quantity);
    }

    public function test_the_name_and_carton_size_can_be_changed()
    {
        $this->update(['name' => 'OFC 15gm (new pack)', 'carton_size' => 24]);

        $this->assertSame('OFC 15gm (new pack)', $this->product->fresh()->name);
        $this->assertSame(24, $this->product->fresh()->carton_size);
    }

    /**
     * Invoice lines copy the name and price at the moment of sale, so
     * repricing changes what the next invoice costs and leaves every invoice
     * already printed exactly as it was.
     */
    public function test_repricing_does_not_rewrite_invoices_already_issued()
    {
        $distributor = Distributor::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            [
                'sold_at' => now()->toDateString(),
                'distributor_id' => $distributor->id,
                'items' => [[
                    'product_id' => $this->product->id,
                    'total_quantity' => 10,
                    'unit_price' => 90,
                ]],
            ],
        );

        $invoice = Invoice::firstOrFail();

        $this->update([
            'name' => 'OFC 15gm (discontinued)',
            'distributor_price' => 500,
        ]);

        $item = $invoice->fresh()->items->first();

        $this->assertSame('OFC 15gm', $item->product_name);
        $this->assertSame(90, $item->unit_price);
        $this->assertSame(900, $item->amount);
        $this->assertSame(900, $invoice->fresh()->invoice_total);
        $this->assertSame(900, $distributor->fresh()->balance);
    }

    public function test_stock_is_set_to_the_count_given_not_added_to_it()
    {
        $this->update(['stock_quantity' => 12]);

        $this->assertSame(12, $this->product->fresh()->stock_quantity);
    }

    public function test_the_sku_may_stay_the_same()
    {
        $this->update(['distributor_price' => 99])->assertSessionHasNoErrors();

        $this->assertSame('OFC-15', $this->product->fresh()->sku);
    }

    public function test_the_sku_cannot_take_another_products_sku()
    {
        Product::factory()->create([
            'team_id' => $this->team->id,
            'sku' => 'OBC-15',
        ]);

        $this->update(['sku' => 'OBC-15'])->assertSessionHasErrors('sku');

        $this->assertSame('OFC-15', $this->product->fresh()->sku);
    }

    public function test_a_fractional_price_is_refused()
    {
        $this->update(['distributor_price' => 90.5])
            ->assertSessionHasErrors('distributor_price');

        $this->assertSame(90, $this->product->fresh()->distributor_price);
    }

    public function test_a_new_photo_replaces_the_old_one_at_a_new_name()
    {
        Storage::fake('public');

        $this->update(['photo' => UploadedFile::fake()->image('first.png')]);

        $first = $this->product->fresh()->photo_path;
        $this->assertSame("products/{$this->team->id}/product-ofc-15-1.png", $first);

        $this->update(['photo' => UploadedFile::fake()->image('second.png')]);

        $second = $this->product->fresh()->photo_path;
        $this->assertSame("products/{$this->team->id}/product-ofc-15-2.png", $second);

        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_leaving_the_photo_empty_keeps_the_current_one()
    {
        Storage::fake('public');

        $this->update(['photo' => UploadedFile::fake()->image('a.png')]);
        $path = $this->product->fresh()->photo_path;

        $this->update(['distributor_price' => 99]);

        $this->assertSame($path, $this->product->fresh()->photo_path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_the_edit_screen_is_prefilled()
    {
        $response = $this->actingAs($this->user)->get(route('products.edit', [
            'current_team' => $this->team->slug,
            'product' => $this->product->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('company/products/edit')
            ->where('product.name', 'OFC 15gm')
            ->where('product.sku', 'OFC-15')
            ->where('product.distributorPrice', 90)
            ->where('product.stockQuantity', 500),
        );
    }

    public function test_another_companys_product_cannot_be_edited()
    {
        $outsider = User::factory()->create();
        $theirs = Product::factory()->create([
            'team_id' => $outsider->currentTeam->id,
        ]);

        $this->actingAs($outsider)->put(
            route('products.update', [
                'current_team' => $outsider->currentTeam->slug,
                'product' => $this->product->id,
            ]),
            [
                'name' => 'Hijacked',
                'sku' => $theirs->sku.'-x',
                'carton_size' => 1,
                'distributor_price' => 1,
                'trade_price' => 1,
                'mrp' => 1,
                'stock_quantity' => 0,
            ],
        )->assertNotFound();

        $this->assertSame('OFC 15gm', $this->product->fresh()->name);
    }
}
