<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A guard against the query counts quietly growing with the data.
 *
 * These are **counts, not timings** — the suite runs on in-memory sqlite, so a
 * millisecond here says nothing about Postgres. What it does catch is the thing
 * that actually makes a page slow: a query per row. A screen that costs a fixed
 * handful of queries at 20 invoices and the same handful at 400 will stay fast;
 * one that grows will not, however fast the database is.
 *
 * The ceilings are deliberately loose. They exist to fail when a page starts
 * querying per row, not to police an exact number.
 */
class PerformanceProbeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    /** @var array<string, int> */
    private array $measured = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = $this->user->currentTeam;

        $this->seedVolume();
    }

    /**
     * Enough rows that a per-row query is unmistakable.
     */
    private function seedVolume(): void
    {
        $products = Product::factory()->count(40)->create(['team_id' => $this->team->id]);
        $distributors = Distributor::factory()->count(30)->create(['team_id' => $this->team->id]);

        $rows = [];
        $items = [];

        foreach (range(1, 200) as $number) {
            $distributor = $distributors[$number % 30];

            $rows[] = [
                'team_id' => $this->team->id,
                'distributor_id' => $distributor->id,
                'created_by' => $this->user->id,
                'invoice_number' => "INV{$number}",
                'sequence_number' => $number,
                'sold_at' => now()->subDays($number % 60),
                'delivery_status' => 'delivered',
                'invoice_total' => 1000,
                'discount_total' => 0,
                'scheme_amount' => 0,
                'previous_dues' => 0,
                'total_amount' => 1000,
                'hide_previous_dues' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Invoice::insert($rows);

        foreach (Invoice::pluck('id') as $index => $invoiceId) {
            foreach (range(0, 2) as $line) {
                $product = $products[($index + $line) % 40];

                $items[] = [
                    'invoice_id' => $invoiceId,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'line_number' => $line + 1,
                    'carton_quantity' => 1,
                    'total_quantity' => 10,
                    'unit_price' => 100,
                    'amount' => 1000,
                    'discount' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        InvoiceItem::insert($items);

        Payment::factory()->count(60)->create([
            'team_id' => $this->team->id,
            'distributor_id' => $distributors->first()->id,
            'created_by' => $this->user->id,
        ]);

        Expense::factory()->count(40)->create([
            'team_id' => $this->team->id,
            'created_by' => $this->user->id,
        ]);

        Vendor::factory()->count(20)->create(['team_id' => $this->team->id]);
    }

    /**
     * Run a request and report how many queries it took.
     */
    private function queriesFor(string $label, string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->user)->get($url)->assertOk();

        $count = count(DB::getRawQueryLog());

        DB::disableQueryLog();

        $this->measured[$label] = $count;

        return $count;
    }

    private function url(string $path): string
    {
        return "/{$this->team->slug}/{$path}";
    }

    public function test_the_main_screens_cost_a_fixed_number_of_queries()
    {
        $budgets = [
            'dashboard' => [$this->url('dashboard'), 25],
            'invoices' => [$this->url('sales/invoices'), 25],
            'products' => [$this->url('products'), 20],
            'distributors' => [$this->url('distributors'), 20],
            'payments' => [$this->url('finance/payments'), 20],
            'expenses' => [$this->url('finance/expenses'), 20],
            'vendors' => [$this->url('vendors'), 20],
            'materials' => [$this->url('raw-materials'), 20],
            'report' => [$this->url('finance/reports'), 30],
        ];

        foreach ($budgets as $label => [$url, $budget]) {
            $count = $this->queriesFor($label, $url);

            $this->assertLessThanOrEqual(
                $budget,
                $count,
                "{$label} took {$count} queries with 200 invoices — that looks like a query per row.",
            );
        }

        // Printed so a human can see the shape, not just the pass.
        fwrite(STDERR, PHP_EOL.'  query counts: '.json_encode($this->measured).PHP_EOL);
    }

    /**
     * The statement walks every invoice and payment for one distributor. It
     * must stay a couple of queries however long the account is.
     */
    public function test_a_distributor_statement_does_not_query_per_line()
    {
        $distributor = Distributor::where('team_id', $this->team->id)->first();

        $count = $this->queriesFor(
            'statement',
            $this->url("distributors/{$distributor->id}"),
        );

        $this->assertLessThanOrEqual(20, $count, "The statement took {$count} queries.");
    }

    /**
     * One invoice with its lines, distributor and settling payments.
     */
    public function test_an_invoice_screen_does_not_query_per_line()
    {
        $invoice = Invoice::where('team_id', $this->team->id)->first();

        $count = $this->queriesFor('invoice', $this->url("sales/invoices/{$invoice->id}"));

        $this->assertLessThanOrEqual(20, $count, "The invoice screen took {$count} queries.");
    }

    /**
     * The platform panel counts everything for every company. This is the one
     * screen whose cost grows with customers rather than with their data, so it
     * is worth knowing the shape before there are a hundred of them.
     */
    public function test_the_platform_panel_cost_is_known()
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();

        Team::factory()->count(9)->create();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($admin)->get(route('platform.index'))->assertOk();

        $count = count(DB::getRawQueryLog());
        DB::disableQueryLog();

        fwrite(STDERR, "  platform panel with 11 companies: {$count} queries".PHP_EOL);

        // ~10 counts per company by design. Fine for a panel one person opens;
        // it would need rewriting as grouped aggregates in the hundreds.
        $this->assertLessThanOrEqual(160, $count);
    }
}
