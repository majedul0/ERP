<?php

namespace Tests\Feature\Distributors;

use App\Models\Bank;
use App\Models\Distributor;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManageDistributorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    private Distributor $distributor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = $this->user->currentTeam;
        $this->distributor = Distributor::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Majedul Islam',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Majedul Islam',
            'proprietor_name' => 'Sahik',
            'phone' => '01560023153',
            'address' => 'KA-148/2/c Noyanogor',
            'thana' => 'Khilkhet',
            'district' => 'Dhaka',
            'division' => 'Dhaka',
            ...$overrides,
        ];
    }

    private function update(array $overrides = [], ?Distributor $distributor = null)
    {
        return $this->actingAs($this->user)->put(
            route('distributors.update', [
                'current_team' => $this->team->slug,
                'distributor' => ($distributor ?? $this->distributor)->id,
            ]),
            $this->payload($overrides),
        );
    }

    private function destroy(?Distributor $distributor = null)
    {
        return $this->actingAs($this->user)->delete(
            route('distributors.destroy', [
                'current_team' => $this->team->slug,
                'distributor' => ($distributor ?? $this->distributor)->id,
            ]),
        );
    }

    public function test_the_edit_screen_loads_the_distributor()
    {
        $this->actingAs($this->user)
            ->get(route('distributors.edit', [
                'current_team' => $this->team->slug,
                'distributor' => $this->distributor->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('company/distributors/edit')
                ->where('distributor.name', 'Majedul Islam'),
            );
    }

    public function test_contact_details_can_be_changed()
    {
        $this->update(['name' => 'Majedul Traders', 'phone' => '01700000000'])
            ->assertRedirect(route('distributors.show', [
                'current_team' => $this->team->slug,
                'distributor' => $this->distributor->id,
            ]));

        $this->distributor->refresh();

        $this->assertSame('Majedul Traders', $this->distributor->name);
        $this->assertSame('01700000000', $this->distributor->phone);
    }

    /**
     * The balance is the result of replaying invoices and payments. A figure
     * typed over it would disagree with the statement on the next screen.
     */
    public function test_the_balance_cannot_be_edited_through_the_form()
    {
        $this->distributor->update(['balance' => 40000]);

        $this->update(['balance' => 0]);

        $this->assertSame(40000, $this->distributor->refresh()->balance);
    }

    public function test_a_name_is_still_required()
    {
        $this->update(['name' => ''])->assertSessionHasErrors('name');

        $this->assertSame('Majedul Islam', $this->distributor->refresh()->name);
    }

    public function test_a_distributor_with_no_history_can_be_deleted()
    {
        $this->destroy()->assertRedirect(
            route('distributors.index', ['current_team' => $this->team->slug]),
        );

        $this->assertSoftDeleted($this->distributor);
    }

    public function test_a_distributor_with_invoices_cannot_be_deleted()
    {
        $this->distributor->invoices()->create([
            'team_id' => $this->team->id,
            'created_by' => $this->user->id,
            'invoice_number' => 'INV1',
            'sequence_number' => 1,
            'sold_at' => now(),
            'delivery_status' => 'pending',
            'invoice_total' => 100,
            'discount_total' => 0,
            'scheme_amount' => 0,
            'previous_dues' => 0,
            'total_amount' => 100,
        ]);

        $this->destroy()->assertSessionHasErrors('distributor');

        $this->assertNotSoftDeleted($this->distributor);
    }

    public function test_a_distributor_with_payments_cannot_be_deleted()
    {
        Payment::create([
            'team_id' => $this->team->id,
            'distributor_id' => $this->distributor->id,
            'bank_id' => Bank::factory()->create(['team_id' => $this->team->id])->id,
            'created_by' => $this->user->id,
            'amount' => 5000,
            'paid_on' => now()->toDateString(),
        ]);

        $this->destroy()->assertSessionHasErrors('distributor');

        $this->assertNotSoftDeleted($this->distributor);
    }

    public function test_another_companys_distributor_cannot_be_edited_or_deleted()
    {
        $theirs = Distributor::factory()->create(['name' => 'Theirs']);

        $this->update([], $theirs)->assertNotFound();
        $this->destroy($theirs)->assertNotFound();

        $this->assertSame('Theirs', $theirs->refresh()->name);
        $this->assertNotSoftDeleted($theirs);
    }

    public function test_guests_cannot_edit_or_delete()
    {
        $this->put(
            route('distributors.update', [
                'current_team' => $this->team->slug,
                'distributor' => $this->distributor->id,
            ]),
            $this->payload(['name' => 'Hacked']),
        )->assertRedirect(route('login'));

        $this->assertSame('Majedul Islam', $this->distributor->refresh()->name);
    }
}
