<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Team;
use Carbon\CarbonInterface;

/**
 * A month of attendance, per person, counted.
 *
 * Nothing is stored: the counts are derived from the marks and the company's
 * working calendar every time the screen is asked for, so correcting a mark or
 * declaring a holiday after the fact corrects the month rather than leaving a
 * stale total behind. Same promise as the stock report.
 *
 * `unmarked` is the column worth reading. For salaried staff it is normal —
 * attendance there is exception-marking — but for daily-wage workers every
 * unmarked working day is a day that will not be paid, so the screen can warn
 * before payroll does it silently.
 */
final class AttendanceSummary
{
    /**
     * @return array<string, mixed>
     */
    public static function build(Team $team, CarbonInterface $month): array
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $calendar = WorkingCalendar::forMonth($team, $month);

        $employees = $team->employees()
            ->with('department')
            ->orderBy('name')
            ->get()
            // Somebody who had left before the month began, or joined after it
            // ended, was never expected — showing them with a month of
            // absences would be a lie about them.
            ->filter(fn (Employee $employee) => $employee->joined_on->lte($to)
                && ($employee->left_on === null || $employee->left_on->gte($from)))
            ->values();

        $marks = AttendanceRecord::query()
            ->where('team_id', $team->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->groupBy('employee_id');

        $rows = $employees->map(function (Employee $employee) use ($marks, $calendar, $from, $to): array {
            $employeeMarks = $marks->get($employee->id, collect())
                ->keyBy(fn (AttendanceRecord $record) => $record->date->toDateString());

            $counts = [
                AttendanceStatus::Present->value => 0,
                AttendanceStatus::HalfDay->value => 0,
                AttendanceStatus::PaidLeave->value => 0,
                AttendanceStatus::UnpaidLeave->value => 0,
                AttendanceStatus::Absent->value => 0,
            ];

            $expected = 0;
            $unmarked = 0;

            foreach ($calendar->days($from, $to) as $day) {
                if (! $employee->wasEmployedOn($day) || ! $calendar->isWorkingDay($day)) {
                    continue;
                }

                $expected++;

                $record = $employeeMarks->get($day->toDateString());

                if ($record === null) {
                    $unmarked++;

                    continue;
                }

                $counts[$record->status->value]++;
            }

            return [
                'id' => $employee->id,
                'employeeCode' => $employee->employee_code,
                'name' => $employee->name,
                'departmentName' => $employee->department?->name,
                'salaryType' => $employee->salary_type->value,
                'expectedDays' => $expected,
                'present' => $counts[AttendanceStatus::Present->value],
                'halfDays' => $counts[AttendanceStatus::HalfDay->value],
                'paidLeave' => $counts[AttendanceStatus::PaidLeave->value],
                'unpaidLeave' => $counts[AttendanceStatus::UnpaidLeave->value],
                'absent' => $counts[AttendanceStatus::Absent->value],
                'unmarked' => $unmarked,
            ];
        })->all();

        return [
            'month' => $from->format('Y-m'),
            'monthLabel' => $from->format('F Y'),
            'workingDays' => $calendar->workingDaysBetween($from, $to),
            'rows' => $rows,
        ];
    }
}
