<?php

namespace Tests\Feature\Hr;

use App\Enums\ExpenseCategory;
use App\Enums\SalaryPaymentKind;
use App\Enums\SalaryType;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\PayrollRun;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * One month, walked end to end through the screens a person actually uses.
 *
 * The unit tests each prove one rule; this proves the rules connect — that a
 * department leads to an employee, an employee to a rate, a rate and a month of
 * attendance to a payroll run, a run to a payslip and a payment, and a payment
 * back to the financial report. A module can pass every test in isolation and
 * still have a step in the middle that nobody can reach.
 *
 * Every screen is opened as well as every action taken, because a page that
 * 500s is not something a unit test on an action would notice.
 */
class PeopleWalkthroughTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = $this->user->currentTeam;

        $this->travelTo('2026-08-31');
    }

    private function url(string $path): string
    {
        return "/{$this->team->slug}/{$path}";
    }

    private function open(string $path): TestResponse
    {
        return $this->actingAs($this->user)->get($this->url($path));
    }

    public function test_a_company_can_run_a_month_of_payroll_end_to_end()
    {
        // ---------------------------------------------------------- people

        $this->open('hr/departments')->assertOk();

        $this->actingAs($this->user)
            ->post($this->url('hr/departments'), ['name' => 'Delivery'])
            ->assertSessionHasNoErrors();

        $department = $this->team->departments()->firstOrFail();

        $this->open('hr/employees')->assertOk();
        $this->open('hr/employees/create')->assertOk();

        $this->actingAs($this->user)->post($this->url('hr/employees'), [
            'employee_code' => 'EMP-001',
            'name' => 'Rahim Uddin',
            'department_id' => $department->id,
            'designation' => 'Delivery Supervisor',
            'salary_type' => SalaryType::Monthly->value,
            'joined_on' => '2025-06-01',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->user)->post($this->url('hr/employees'), [
            'employee_code' => 'EMP-002',
            'name' => 'Karim Mia',
            'salary_type' => SalaryType::Daily->value,
            'joined_on' => '2025-06-01',
        ])->assertSessionHasNoErrors();

        $monthly = $this->team->employees()->where('employee_code', 'EMP-001')->firstOrFail();
        $daily = $this->team->employees()->where('employee_code', 'EMP-002')->firstOrFail();

        $this->open("hr/employees/{$monthly->id}")->assertOk();
        $this->open("hr/employees/{$monthly->id}/edit")->assertOk();

        // ------------------------------------------------- the working week

        $this->open('hr/holidays')->assertOk();

        $this->actingAs($this->user)->put($this->url('hr/holidays/settings'), [
            'weekend_days' => [5, 6],
            'overtime_hourly_rate' => 60,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->user)->post($this->url('hr/holidays'), [
            'date' => '2026-08-17',
            'name' => 'Company day',
        ])->assertSessionHasNoErrors();

        // ------------------------------------------------------ attendance

        $this->open('hr/attendance?month=2026-08')->assertOk();

        $this->actingAs($this->user)->put($this->url('hr/attendance'), [
            'month' => '2026-08',
            'marks' => [
                // The salaried supervisor missed two days.
                ['employee_id' => $monthly->id, 'day' => 3, 'status' => 'absent'],
                ['employee_id' => $monthly->id, 'day' => 4, 'status' => 'absent'],
                // The daily worker worked five.
                ['employee_id' => $daily->id, 'day' => 3, 'status' => 'present'],
                ['employee_id' => $daily->id, 'day' => 4, 'status' => 'present'],
                ['employee_id' => $daily->id, 'day' => 5, 'status' => 'present'],
                ['employee_id' => $daily->id, 'day' => 6, 'status' => 'present'],
                ['employee_id' => $daily->id, 'day' => 10, 'status' => 'present'],
            ],
        ])->assertSessionHasNoErrors();

        $this->open('hr/attendance/summary?month=2026-08')->assertOk();
        $this->open('hr/attendance/excel?month=2026-08')->assertOk();

        // ----------------------------------------------------------- rates

        $this->open('hr/payroll/rates')->assertOk();

        $this->actingAs($this->user)->post($this->url('hr/payroll/rates'), [
            'employee_id' => $monthly->id,
            'salary_type' => SalaryType::Monthly->value,
            'amount' => 30_000,
            'effective_from' => '2025-06-01',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->user)->post($this->url('hr/payroll/rates'), [
            'employee_id' => $daily->id,
            'salary_type' => SalaryType::Daily->value,
            'amount' => 700,
            'effective_from' => '2025-06-01',
        ])->assertSessionHasNoErrors();

        // ---------------------------------------------------------- bonuses

        $this->open('hr/bonuses')->assertOk();

        $this->actingAs($this->user)->post($this->url('hr/bonuses'), [
            'employee_id' => $monthly->id,
            'bonus_type' => 'festival',
            'awarded_on' => '2026-08-20',
            'amount' => 5_000,
        ])->assertSessionHasNoErrors();

        // --------------------------------------------------------- advance

        $this->open('hr/salary-payments')->assertOk();
        $this->open('hr/salary-payments/create')->assertOk();

        $this->actingAs($this->user)->post($this->url('hr/salary-payments'), [
            'employee_id' => $monthly->id,
            'kind' => SalaryPaymentKind::Advance->value,
            'paid_on' => '2026-08-08',
            'amount' => 6_000,
            'installment_amount' => 2_000,
        ])->assertSessionHasNoErrors();

        // --------------------------------------------------------- payroll

        $this->open('hr/payroll')->assertOk();

        $this->actingAs($this->user)
            ->post($this->url('hr/payroll/open'), ['month' => '2026-08'])
            ->assertSessionHasNoErrors();

        $run = PayrollRun::firstOrFail();

        $this->open("hr/payroll/{$run->id}")->assertOk();

        // Type some overtime onto the supervisor's line.
        $this->actingAs($this->user)->put($this->url("hr/payroll/{$run->id}"), [
            'lines' => [
                ['employee_id' => $monthly->id, 'overtime_hours' => 5, 'overtime_rate' => 60],
                ['employee_id' => $daily->id],
            ],
        ])->assertSessionHasNoErrors();

        $line = $run->lines()->where('employee_id', $monthly->id)->firstOrFail();
        $dailyLine = $run->lines()->where('employee_id', $daily->id)->firstOrFail();

        // Two days missed out of thirty, on a 30,000 salary.
        $this->assertSame(intdiv(30_000 * 56, 60), $line->gross_earned);
        $this->assertSame(300, $line->overtime_amount);
        $this->assertSame(5_000, $line->bonus_amount);
        $this->assertSame(2_000, $line->advance_deduction);

        // Five days worked at 700 a day.
        $this->assertSame(3_500, $dailyLine->gross_earned);

        $this->open("hr/payroll/{$run->id}/payslips")->assertOk();

        $this->actingAs($this->user)
            ->post($this->url("hr/payroll/{$run->id}/approve"))
            ->assertSessionHasNoErrors();

        $this->open("hr/payroll/{$run->id}")->assertOk();
        $this->open("hr/payroll/{$run->id}/payslips")->assertOk();

        // ---------------------------------------------------------- paying

        /*
         * Re-read rather than refresh: approving recomputes the month, which
         * deletes and rebuilds every line, so the rows fetched above no longer
         * exist by id. That is deliberate — a recompute rebuilds rather than
         * diffs, because the set of people on a month changes when somebody
         * joins or leaves — and it is why nothing outside a run holds on to a
         * line id except `advance_repayments`, which is written after the
         * rebuild and cascades with it.
         */
        $line = $run->lines()->where('employee_id', $monthly->id)->firstOrFail();
        $dailyLine = $run->lines()->where('employee_id', $daily->id)->firstOrFail();

        foreach ([$line, $dailyLine] as $payable) {
            $this->actingAs($this->user)->post($this->url('hr/salary-payments'), [
                'employee_id' => $payable->employee_id,
                'kind' => SalaryPaymentKind::Salary->value,
                'paid_on' => '2026-08-31',
                'amount' => $payable->net_payable,
                'payroll_run_id' => $run->id,
            ])->assertSessionHasNoErrors();
        }

        // The run now reports itself as paid.
        $this->open("hr/payroll/{$run->id}")
            ->assertInertia(fn ($page) => $page
                ->where('paidTotal', $line->net_payable + $dailyLine->net_payable),
            );

        // ------------------------------------------------------- the books

        $this->open("hr/employees/{$monthly->id}/statement")->assertOk();

        // Earned, less the advance and the salary paid, is what is left owing.
        $earned = $line->gross_earned + $line->overtime_amount;
        $this->assertSame(
            $earned + 5_000 - 6_000 - $line->net_payable,
            $monthly->fresh()->balance,
        );

        $wagesPaid = 6_000 + $line->net_payable + $dailyLine->net_payable;

        $this->actingAs($this->user)
            ->get($this->url('finance/reports?from=2026-08-01&to=2026-08-31'))
            ->assertOk()
            ->assertInertia(function ($page) use ($wagesPaid) {
                $report = $page->toArray()['props']['report'];

                $this->assertSame($wagesPaid, $report['money']['salaryPaid']);

                // The card's own arithmetic.
                $this->assertSame(
                    $report['money']['received']
                        - $report['money']['expenses']
                        - $report['money']['salaryPaid']
                        - $report['money']['vendorPaid'],
                    $report['money']['netCash'],
                );

                // And the donut knows where it went.
                $salary = collect($report['expensesByCategory'])
                    ->firstWhere('category', 'salary');

                $this->assertNotNull($salary, 'Wages are missing from the breakdown.');
                $this->assertSame($wagesPaid, $salary['amount']);
                $this->assertSame('Salary & Wages', $salary['label']);
            });

        $this->open('finance/expenses')
            ->assertInertia(fn ($page) => $page
                ->where('wagesTotal', $wagesPaid)
                ->has('wages', 3),
            );
    }

    /**
     * A wage recorded before payroll existed and a payroll payment are the same
     * kind of spending, so the breakdown states them as one line rather than
     * two slices with the same name.
     */
    public function test_legacy_salary_expenses_merge_with_payroll_in_the_breakdown()
    {
        Expense::factory()->create([
            'team_id' => $this->team->id,
            'category' => ExpenseCategory::Salary,
            'spent_on' => '2026-08-05',
            'amount' => 4_000,
        ]);

        $employee = Employee::factory()->create([
            'team_id' => $this->team->id,
            'joined_on' => '2025-01-01',
        ]);

        $this->actingAs($this->user)->post($this->url('hr/salary-payments'), [
            'employee_id' => $employee->id,
            'kind' => SalaryPaymentKind::Salary->value,
            'paid_on' => '2026-08-25',
            'amount' => 11_000,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->user)
            ->get($this->url('finance/reports?from=2026-08-01&to=2026-08-31'))
            ->assertInertia(function ($page) {
                $breakdown = collect(
                    $page->toArray()['props']['report']['expensesByCategory'],
                );

                $salary = $breakdown->where('category', 'salary');

                $this->assertCount(1, $salary, 'Wages should be one line, not two.');
                $this->assertSame(15_000, $salary->first()['amount']);
            });
    }
}
