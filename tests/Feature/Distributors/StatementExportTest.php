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

class StatementExportTest extends TestCase
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

        Payment::create([
            'team_id' => $this->team->id,
            'distributor_id' => $this->distributor->id,
            'bank_id' => Bank::factory()->create(['team_id' => $this->team->id])->id,
            'created_by' => $this->user->id,
            'amount' => 90_000,
            'paid_on' => '2026-08-09',
        ]);
    }

    private function csv(): string
    {
        $response = $this->actingAs($this->user)->get(route('statements.excel', [
            'current_team' => $this->team->slug,
            'distributor' => $this->distributor->id,
        ]));

        $response->assertOk();

        return $response->streamedContent();
    }

    public function test_the_printable_statement_renders()
    {
        $this->actingAs($this->user)
            ->get(route('statements.print', [
                'current_team' => $this->team->slug,
                'distributor' => $this->distributor->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('company/distributors/statement')
                ->where('distributor.name', 'Majedul Islam')
                ->has('statement', 1)
                ->where('totals.paid', 90_000),
            );
    }

    public function test_the_excel_export_carries_the_account()
    {
        $csv = $this->csv();

        $this->assertStringContainsString('Statement of Account', $csv);
        $this->assertStringContainsString('Majedul Islam', $csv);
        $this->assertStringContainsString('Payment received', $csv);
        $this->assertStringContainsString('90000', $csv);
    }

    /**
     * Excel reads a file as the system codepage without this, which mangles ৳
     * and any Bangla text.
     */
    public function test_the_export_starts_with_a_utf8_bom()
    {
        $this->assertStringStartsWith("\u{FEFF}", $this->csv());
    }

    public function test_another_companys_statement_is_not_reachable()
    {
        $theirs = Distributor::factory()->create();

        $this->actingAs($this->user)->get(route('statements.print', [
            'current_team' => $this->team->slug,
            'distributor' => $theirs->id,
        ]))->assertNotFound();

        $this->actingAs($this->user)->get(route('statements.excel', [
            'current_team' => $this->team->slug,
            'distributor' => $theirs->id,
        ]))->assertNotFound();
    }

    public function test_guests_cannot_download_a_statement()
    {
        $this->get(route('statements.excel', [
            'current_team' => $this->team->slug,
            'distributor' => $this->distributor->id,
        ]))->assertRedirect(route('login'));
    }
}
