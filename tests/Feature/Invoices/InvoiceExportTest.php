<?php

namespace Tests\Feature\Invoices;

use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = $this->user->currentTeam;
        $this->team->update([
            'name' => 'Ocean Consumer Products',
            'address' => 'Uttara, Dhaka, Bangladesh',
            'phone' => '01712-932814',
        ]);

        $product = Product::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'ZHO 200ml',
            'sku' => 'ZHO-200',
            'distributor_price' => 200,
            'stock_quantity' => 500,
        ]);

        $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            [
                'sold_at' => now()->toDateString(),
                'distributor_id' => Distributor::factory()->create([
                    'team_id' => $this->team->id,
                    'name' => 'Bismillah Treders-Atrai',
                ])->id,
                'items' => [[
                    'product_id' => $product->id,
                    'carton_quantity' => 1,
                    'total_quantity' => 42,
                    'unit_price' => 200,
                ]],
            ],
        );

        $this->invoice = Invoice::firstOrFail();
    }

    private function download(): string
    {
        $response = $this->actingAs($this->user)->get(route('invoices.excel', [
            'current_team' => $this->team->slug,
            'invoice' => $this->invoice->id,
        ]));

        $response->assertOk();

        return $response->streamedContent();
    }

    public function test_the_file_downloads_under_the_invoice_number()
    {
        $response = $this->actingAs($this->user)->get(route('invoices.excel', [
            'current_team' => $this->team->slug,
            'invoice' => $this->invoice->id,
        ]));

        $response->assertOk();
        $response->assertHeader(
            'content-disposition',
            "attachment; filename={$this->invoice->invoice_number}.csv",
        );
    }

    public function test_the_export_contains_the_company_distributor_and_lines()
    {
        $csv = $this->download();

        $this->assertStringContainsString('Ocean Consumer Products', $csv);
        $this->assertStringContainsString('Uttara, Dhaka, Bangladesh', $csv);
        $this->assertStringContainsString('01712-932814', $csv);
        $this->assertStringContainsString($this->invoice->invoice_number, $csv);
        $this->assertStringContainsString('Bismillah Treders-Atrai', $csv);
        $this->assertStringContainsString('ZHO 200ml', $csv);
        $this->assertStringContainsString('ZHO-200', $csv);
    }

    public function test_the_amounts_are_whole_numbers_with_no_decimal_part()
    {
        $csv = $this->download();

        $this->assertStringContainsString('"Total Amount",8400', $csv);
        $this->assertStringNotContainsString('8400.00', $csv);
    }

    /**
     * Excel reads a file as the system codepage unless a BOM says otherwise,
     * which would mangle the currency symbol and any Bangla text.
     */
    public function test_the_file_starts_with_a_utf8_bom()
    {
        $this->assertStringStartsWith("\u{FEFF}", $this->download());
    }

    public function test_another_companys_invoice_cannot_be_exported()
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('invoices.excel', [
            'current_team' => $outsider->currentTeam->slug,
            'invoice' => $this->invoice->id,
        ]))->assertNotFound();
    }

    public function test_guests_cannot_export()
    {
        // setUp() signed a user in to create the invoice; drop that first or
        // this request is not a guest's at all.
        auth()->logout();

        $this->get(route('invoices.excel', [
            'current_team' => $this->team->slug,
            'invoice' => $this->invoice->id,
        ]))->assertRedirect(route('login'));
    }
}
