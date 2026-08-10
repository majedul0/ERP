<?php

namespace Tests\Feature\Payments;

use App\Models\Bank;
use App\Models\Distributor;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Date-only columns must reach React as `YYYY-MM-DD`.
 *
 * A payment is booked on a day, not at a moment. Sending a timestamp put a raw
 * `2026-08-09T00:00:00+00:00` on screen, and — worse — invited the browser to
 * shift a date that has no time in it across a timezone boundary.
 */
class DatePresentationTest extends TestCase
{
    use RefreshDatabase;

    private const DATE_ONLY = '/^\d{4}-\d{2}-\d{2}$/';

    private User $user;

    private Team $team;

    private Distributor $distributor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = $this->user->currentTeam;
        $this->distributor = Distributor::factory()->create(['team_id' => $this->team->id]);

        Payment::create([
            'team_id' => $this->team->id,
            'distributor_id' => $this->distributor->id,
            'bank_id' => Bank::factory()->create(['team_id' => $this->team->id])->id,
            'created_by' => $this->user->id,
            'amount' => 5000,
            'paid_on' => '2026-08-09',
        ]);
    }

    public function test_the_payments_list_sends_a_plain_date()
    {
        $this->actingAs($this->user)
            ->get(route('payments.index', ['current_team' => $this->team->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('payments.0.paidOn', '2026-08-09'),
            );
    }

    public function test_the_statement_sends_plain_dates()
    {
        $this->actingAs($this->user)
            ->get(route('distributors.show', [
                'current_team' => $this->team->slug,
                'distributor' => $this->distributor->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('statement.0.occurredOn', fn (string $date) => (bool) preg_match(
                    self::DATE_ONLY,
                    $date,
                )),
            );
    }
}
