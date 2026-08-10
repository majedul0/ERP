<?php

namespace Tests\Feature\Invoices;

use App\Enums\DeliveryStatus;
use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteInvoiceTest extends TestCase
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
        $this->distributor = Distributor::factory()->create(['team_id' => $this->team->id]);
        $this->product = Product::factory()->create([
            'team_id' => $this->team->id,
            'distributor_price' => 100,
            'stock_quantity' => 500,
        ]);
    }

    private function sell(int $quantity = 10): Invoice
    {
        $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            [
                'distributor_id' => $this->distributor->id,
                'sold_at' => now()->toDateString(),
                'items' => [[
                    'product_id' => $this->product->id,
                    'total_quantity' => $quantity,
                    'unit_price' => 100,
                ]],
            ],
        )->assertSessionHasNoErrors();

        return Invoice::orderByDesc('id')->firstOrFail();
    }

    private function deleteInvoice(Invoice $invoice)
    {
        return $this->actingAs($this->user)->delete(route('invoices.destroy', [
            'current_team' => $this->team->slug,
            'invoice' => $invoice->id,
        ]));
    }

    public function test_a_duplicate_invoice_can_be_deleted()
    {
        $invoice = $this->sell();

        $this->deleteInvoice($invoice)->assertRedirect(
            route('invoices.index', ['current_team' => $this->team->slug]),
        );

        $this->assertSoftDeleted($invoice);
    }

    public function test_deleting_returns_the_stock()
    {
        $invoice = $this->sell(10);

        $this->assertSame(490, $this->product->refresh()->stock_quantity);

        $this->deleteInvoice($invoice);

        $this->assertSame(500, $this->product->refresh()->stock_quantity);
    }

    public function test_deleting_clears_the_debt()
    {
        $invoice = $this->sell(10);

        $this->assertSame(1000, $this->distributor->refresh()->balance);

        $this->deleteInvoice($invoice);

        $this->assertSame(0, $this->distributor->refresh()->balance);
    }

    /**
     * The stock was already back on the shelf when it was cancelled; deleting
     * must not hand it over a second time.
     */
    public function test_deleting_an_already_cancelled_invoice_returns_the_stock_once()
    {
        $invoice = $this->sell(10);

        $this->actingAs($this->user)->patch(
            route('invoices.status.update', [
                'current_team' => $this->team->slug,
                'invoice' => $invoice->id,
            ]),
            ['delivery_status' => DeliveryStatus::Cancelled->value],
        );

        $this->assertSame(500, $this->product->refresh()->stock_quantity);

        $this->deleteInvoice($invoice);

        $this->assertSame(500, $this->product->refresh()->stock_quantity);
    }

    /**
     * A number that reached a customer must never name two documents.
     */
    public function test_the_invoice_number_is_retired_not_reused()
    {
        $first = $this->sell();
        $this->deleteInvoice($first);

        $next = $this->sell();

        $this->assertNotSame($first->invoice_number, $next->invoice_number);
        $this->assertSame('INV2', $next->invoice_number);
    }

    public function test_the_statement_reflows_after_a_deletion()
    {
        $this->sell(10);
        $second = $this->sell(5);
        $third = $this->sell(2);

        // 1000 + 500 + 200 owed, less the 500 invoice.
        $this->deleteInvoice($second);

        $this->assertSame(1200, $this->distributor->refresh()->balance);
        $this->assertSame(1000, $third->refresh()->previous_dues);
    }

    public function test_a_deleted_invoice_is_gone_from_the_list_and_the_screen()
    {
        $invoice = $this->sell();
        $this->deleteInvoice($invoice);

        $this->actingAs($this->user)->get(route('invoices.show', [
            'current_team' => $this->team->slug,
            'invoice' => $invoice->id,
        ]))->assertNotFound();
    }

    public function test_another_companys_invoice_cannot_be_deleted()
    {
        // Invoice has no factory — it is only ever created through the action,
        // which is the point. Built by hand here so the row belongs to a team
        // this user is not in.
        $other = User::factory()->create();
        $otherTeam = $other->currentTeam;

        $theirs = Invoice::create([
            'team_id' => $otherTeam->id,
            'distributor_id' => Distributor::factory()->create(['team_id' => $otherTeam->id])->id,
            'created_by' => $other->id,
            'invoice_number' => 'INV1',
            'sequence_number' => 1,
            'sold_at' => now(),
            'delivery_status' => DeliveryStatus::Pending,
            'invoice_total' => 100,
            'discount_total' => 0,
            'scheme_amount' => 0,
            'previous_dues' => 0,
            'total_amount' => 100,
        ]);

        $this->deleteInvoice($theirs)->assertNotFound();

        $this->assertNotSoftDeleted($theirs);
    }

    public function test_guests_cannot_delete_invoices()
    {
        $invoice = $this->sell();

        $this->post('/logout');

        $this->delete(route('invoices.destroy', [
            'current_team' => $this->team->slug,
            'invoice' => $invoice->id,
        ]))->assertRedirect(route('login'));

        $this->assertNotSoftDeleted($invoice);
    }
}
