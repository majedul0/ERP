<?php

namespace Tests\Feature\Invoices;

use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Support\InvoiceNumbers;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceNumberingTest extends TestCase
{
    use RefreshDatabase;

    public function test_numbers_are_allocated_in_sequence_and_never_repeat()
    {
        $team = Team::factory()->create();

        $allocated = collect(range(1, 50))
            ->map(fn () => InvoiceNumbers::next($team))
            ->pluck('sequence');

        $this->assertSame(50, $allocated->unique()->count());
        $this->assertSame(range(1, 50), $allocated->all());
    }

    public function test_each_company_has_its_own_series()
    {
        $first = Team::factory()->create();
        $second = Team::factory()->create();

        InvoiceNumbers::next($first);
        InvoiceNumbers::next($first);

        $this->assertSame(1, InvoiceNumbers::next($second)['sequence']);
        $this->assertSame(3, InvoiceNumbers::next($first)['sequence']);
    }

    public function test_the_number_carries_the_configured_prefix()
    {
        config(['company.invoices.number_prefix' => 'OCP-']);

        $this->assertSame('OCP-1', InvoiceNumbers::next(Team::factory()->create())['number']);
    }

    public function test_a_company_can_continue_an_existing_series()
    {
        config(['company.invoices.starting_number' => 2574]);

        $this->assertSame('INV2574', InvoiceNumbers::next(Team::factory()->create())['number']);
    }

    public function test_the_preview_does_not_consume_a_number()
    {
        $team = Team::factory()->create();

        $this->assertSame('INV1', InvoiceNumbers::preview($team));
        $this->assertSame('INV1', InvoiceNumbers::preview($team));
        $this->assertSame(1, InvoiceNumbers::next($team)['sequence']);
    }

    /**
     * The layer of last resort: even if the lock and the row lock were both
     * bypassed, the database refuses two invoices with one number.
     */
    public function test_the_database_refuses_a_duplicate_number_within_a_company()
    {
        $team = Team::factory()->create();
        $distributor = Distributor::factory()->create(['team_id' => $team->id]);

        $attributes = [
            'team_id' => $team->id,
            'distributor_id' => $distributor->id,
            'invoice_number' => 'INV1',
            'sequence_number' => 1,
            'sold_at' => now(),
        ];

        Invoice::create($attributes);

        $this->expectException(QueryException::class);

        Invoice::create($attributes);
    }

    public function test_two_companies_may_hold_the_same_number()
    {
        $first = Team::factory()->create();
        $second = Team::factory()->create();

        foreach ([$first, $second] as $team) {
            Invoice::create([
                'team_id' => $team->id,
                'distributor_id' => Distributor::factory()->create(['team_id' => $team->id])->id,
                'invoice_number' => 'INV1',
                'sequence_number' => 1,
                'sold_at' => now(),
            ]);
        }

        $this->assertSame(2, Invoice::where('invoice_number', 'INV1')->count());
    }

    /**
     * The number is allocated inside the invoice's transaction, so an invoice
     * that fails hands its number back instead of leaving a hole in the books.
     */
    public function test_a_rejected_invoice_does_not_burn_its_number()
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;
        $distributor = Distributor::factory()->create(['team_id' => $team->id]);
        $product = Product::factory()->create(['team_id' => $team->id, 'stock_quantity' => 1]);
        $url = route('invoices.store', ['current_team' => $team->slug]);

        $this->actingAs($user)->post($url, [
            'sold_at' => now()->toDateString(),
            'distributor_id' => $distributor->id,
            'items' => [['product_id' => $product->id, 'total_quantity' => 99]],
        ]);

        $this->assertSame(0, Invoice::count());

        $this->actingAs($user)->post($url, [
            'sold_at' => now()->toDateString(),
            'distributor_id' => $distributor->id,
            'items' => [['product_id' => $product->id, 'total_quantity' => 1]],
        ]);

        $this->assertSame('INV1', Invoice::firstOrFail()->invoice_number);
    }
}
