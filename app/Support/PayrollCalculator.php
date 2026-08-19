<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Enums\SalaryPaymentKind;
use App\Enums\SalaryType;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\EmployeeSalaryRate;
use App\Models\PayrollLine;
use App\Models\PayrollSetting;
use App\Models\SalaryPayment;
use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * What each person earned in a month.
 *
 * The **single** place a month's pay is worked out. The draft recompute, the
 * approve action and the drift check on an approved run all call this, so the
 * figure on the screen, the figure on the payslip and the figure written to the
 * ledger cannot be three different numbers — the same property
 * `DistributorLedger` gives the statement.
 *
 * ## A month is thirty days, and only absence deducts
 *
 * A monthly salary is divided by **thirty**, always — not by the working days a
 * particular month happens to have. That is how these companies state a wage
 * ("twenty thousand a month, so a day is 666"), and it means the same day off
 * costs the same in February as in August.
 *
 * The salary is then **reduced by what was missed**, never built up from what
 * was attended. The difference shows in a month still running: on the 5th,
 * twenty-five days have not happened yet, and a figure built up from attendance
 * would read as a sixth of a salary and look like a pay cut. Nothing is
 * deducted for a day that has not happened, or for a day nobody marked —
 * absence is something a person records.
 *
 * Counting is in half-days so a half-day worked never needs a second rounding:
 *
 *     monthly:  unit_total   = 60                    (30 days × 2)
 *               unit_payable = 60 − time not employed − half-days missed
 *               gross        = intdiv(rate × unit_payable, 60)
 *
 *     daily:    unit_total   = 2 × every day employed, weekends included
 *               unit_payable = half-days actually worked
 *               gross        = intdiv(rate × unit_payable, 2)
 *
 * A daily wage is the opposite by design: it is a price per day worked, so five
 * days worked is five days' pay and an unmarked day is worth nothing. In a
 * running month that is not a bug — it is what a daily wage means.
 *
 * A full month therefore pays exactly the salary, in any month length, with
 * nothing to round. The absence figure is reported as the complement
 * (`rate − gross`) so the payslip still adds up where a part-month division
 * truncated.
 *
 * ## Why the two bases treat a weekend differently
 *
 * A monthly salary already contains the weekend, so a monthly employee marked
 * present on a Friday is **not** paid twice for it; extra hours are recorded as
 * overtime, which is typed. A daily wage is a price per day worked, so a
 * daily-wage employee present on a Friday **is** paid for it. This asymmetry is
 * the whole difference between the two bases and is expressed only here.
 *
 * ## Unmarked days
 *
 * For monthly staff an unmarked working day counts as worked — attendance is
 * exception-marking, and an office of thirty cannot be ticked off every
 * morning. For daily wages it counts as nothing: no record of the day means no
 * day's work to pay for. See App\Enums\SalaryType::unmarkedDayCountsAsWorked().
 */
final class PayrollCalculator
{
    /**
     * Half-days in a month's salary: thirty days, always.
     *
     * Fixed rather than counted, so the same absence costs the same whichever
     * month it falls in, and a full month always pays exactly the salary.
     */
    private const MONTHLY_UNITS = 60;

    /**
     * Compute every line of a month, without writing anything.
     *
     * @param  Collection<int, PayrollLine>  $existing  Lines whose typed inputs must survive, keyed by employee id.
     * @return list<array<string, mixed>>
     */
    public static function forMonth(Team $team, CarbonInterface $month, Collection $existing = new Collection): array
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $calendar = WorkingCalendar::forMonth($team, $month);
        $settings = PayrollSetting::forTeam($team);

        $employees = self::employeesFor($team, $from, $to);

        if ($employees->isEmpty()) {
            return [];
        }

        /** @var list<int> $employeeIds */
        $employeeIds = array_values(array_map(
            static fn (Employee $employee): int => $employee->id,
            $employees->all(),
        ));

        $rates = self::ratesAsOf($team, $employeeIds, $to);
        $marks = self::marks($team, $employeeIds, $from, $to);
        $bonuses = self::bonuses($team, $employeeIds, $from, $to);
        $advances = self::openAdvances($team, $employeeIds, $to);

        return array_values($employees->map(function (Employee $employee) use (
            $calendar, $settings, $rates, $marks, $bonuses, $advances, $existing, $from, $to,
        ): array {
            $line = $existing->get($employee->id);
            $rate = $rates[$employee->id] ?? null;

            // The rate row is the authority — including for the basis, since
            // somebody can move from a daily wage to a monthly salary.
            // `->` not `?->`: `??` already suppresses the access on a null
            // rate, and the nullsafe would be redundant noise.
            $salaryType = $rate->salary_type ?? $employee->salary_type;
            $amount = $rate->amount ?? 0;

            $counted = self::countDays(
                $employee,
                $salaryType,
                $calendar,
                $marks[$employee->id] ?? [],
                $from,
                $to,
            );

            /*
             * The divisor is where the two bases part company.
             *
             * A monthly salary is divided by thirty whatever the month holds,
             * so a day off costs the same all year. A daily wage is divided by
             * 2 — the half-days in one day — because it is a price per day
             * worked, and dividing it by a month as well would pay somebody a
             * thirtieth of a day's wage for a full day's work.
             */
            $divisor = $salaryType === SalaryType::Monthly
                ? self::MONTHLY_UNITS
                : 2;

            // Never zero — 60 or 2 — so there is nothing to guard against.
            $gross = intdiv($amount * $counted['unitPayable'], $divisor);

            $overtimeHours = (int) ($line->overtime_hours ?? 0);
            $overtimeRate = (int) ($line->overtime_rate ?? $settings->overtime_hourly_rate ?? 0);
            $otherAddition = (int) ($line->other_addition ?? 0);
            $otherDeduction = (int) ($line->other_deduction ?? 0);

            $overtimeAmount = $overtimeHours * $overtimeRate;
            $bonusAmount = $bonuses[$employee->id] ?? 0;

            $available = $gross + $overtimeAmount + $bonusAmount + $otherAddition;

            $advanceDeduction = self::advanceDeduction(
                $advances[$employee->id] ?? [],
                $available,
            );

            return [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'employee_code' => $employee->employee_code,

                // Inputs, carried through untouched.
                'overtime_hours' => $overtimeHours,
                'overtime_rate' => $overtimeRate,
                'other_addition' => $otherAddition,
                'other_deduction' => $otherDeduction,
                'remarks' => $line->remarks ?? null,

                // Computed.
                'salary_type' => $salaryType->value,
                'rate_applied' => $amount,
                'unit_total' => $counted['unitTotal'],
                'unit_payable' => $counted['unitPayable'],
                'present_days' => $counted['present'],
                'half_days' => $counted['halfDays'],
                'absent_days' => $counted['absent'],
                'leave_days' => $counted['leave'],
                'gross_earned' => $gross,
                'overtime_amount' => $overtimeAmount,
                'bonus_amount' => $bonusAmount,
                'advance_deduction' => $advanceDeduction,
                'net_payable' => $available - $advanceDeduction - $otherDeduction,

                /*
                 * Reported as the complement rather than computed separately,
                 * so `rate_applied = gross_earned + absence_deduction` holds
                 * exactly even where the division truncated.
                 *
                 * Only meaningful for a monthly salary. A daily wage has no
                 * promised monthly total to fall short of, so there is nothing
                 * for absence to be deducted from.
                 */
                'absence_deduction' => $salaryType === SalaryType::Monthly
                    ? max($amount - $gross, 0)
                    : 0,
            ];
        })->all());
    }

    /**
     * Everyone whose employment overlapped the month.
     *
     * Somebody who left in June is not on July's payroll, and a mid-month
     * joiner is paid from the day they started rather than for the whole month.
     *
     * @return Collection<int, Employee>
     */
    private static function employeesFor(Team $team, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $team->employees()
            ->orderBy('name')
            ->get()
            ->filter(fn (Employee $employee) => $employee->joined_on->lte($to)
                && ($employee->left_on === null || $employee->left_on->gte($from)))
            ->values();
    }

    /**
     * The rate in force for each employee during the month.
     *
     * The latest row dated on or before the month's last day. A raise dated
     * after the month is therefore invisible to it, which is what keeps an old
     * payslip true.
     *
     * @param  list<int>  $employeeIds
     * @return array<int, EmployeeSalaryRate>
     */
    private static function ratesAsOf(Team $team, array $employeeIds, CarbonInterface $to): array
    {
        $rates = [];

        $rows = EmployeeSalaryRate::query()
            ->where('team_id', $team->id)
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('effective_from', '<=', $to->toDateString())
            ->orderBy('effective_from')
            ->orderBy('id')
            ->get();

        // Ascending, so later rows overwrite earlier ones and what is left is
        // the rate in force during the month.
        foreach ($rows as $rate) {
            $rates[$rate->employee_id] = $rate;
        }

        return $rates;
    }

    /**
     * @param  list<int>  $employeeIds
     * @return array<int, array<string, AttendanceRecord>>
     */
    private static function marks(Team $team, array $employeeIds, CarbonInterface $from, CarbonInterface $to): array
    {
        $marks = [];

        $rows = AttendanceRecord::query()
            ->where('team_id', $team->id)
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get();

        foreach ($rows as $record) {
            $marks[$record->employee_id][$record->date->toDateString()] = $record;
        }

        return $marks;
    }

    /**
     * Bonuses awarded inside the month, per employee.
     *
     * Derived from the award date rather than joined to the run, so backdating
     * an Eid bonus corrects the month it belongs to.
     *
     * @param  list<int>  $employeeIds
     * @return array<int, int>
     */
    private static function bonuses(Team $team, array $employeeIds, CarbonInterface $from, CarbonInterface $to): array
    {
        $totals = [];

        $rows = EmployeeBonus::query()
            ->where('team_id', $team->id)
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('awarded_on', [$from->toDateString(), $to->toDateString()])
            ->get();

        foreach ($rows as $bonus) {
            $totals[$bonus->employee_id] = ($totals[$bonus->employee_id] ?? 0) + $bonus->amount;
        }

        return $totals;
    }

    /**
     * Advances with money still to recover, oldest first.
     *
     * @param  list<int>  $employeeIds
     * @return array<int, list<SalaryPayment>>
     */
    private static function openAdvances(Team $team, array $employeeIds, CarbonInterface $to): array
    {
        $advances = [];

        $rows = SalaryPayment::query()
            ->where('team_id', $team->id)
            ->whereIn('employee_id', $employeeIds)
            ->where('kind', SalaryPaymentKind::Advance->value)
            ->where('outstanding', '>', 0)
            ->whereDate('paid_on', '<=', $to->toDateString())
            // Oldest first: the order they are recovered in.
            ->orderBy('paid_on')
            ->orderBy('id')
            ->get();

        foreach ($rows as $advance) {
            $advances[$advance->employee_id][] = $advance;
        }

        return $advances;
    }

    /**
     * How much of the outstanding advances this month can recover.
     *
     * Oldest first, each capped at its own installment and at what is left of
     * it, and the whole thing capped at what was actually earned — recovering
     * more than somebody earned would hand them a negative payslip and a debt
     * they cannot see.
     *
     * @param  list<SalaryPayment>  $advances
     */
    private static function advanceDeduction(array $advances, int $available): int
    {
        $remaining = max($available, 0);
        $deducted = 0;

        foreach ($advances as $advance) {
            if ($remaining <= 0) {
                break;
            }

            $installment = $advance->installment_amount ?? $advance->outstanding;

            $take = min($installment, $advance->outstanding, $remaining);

            if ($take <= 0) {
                continue;
            }

            $deducted += $take;
            $remaining -= $take;
        }

        return $deducted;
    }

    /**
     * Walk the month, counting half-days.
     *
     * The two bases count in opposite directions, and that is the point:
     *
     * - **Monthly** starts at a full month and *subtracts* — recorded absence,
     *   unpaid leave, half a day for a half day, and the stretch before
     *   somebody joined or after they left. A day nobody marked, and a day that
     *   has not happened yet, subtract nothing. That is what stops a salary
     *   reading as a sixth of itself on the 5th of the month.
     * - **Daily** starts at nothing and *adds* the days actually worked,
     *   because a wage is owed only for a day that was worked.
     *
     * The two kinds of deduction are kept apart deliberately. Time not employed
     * is capped at the month's entitlement, because a 31-day month holds one
     * more day than the thirty being divided by and nobody should be docked for
     * a day the salary never contained. Absence is never capped that way — a
     * short month does not forgive a day off.
     *
     * @param  array<string, AttendanceRecord>  $marks
     * @return array{unitTotal: int, unitPayable: int, present: int, halfDays: int, absent: int, leave: int}
     */
    private static function countDays(
        Employee $employee,
        SalaryType $salaryType,
        WorkingCalendar $calendar,
        array $marks,
        CarbonInterface $from,
        CarbonInterface $to,
    ): array {
        $monthly = $salaryType === SalaryType::Monthly;

        $unitTotal = $monthly ? self::MONTHLY_UNITS : 0;
        $notEmployed = 0;
        $missed = 0;
        $earned = 0;
        $present = 0;
        $halfDays = 0;
        $absent = 0;
        $leave = 0;

        foreach ($calendar->days($from, $to) as $day) {
            if (! $employee->wasEmployedOn($day)) {
                if ($monthly) {
                    $notEmployed += 2;
                }

                continue;
            }

            // A monthly salary already contains the weekend; a daily wage earns
            // any day that was worked, whichever day of the week it fell on.
            if ($monthly && ! $calendar->isWorkingDay($day)) {
                continue;
            }

            if (! $monthly) {
                $unitTotal += 2;
            }

            $record = $marks[$day->toDateString()] ?? null;

            if ($record === null) {
                /*
                 * Nothing recorded. For a salary that means nothing happened
                 * worth deducting — including every day of the month still to
                 * come. For a wage it means no day's work to pay for.
                 */
                if (! $monthly && $salaryType->unmarkedDayCountsAsWorked()) {
                    $earned += 2;
                }

                continue;
            }

            $payable = $record->status->payableHalfDays();

            if ($monthly) {
                $missed += 2 - $payable;
            } else {
                $earned += $payable;
            }

            match ($record->status) {
                AttendanceStatus::Present => $present++,
                AttendanceStatus::HalfDay => $halfDays++,
                AttendanceStatus::Absent => $absent++,
                AttendanceStatus::PaidLeave, AttendanceStatus::UnpaidLeave => $leave++,
            };
        }

        $payable = $monthly
            ? max(self::MONTHLY_UNITS - min($notEmployed, self::MONTHLY_UNITS) - $missed, 0)
            : $earned;

        return [
            'unitTotal' => $unitTotal,
            'unitPayable' => $payable,
            'present' => $present,
            'halfDays' => $halfDays,
            'absent' => $absent,
            'leave' => $leave,
        ];
    }
}
