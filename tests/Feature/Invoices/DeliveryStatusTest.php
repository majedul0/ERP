<?php

namespace Tests\Feature\Invoices;

use App\Actions\Invoices\UpdateDeliveryStatus;
use App\Enums\DeliveryStatus;
use App\Models\Distributor;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    private Product $product;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = $this->user->currentTeam;
        $this->product = Product::factory()->create([
            'team_id' => $this->team->id,
            'stock_quantity' => 100,
        ]);

        $this->actingAs($this->user)->post(
            route('invoices.store', ['current_team' => $this->team->slug]),
            [
                'sold_at' => now()->toDateString(),
                'distributor_id' => Distributor::factory()->create(['team_id' => $this->team->id])->id,
                'items' => [[
                    'product_id' => $this->product->id,
                    'total_quantity' => 10,
                    'unit_price' => 90,
                ]],
            ],
        );

        $this->invoice = Invoice::firstOrFail();
    }

    private function setStatus(DeliveryStatus $status): void
    {
        $this->actingAs($this->user)->patch(
            route('invoices.status.update', [
                'current_team' => $this->team->slug,
                'invoice' => $this->invoice->id,
            ]),
            ['delivery_status' => $status->value],
        );
    }

    public function test_marking_an_invoice_delivered_does_not_move_stock_again()
    {
        // Pending already holds the stock — the goods are sold, just not yet
        // driven out — so delivering must not deduct a second time.
        $this->assertSame(90, $this->product->fresh()->stock_quantity);

        $this->setStatus(DeliveryStatus::Delivered);

        $this->assertSame(90, $this->product->fresh()->stock_quantity);
        $this->assertSame(
            DeliveryStatus::Delivered,
            $this->invoice->fresh()->delivery_status,
        );
    }

    public function test_cancelling_an_invoice_returns_its_stock()
    {
        $this->setStatus(DeliveryStatus::Cancelled);

        $this->assertSame(100, $this->product->fresh()->stock_quantity);
    }

    public function test_reinstating_a_cancelled_invoice_takes_the_stock_back()
    {
        $this->setStatus(DeliveryStatus::Cancelled);
        $this->setStatus(DeliveryStatus::Delivered);

        $this->assertSame(90, $this->product->fresh()->stock_quantity);
    }

    /**
     * Two people pressing the same button at once must not move stock twice.
     * The action re-reads the status under a row lock, so the second call is
     * a no-op rather than a second return to the shelf.
     */
    public function test_repeating_a_status_change_moves_stock_only_once()
    {
        $action = app(UpdateDeliveryStatus::class);

        $action->handle($this->invoice, DeliveryStatus::Cancelled);
        $action->handle($this->invoice, DeliveryStatus::Cancelled);
        $action->handle($this->invoice, DeliveryStatus::Cancelled);

        $this->assertSame(100, $this->product->fresh()->stock_quantity);
    }

    public function test_an_unknown_status_is_rejected()
    {
        $this->actingAs($this->user)->patch(
            route('invoices.status.update', [
                'current_team' => $this->team->slug,
                'invoice' => $this->invoice->id,
            ]),
            ['delivery_status' => 'teleported'],
        )->assertSessionHasErrors('delivery_status');
    }

    public function test_an_invoice_from_another_company_is_not_reachable()
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(
            route('invoices.show', [
                'current_team' => $outsider->currentTeam->slug,
                'invoice' => $this->invoice->id,
            ]),
        )->assertNotFound();
    }
}
