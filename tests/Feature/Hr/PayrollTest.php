<?php

namespace Tests\Feature\Hr;

use App\Enums\PayrollRunStatus;
use App\Enums\SalaryPaymentKind;
use App\Enums\SalaryType;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\EmployeeSalaryRate;
use App\Models\Expense;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\SalaryPayment;
use App\Models\Team;
use App\Models\User;
use App\Support\EmployeeLedger;
use App\Support\FinancialReport;
use App\Support\PayrollCalculator;
use App\Support\WorkingCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = $this->user->currentTeam;

        $this->employee = Employee::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Rahim Uddin',
            'salary_type' => SalaryType::Monthly,
            'joined_on' => '2025-01-01',
        ]);

        $this->rate($this->employee, 20_000);
    }

    private function rate(Employee $employee, int $amount, string $from = '2025-01-01', SalaryType $type = SalaryType::Monthly): EmployeeSalaryRate
    {
        return EmployeeSalaryRate::create([
            'team_id' => $this->team->id,
            'employee_id' => $employee->id,
            'salary_type' => $type,
            'amount' => $amount,
            'effective_from' => $from,
        ]);
    }

    /**
     * @param  list<array{employee_id: int, day: int, status: string|null}>  $marks
     */
    private function mark(array $marks, string $month = '2026-08'): void
    {
        $this->actingAs($this->user)->put(
            route('attendance.update', ['current_team' => $this->team->slug]),
            ['month' => $month, 'marks' => $marks],
        )->assertSessionHasNoErrors();
    }

    private function openRun(string $month = '2026-08'): PayrollRun
    {
        $this->actingAs($this->user)->post(
            route('payroll.open', ['current_team' => $this->team->slug]),
            ['month' => $month],
        )->assertSessionHasNoErrors();

        return PayrollRun::latest('id')->firstOrFail();
    }

    private function approve(PayrollRun $run): TestResponse
    {
        return $this->actingAs($this->user)->post(route('payroll.approve', [
            'current_team' => $this->team->slug,
            'run' => $run->id,
        ]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function pay(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->user)->post(
            route('salary-payments.store', ['current_team' => $this->team->slug]),
            array_merge([
                'employee_id' => $this->employee->id,
                'kind' => SalaryPaymentKind::Salary->value,
                'paid_on' => '2026-08-31',
                'amount' => 20_000,
            ], $overrides),
        );
    }

    private function lineFor(PayrollRun $run, ?Employee $employee = null): PayrollLine
    {
        return $run->lines()
            ->where('employee_id', ($employee ?? $this->employee)->id)
            ->firstOrFail();
    }

    /**
     * A full month pays exactly the salary — in any month length, with nothing
     * to round.
     */
    public function test_a_full_month_pays_exactly_the_salary()
    {
        $run = $this->openRun();
        $line = $this->lineFor($run);

        $this->assertSame(20_000, $line->gross_earned);
        $this->assertSame(20_000, $line->net_payable);
        // Thirty days, always — not the working days August happens to have.
        $this->assertSame(60, $line->unit_total);
        $this->assertSame(60, $line->unit_payable);
    }

    /**
     * A month is thirty days whatever the calendar says, so the same day off
     * costs the same in February as in August.
     */
    public function test_a_day_off_costs_a_thirtieth_in_every_month()
    {
        $this->mark(
            [['employee_id' => $this->employee->id, 'day' => 3, 'status' => 'absent']],
            '2026-08',
        );

        $this->mark(
            [['employee_id' => $this->employee->id, 'day' => 3, 'status' => 'absent']],
            '2026-02',
        );

        $august = $this->lineFor($this->openRun('2026-08'));
        $february = $this->lineFor($this->openRun('2026-02'));

        $expected = intdiv(20_000 * 58, 60);

        $this->assertSame($expected, $august->gross_earned);
        $this->assertSame($expected, $february->gross_earned);
    }

    /**
     * February is a full month's work, even though it is not thirty days long.
     */
    public function test_a_short_month_still_pays_the_full_salary()
    {
        $line = $this->lineFor($this->openRun('2026-02'));

        $this->assertSame(20_000, $line->gross_earned);
    }

    /**
     * The bug this rule was written for.
     *
     * On the 5th of a running month, twenty-five days have not happened yet.
     * A salary built up from attendance would read as a sixth of itself and
     * look like a pay cut; a salary reduced by what was missed reads as the
     * whole thing, because nothing has been missed.
     */
    public function test_a_running_month_shows_the_whole_salary_not_the_days_so_far()
    {
        $this->mark([
            ['employee_id' => $this->employee->id, 'day' => 1, 'status' => 'present'],
            ['employee_id' => $this->employee->id, 'day' => 2, 'status' => 'present'],
            ['employee_id' => $this->employee->id, 'day' => 3, 'status' => 'present'],
            ['employee_id' => $this->employee->id, 'day' => 4, 'status' => 'present'],
            ['employee_id' => $this->employee->id, 'day' => 5, 'status' => 'present'],
        ]);

        $line = $this->lineFor($this->openRun());

        $this->assertSame(20_000, $line->gross_earned);
    }

    /**
     * A mid-month joiner is paid from the day they started, not for the month.
     */
    public function test_a_mid_month_joiner_is_paid_pro_rata()
    {
        $joiner = Employee::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'New Start',
            'joined_on' => '2026-08-16',
        ]);

        $this->rate($joiner, 30_000, '2026-08-16');

        $line = $this->lineFor($this->openRun(), $joiner);

        // Fifteen days not employed, out of thirty.
        $this->assertSame(intdiv(30_000 * 30, 60), $line->gross_earned);
    }

    /**
     * The row has to add up on paper: what was earned plus what was docked is
     * the whole salary, even where the division truncated.
     */
    public function test_the_payslip_adds_up_on_a_partial_month()
    {
        $this->mark([
            ['employee_id' => $this->employee->id, 'day' => 3, 'status' => 'absent'],
            ['employee_id' => $this->employee->id, 'day' => 4, 'status' => 'absent'],
            ['employee_id' => $this->employee->id, 'day' => 5, 'status' => 'half_day'],
        ]);

        $run = $this->openRun();
        $line = $this->lineFor($run);

        $absence = $line->rate_applied - $line->gross_earned;

        $this->assertSame($line->rate_applied, $line->gross_earned + $absence);
        // Two days and a half missed out of thirty: 55 half-days of 60.
        $this->assertSame(intdiv(20_000 * 55, 60), $line->gross_earned);
        $this->assertSame(2, $line->absent_days);
        $this->assertSame(1, $line->half_days);
    }

    /**
     * Salaried staff are marked by exception: an unmarked working day is a day
     * worked, or an office of thirty could never be paid.
     */
    public function test_an_unmarked_day_pays_a_monthly_employee()
    {
        $run = $this->openRun();

        // Nothing was marked at all.
        $this->assertSame(20_000, $this->lineFor($run)->gross_earned);
    }

    /**
     * A daily wage is the opposite: no record of the day, no day's work to pay.
     */
    public function test_an_unmarked_day_pays_a_daily_wage_employee_nothing()
    {
        $daily = Employee::factory()->dailyWage()->create([
            'team_id' => $this->team->id,
            'joined_on' => '2025-01-01',
        ]);

        $this->rate($daily, 500, '2025-01-01', SalaryType::Daily);

        $run = $this->openRun();

        $this->assertSame(0, $this->lineFor($run, $daily)->gross_earned);
    }

    /**
     * The whole difference between the two bases, in one test.
     */
    public function test_a_friday_pays_a_daily_worker_but_not_a_monthly_one()
    {
        $daily = Employee::factory()->dailyWage()->create([
            'team_id' => $this->team->id,
            'joined_on' => '2025-01-01',
        ]);

        $this->rate($daily, 500, '2025-01-01', SalaryType::Daily);

        // 2026-08-07 is a Friday — a weekend day by default.
        $calendar = WorkingCalendar::forMonth($this->team, Carbon::create(2026, 8, 1));
        $this->assertTrue($calendar->isWeekend(Carbon::create(2026, 8, 7)));

        $this->mark([
            ['employee_id' => $daily->id, 'day' => 7, 'status' => 'present'],
            ['employee_id' => $this->employee->id, 'day' => 7, 'status' => 'present'],
        ]);

        $run = $this->openRun();

        // The daily worker earns the day.
        $this->assertSame(500, $this->lineFor($run, $daily)->gross_earned);

        // The monthly one is not paid twice for a day their salary already
        // contains — overtime is what records extra hours.
        $this->assertSame(20_000, $this->lineFor($run)->gross_earned);
        $this->assertSame(60, $this->lineFor($run)->unit_total);
    }

    public function test_a_raise_dated_after_the_month_does_not_change_it()
    {
        $this->rate($this->employee, 30_000, '2026-09-01');

        $run = $this->openRun('2026-08');

        $this->assertSame(20_000, $this->lineFor($run)->rate_applied);

        $september = $this->openRun('2026-09');

        $this->assertSame(30_000, $this->lineFor($september)->rate_applied);
    }

    public function test_somebody_who_left_before_the_month_is_not_on_the_run()
    {
        Employee::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Gone',
            'joined_on' => '2025-01-01',
            'left_on' => '2026-07-20',
        ]);

        $run = $this->openRun();

        $this->assertSame(1, $run->lines()->count());
    }

    public function test_overtime_is_hours_times_rate()
    {
        $run = $this->openRun();

        $this->actingAs($this->user)->put(
            route('payroll.update', ['current_team' => $this->team->slug, 'run' => $run->id]),
            ['lines' => [[
                'employee_id' => $this->employee->id,
                'overtime_hours' => 10,
                'overtime_rate' => 50,
            ]]],
        )->assertSessionHasNoErrors();

        $line = $this->lineFor($run->refresh());

        $this->assertSame(500, $line->overtime_amount);
        $this->assertSame(20_500, $line->net_payable);
    }

    /**
     * A recompute must not throw away what somebody typed.
     */
    public function test_typed_inputs_survive_a_recompute()
    {
        $run = $this->openRun();

        $this->actingAs($this->user)->put(
            route('payroll.update', ['current_team' => $this->team->slug, 'run' => $run->id]),
            ['lines' => [[
                'employee_id' => $this->employee->id,
                'overtime_hours' => 4,
                'overtime_rate' => 100,
                'remarks' => 'Covered the night shift',
            ]]],
        )->assertSessionHasNoErrors();

        // Something changes in attendance, forcing a fresh computation.
        $this->mark([['employee_id' => $this->employee->id, 'day' => 3, 'status' => 'absent']]);
        $this->openRun();

        $line = $this->lineFor($run->refresh());

        $this->assertSame(4, $line->overtime_hours);
        $this->assertSame(400, $line->overtime_amount);
        $this->assertSame('Covered the night shift', $line->remarks);
    }

    /**
     * A draft holds no truth and recomputes; an approved run is a document and
     * does not move underneath somebody holding its payslip.
     */
    public function test_attendance_recomputes_a_draft_but_not_an_approved_run()
    {
        $run = $this->openRun();
        $this->assertSame(20_000, $this->lineFor($run)->gross_earned);

        $this->mark([['employee_id' => $this->employee->id, 'day' => 3, 'status' => 'absent']]);

        // A draft reopens recomputed.
        $this->openRun();
        $draftGross = $this->lineFor($run->refresh())->gross_earned;
        $this->assertLessThan(20_000, $draftGross);

        $this->approve($run)->assertSessionHasNoErrors();

        // Now the same change leaves the frozen figure alone.
        $this->mark([['employee_id' => $this->employee->id, 'day' => 4, 'status' => 'absent']]);

        $this->assertSame($draftGross, $this->lineFor($run->refresh())->gross_earned);
    }

    public function test_an_approved_run_shows_drift_rather_than_hiding_it()
    {
        $run = $this->openRun();
        $this->approve($run)->assertSessionHasNoErrors();

        $this->mark([['employee_id' => $this->employee->id, 'day' => 3, 'status' => 'absent']]);

        $this->actingAs($this->user)
            ->get(route('payroll.show', ['current_team' => $this->team->slug, 'run' => $run->id]))
            ->assertInertia(fn ($page) => $page
                ->where('driftedEmployeeIds', [$this->employee->id]),
            );
    }

    public function test_an_approved_run_cannot_be_edited_without_reopening()
    {
        $run = $this->openRun();
        $this->approve($run);

        $this->actingAs($this->user)->put(
            route('payroll.update', ['current_team' => $this->team->slug, 'run' => $run->id]),
            ['lines' => []],
        )->assertSessionHasErrors('status');
    }

    public function test_a_run_with_payments_against_it_cannot_be_reopened()
    {
        $run = $this->openRun();
        $this->approve($run);

        $this->pay(['payroll_run_id' => $run->id])->assertSessionHasNoErrors();

        $this->actingAs($this->user)
            ->post(route('payroll.reopen', ['current_team' => $this->team->slug, 'run' => $run->id]))
            ->assertSessionHasErrors('status');

        $this->assertSame(PayrollRunStatus::Approved, $run->fresh()->status);
    }

    public function test_only_one_run_exists_per_month()
    {
        $this->openRun();
        $this->openRun();

        $this->assertSame(1, PayrollRun::count());
    }

    // ---------------------------------------------------------------- ledger

    /**
     * The reconciliation the whole design rests on: an advance is salary paid
     * early, not a second debt.
     */
    public function test_earn_advance_and_pay_reconciles_to_zero()
    {
        $run = $this->openRun();

        $this->pay([
            'kind' => SalaryPaymentKind::Advance->value,
            'paid_on' => '2026-08-10',
            'amount' => 2_000,
            'installment_amount' => 2_000,
        ])->assertSessionHasNoErrors();

        $this->approve($run)->assertSessionHasNoErrors();

        $line = $this->lineFor($run->refresh());

        // The advance comes off what is handed over, not off what was earned.
        $this->assertSame(20_000, $line->gross_earned);
        $this->assertSame(2_000, $line->advance_deduction);
        $this->assertSame(18_000, $line->net_payable);

        $this->pay(['amount' => 18_000])->assertSessionHasNoErrors();

        $this->assertSame(0, EmployeeLedger::balance($this->employee->fresh()));
        $this->assertSame(0, $this->employee->fresh()->balance);
    }

    public function test_a_draft_run_is_not_on_the_ledger()
    {
        $this->openRun();

        // Nothing is owed until the month is agreed.
        $this->assertSame(0, EmployeeLedger::balance($this->employee));
    }

    public function test_approving_a_run_puts_the_balance_on_the_account()
    {
        $run = $this->openRun();
        $this->approve($run);

        $this->assertSame(20_000, $this->employee->fresh()->balance);
    }

    public function test_reopening_gives_the_advance_outstanding_back()
    {
        $run = $this->openRun();

        $this->pay([
            'kind' => SalaryPaymentKind::Advance->value,
            'paid_on' => '2026-08-05',
            'amount' => 5_000,
            'installment_amount' => 2_000,
        ]);

        $this->approve($run);

        $advance = SalaryPayment::where('kind', SalaryPaymentKind::Advance->value)->firstOrFail();
        $this->assertSame(3_000, $advance->fresh()->outstanding);

        $this->actingAs($this->user)
            ->post(route('payroll.reopen', ['current_team' => $this->team->slug, 'run' => $run->id]))
            ->assertSessionHasNoErrors();

        // The recovery is undone with the run that made it.
        $this->assertSame(5_000, $advance->fresh()->outstanding);
    }

    public function test_an_advance_is_never_recovered_beyond_what_was_earned()
    {
        // Nothing earned: employed, but the rate is zero.
        $pauper = Employee::factory()->create([
            'team_id' => $this->team->id,
            'joined_on' => '2025-01-01',
        ]);
        $this->rate($pauper, 0);

        $this->pay([
            'employee_id' => $pauper->id,
            'kind' => SalaryPaymentKind::Advance->value,
            'paid_on' => '2026-08-01',
            'amount' => 5_000,
            'installment_amount' => 5_000,
        ]);

        $run = $this->openRun();
        $line = $this->lineFor($run, $pauper);

        $this->assertSame(0, $line->advance_deduction);
        $this->assertSame(0, $line->net_payable);
    }

    public function test_a_bonus_dated_inside_the_month_lands_on_the_run()
    {
        EmployeeBonus::factory()->create([
            'team_id' => $this->team->id,
            'employee_id' => $this->employee->id,
            'awarded_on' => '2026-08-15',
            'amount' => 5_000,
        ]);

        $run = $this->openRun();
        $line = $this->lineFor($run);

        $this->assertSame(5_000, $line->bonus_amount);
        $this->assertSame(25_000, $line->net_payable);
    }

    // ------------------------------------------------------------- the money

    /**
     * Invariant I3: wages are money out exactly once, and only when paid.
     */
    public function test_an_approved_run_moves_no_cash()
    {
        $run = $this->openRun();
        $this->approve($run);

        $report = FinancialReport::build(
            $this->team,
            Carbon::create(2026, 8, 1),
            Carbon::create(2026, 8, 31),
        );

        $this->assertSame(0, $report['money']['salaryPaid']);
        $this->assertSame(0, $report['money']['netCash']);
    }

    public function test_a_salary_payment_reduces_net_cash_once()
    {
        $this->pay(['amount' => 20_000])->assertSessionHasNoErrors();

        $report = FinancialReport::build(
            $this->team,
            Carbon::create(2026, 8, 1),
            Carbon::create(2026, 8, 31),
        );

        $this->assertSame(20_000, $report['money']['salaryPaid']);
        $this->assertSame(-20_000, $report['money']['netCash']);
        // And it is not also sitting in expenses.
        $this->assertSame(0, $report['money']['expenses']);
    }

    public function test_the_calculator_is_the_only_answer()
    {
        $run = $this->openRun();

        $computed = collect(PayrollCalculator::forMonth(
            $this->team,
            Carbon::create(2026, 8, 1),
            $run->lines()->get()->keyBy('employee_id'),
        ))->firstWhere('employee_id', $this->employee->id);

        $this->assertSame($this->lineFor($run)->net_payable, $computed['net_payable']);
    }

    /**
     * What has been handed over is read from the payments, not flagged on the
     * line — a payment can be corrected or removed after approval.
     */
    public function test_the_run_shows_what_has_been_paid()
    {
        $run = $this->openRun();
        $this->approve($run);

        $net = $this->lineFor($run->refresh())->net_payable;

        $this->actingAs($this->user)
            ->get(route('payroll.show', ['current_team' => $this->team->slug, 'run' => $run->id]))
            ->assertInertia(fn ($page) => $page
                ->where('paidTotal', 0)
                ->where('lines.0.paid', 0),
            );

        $this->pay(['amount' => $net, 'payroll_run_id' => $run->id])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->user)
            ->get(route('payroll.show', ['current_team' => $this->team->slug, 'run' => $run->id]))
            ->assertInertia(fn ($page) => $page
                ->where('paidTotal', $net)
                ->where('lines.0.paid', $net),
            );
    }

    /**
     * Wages belong on the spending screen, but they are read from
     * `salary_payments` rather than copied into `expenses` — one record of one
     * payment.
     */
    public function test_a_salary_payment_shows_on_the_expenses_screen()
    {
        $this->pay(['amount' => 20_000])->assertSessionHasNoErrors();

        $this->actingAs($this->user)
            ->get(route('expenses.index', ['current_team' => $this->team->slug]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('wages', 1)
                ->where('wages.0.amount', 20_000)
                ->where('wagesTotal', 20_000)
                // And no row was written into the expenses table.
                ->has('expenses', 0)
                ->where('total', 0),
            );

        $this->assertSame(0, Expense::count());
    }

    /**
     * The money card has to add up on screen.
     *
     * Received less expenses less vendors did not reach net cash, and the
     * difference — the wages — had no line and no name, so the card read as
     * arithmetic that had gone wrong.
     */
    public function test_the_report_states_wages_as_their_own_line()
    {
        Expense::factory()->create([
            'team_id' => $this->team->id,
            'spent_on' => '2026-08-10',
            'amount' => 1_500,
        ]);

        $this->pay(['amount' => 19_090])->assertSessionHasNoErrors();

        $this->actingAs($this->user)
            ->get(route('reports.index', [
                'current_team' => $this->team->slug,
                'from' => '2026-08-01',
                'to' => '2026-08-31',
            ]))
            ->assertInertia(function ($page) {
                /** @var array<string, mixed> $money */
                $money = $page->toArray()['props']['report']['money'];

                $this->assertSame(1_500, $money['expenses']);
                $this->assertSame(19_090, $money['salaryPaid']);

                // The card's arithmetic, asserted rather than assumed.
                $this->assertSame(
                    $money['received']
                        - $money['expenses']
                        - $money['salaryPaid']
                        - $money['vendorPaid'],
                    $money['netCash'],
                );
            });
    }

    /**
     * A day the staff were paid is not a good day for the banner to hide.
     */
    public function test_the_dashboard_counts_wages_as_money_out_today()
    {
        $this->pay([
            'amount' => 5_000,
            'paid_on' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->user)
            ->get(route('dashboard', ['current_team' => $this->team->slug]))
            ->assertInertia(fn ($page) => $page
                ->where('stats.expenses', 5_000)
                ->where('stats.total', -5_000),
            );
    }

    /**
     * A wage row names a person and states what they were paid, so it sits
     * behind `payroll:view` — not behind the permission that opens the
     * spending screen.
     */
    public function test_the_expenses_screen_hides_wage_rows_without_payroll_access()
    {
        $this->pay(['amount' => 20_000])->assertSessionHasNoErrors();

        $spender = User::factory()->create();

        $this->team->memberships()->create([
            'user_id' => $spender->id,
            'role' => TeamRole::Member,
            'permissions' => [
                TeamPermission::ViewExpenses->value,
                TeamPermission::ManageExpenses->value,
            ],
        ]);

        $spender->switchTeam($this->team);

        $this->actingAs($spender)
            ->get(route('expenses.index', ['current_team' => $this->team->slug]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // No names, no per-payment amounts.
                ->has('wages', 0)
                // But the total still reconciles with the report.
                ->where('wagesTotal', 20_000),
            );
    }

    public function test_payroll_access_shows_the_wage_rows()
    {
        $this->pay(['amount' => 20_000])->assertSessionHasNoErrors();

        $this->actingAs($this->user)
            ->get(route('expenses.index', ['current_team' => $this->team->slug]))
            ->assertInertia(fn ($page) => $page
                ->has('wages', 1)
                ->where('wages.0.amount', 20_000),
            );
    }

    public function test_another_companys_run_is_not_found()
    {
        $foreign = User::factory()->create();

        $this->actingAs($foreign)->post(
            route('payroll.open', ['current_team' => $foreign->currentTeam->slug]),
            ['month' => '2026-08'],
        )->assertSessionHasNoErrors();

        $foreignRun = PayrollRun::where('team_id', $foreign->currentTeam->id)->firstOrFail();

        $this->actingAs($this->user)
            ->get(route('payroll.show', [
                'current_team' => $this->team->slug,
                'run' => $foreignRun->id,
            ]))
            ->assertNotFound();
    }

    public function test_a_member_cannot_see_payroll()
    {
        $member = User::factory()->create();
        $this->team->memberships()->create([
            'user_id' => $member->id,
            'role' => TeamRole::Member,
        ]);
        $member->switchTeam($this->team);

        $this->actingAs($member)
            ->get(route('payroll.index', ['current_team' => $this->team->slug]))
            ->assertForbidden();
    }
}
