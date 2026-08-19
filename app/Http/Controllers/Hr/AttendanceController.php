<?php

namespace App\Http\Controllers\Hr;

use App\Actions\Attendance\SaveAttendance;
use App\Concerns\ResolvesCurrentTeam;
use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\SaveAttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Support\AttendanceSummary;
use App\Support\WorkingCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    use ResolvesCurrentTeam;

    /**
     * The month's grid: people down the side, days across the top.
     */
    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);
        $month = $this->month($request);

        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $calendar = WorkingCalendar::forMonth($team, $month);

        $employees = $team->employees()
            ->with('department')
            ->orderBy('name')
            ->get()
            /*
             * Only people who were here for some part of the month. Marking
             * somebody before they joined would produce attendance that payroll
             * then has to ignore, so the grid never offers the cell.
             */
            ->filter(fn (Employee $employee) => $employee->joined_on->lte($to)
                && ($employee->left_on === null || $employee->left_on->gte($from)))
            ->values();

        $marks = AttendanceRecord::query()
            ->where('team_id', $team->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get();

        return Inertia::render('company/hr/attendance/index', [
            'month' => $from->format('Y-m'),
            'monthLabel' => $from->format('F Y'),
            'daysInMonth' => (int) $from->daysInMonth,
            'nonWorkingDays' => $calendar->nonWorkingDaysOfMonth($month),
            'workingDays' => $calendar->workingDaysBetween($from, $to),
            'statuses' => AttendanceStatus::options(),

            'employees' => $employees->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'employeeCode' => $employee->employee_code,
                'name' => $employee->name,
                'departmentName' => $employee->department?->name,
                'salaryType' => $employee->salary_type->value,
                // Which cells are theirs to mark: a mid-month joiner's earlier
                // days are not.
                'firstDay' => $employee->joined_on->gt($from) ? (int) $employee->joined_on->day : 1,
                'lastDay' => $employee->left_on !== null && $employee->left_on->lt($to)
                    ? (int) $employee->left_on->day
                    : (int) $from->daysInMonth,
            ])->all(),

            // `{employeeId: {day: status}}` — the shape the grid indexes by.
            'marks' => $marks
                ->groupBy('employee_id')
                ->map(fn ($group) => $group
                    ->mapWithKeys(fn (AttendanceRecord $record) => [
                        (int) $record->date->day => $record->status->value,
                    ]))
                ->all(),
        ]);
    }

    /**
     * Apply the cells that changed.
     */
    public function update(SaveAttendanceRequest $request, SaveAttendance $saveAttendance): RedirectResponse
    {
        $team = $this->currentTeam($request);

        /** @var list<array{employee_id: int, day: int, status: string|null}> $marks */
        $marks = $request->validated('marks');

        $changed = $saveAttendance->handle(
            team: $team,
            month: $request->month(),
            marks: $marks,
            actor: $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $changed === 0
                ? __('Nothing to save.')
                : __(':count day(s) saved.', ['count' => $changed]),
        ]);

        return to_route('attendance.index', [
            'current_team' => $team->slug,
            'month' => $request->month()->format('Y-m'),
        ]);
    }

    /**
     * The month counted per person.
     */
    public function summary(Request $request): Response
    {
        $team = $this->currentTeam($request);

        return Inertia::render('company/hr/attendance/summary', [
            'summary' => AttendanceSummary::build($team, $this->month($request)),
        ]);
    }

    /**
     * The same summary as a spreadsheet.
     */
    public function excel(Request $request): StreamedResponse
    {
        $team = $this->currentTeam($request);
        $month = $this->month($request);
        $summary = AttendanceSummary::build($team, $month);

        return response()->streamDownload(function () use ($team, $summary) {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                throw new RuntimeException('Could not open the output stream for the attendance export.');
            }

            // Excel reads a file as the system codepage unless a UTF-8 BOM says
            // otherwise, which would mangle any Bangla name.
            fwrite($handle, "\u{FEFF}");

            fputcsv($handle, [$team->name]);
            fputcsv($handle, ['Attendance Summary']);
            fputcsv($handle, [(string) $summary['monthLabel']]);
            fputcsv($handle, ['Working days', $summary['workingDays']]);
            fputcsv($handle, []);
            fputcsv($handle, [
                'Staff No.', 'Name', 'Department', 'Expected', 'Present',
                'Half days', 'Paid leave', 'Unpaid leave', 'Absent', 'Unmarked',
            ]);

            /** @var list<array<string, mixed>> $rows */
            $rows = $summary['rows'];

            foreach ($rows as $row) {
                fputcsv($handle, [
                    (string) $row['employeeCode'],
                    (string) $row['name'],
                    (string) ($row['departmentName'] ?? ''),
                    (int) $row['expectedDays'],
                    (int) $row['present'],
                    (int) $row['halfDays'],
                    (int) $row['paidLeave'],
                    (int) $row['unpaidLeave'],
                    (int) $row['absent'],
                    (int) $row['unmarked'],
                ]);
            }

            fclose($handle);
        }, "attendance-{$month->format('Y-m')}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The month being worked on.
     *
     * Defaults to this one in the business's timezone — see `APP_TIMEZONE`,
     * which is what decides whether the 1st belongs to this month or the last.
     */
    private function month(Request $request): Carbon
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        return isset($validated['month'])
            ? Carbon::createFromFormat('Y-m-d', $validated['month'].'-01')->startOfMonth()
            : Carbon::now()->startOfMonth();
    }
}
