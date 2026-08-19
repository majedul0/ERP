import { useState } from 'react';
import { Button } from '@/components/ui/button';
import type { AttendanceEmployee, AttendanceStatusOption } from '../types';

/**
 * The colour of each mark.
 *
 * Semantic, not categorical: present/absent is a state, and the app's status
 * vocabulary already reads that way elsewhere. Every cell also carries its
 * letter, so the meaning never rests on colour alone.
 */
const statusStyles: Record<string, string> = {
    present: 'bg-emerald-100 text-emerald-900',
    half_day: 'bg-amber-100 text-amber-900',
    paid_leave: 'bg-sky-100 text-sky-900',
    unpaid_leave: 'bg-coffee-200 text-coffee-900',
    absent: 'bg-red-100 text-red-900',
};

/** What a click cycles through, ending back at "no mark". */
const cycle = [
    'present',
    'half_day',
    'paid_leave',
    'unpaid_leave',
    'absent',
    '',
];

export type PendingMark = {
    employee_id: number;
    day: number;
    status: string | null;
};

/**
 * A month of attendance, marked in place.
 *
 * Cells are edited locally and only the ones that changed are submitted — which
 * is what lets two supervisors work the same month at once without one saving
 * over the other. The dirty count is shown so nobody navigates away thinking
 * they saved.
 *
 * Weekends and holidays are tinted and not clickable: they are derived from the
 * company's working week rather than marked, so the way to change one is to
 * change the working week. Days before somebody joined or after they left are
 * likewise not theirs to mark.
 */
export function AttendanceGrid({
    employees,
    daysInMonth,
    nonWorkingDays,
    statuses,
    marks,
    pending,
    onChange,
    readOnly,
}: {
    employees: AttendanceEmployee[];
    daysInMonth: number;
    nonWorkingDays: number[];
    statuses: AttendanceStatusOption[];
    marks: Record<string, Record<string, string>>;
    pending: PendingMark[];
    onChange: (next: PendingMark[]) => void;
    readOnly: boolean;
}) {
    const [bulk, setBulk] = useState('present');

    const days = Array.from({ length: daysInMonth }, (_, index) => index + 1);
    const nonWorking = new Set(nonWorkingDays);
    const initials = new Map(statuses.map((s) => [s.value, s.initial]));

    /** What a cell shows now: the pending edit if there is one, else the saved mark. */
    const valueOf = (employeeId: number, day: number): string => {
        const edit = pending.find(
            (mark) => mark.employee_id === employeeId && mark.day === day,
        );

        if (edit) {
            return edit.status ?? '';
        }

        return marks[String(employeeId)]?.[String(day)] ?? '';
    };

    const markable = (employee: AttendanceEmployee, day: number) =>
        !readOnly &&
        !nonWorking.has(day) &&
        day >= employee.firstDay &&
        day <= employee.lastDay;

    const set = (employeeId: number, day: number, status: string) => {
        const others = pending.filter(
            (mark) => !(mark.employee_id === employeeId && mark.day === day),
        );

        const saved = marks[String(employeeId)]?.[String(day)] ?? '';

        // Setting a cell back to what the server already has is not a change —
        // dropping it keeps the dirty count honest.
        if (status === saved) {
            onChange(others);

            return;
        }

        onChange([
            ...others,
            {
                employee_id: employeeId,
                day,
                status: status === '' ? null : status,
            },
        ]);
    };

    const advance = (employee: AttendanceEmployee, day: number) => {
        const current = valueOf(employee.id, day);
        const next = cycle[(cycle.indexOf(current) + 1) % cycle.length];

        set(employee.id, day, next);
    };

    const fillRow = (employee: AttendanceEmployee) => {
        days.forEach((day) => {
            if (markable(employee, day)) {
                set(employee.id, day, bulk);
            }
        });
    };

    return (
        <div className="space-y-3">
            {!readOnly && (
                <div className="flex flex-wrap items-center gap-2 text-sm print:hidden">
                    <span className="text-coffee-800/70">
                        Click a cell to cycle. Fill a whole row with:
                    </span>
                    <select
                        value={bulk}
                        onChange={(event) => setBulk(event.target.value)}
                        className="h-8 rounded-md border border-coffee-200 bg-white px-2 text-sm"
                        aria-label="Bulk fill status"
                    >
                        {statuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                    </select>
                </div>
            )}

            <div className="overflow-x-auto rounded-lg border border-coffee-100 bg-white shadow-sm">
                <table className="text-sm">
                    <thead>
                        <tr>
                            <th className="sticky left-0 z-10 bg-coffee-500 px-3 py-2 text-left text-xs font-bold text-white uppercase">
                                Employee
                            </th>
                            {days.map((day) => (
                                <th
                                    key={day}
                                    className={`w-8 px-1 py-2 text-center text-xs font-bold ${
                                        nonWorking.has(day)
                                            ? 'bg-coffee-700 text-white/60'
                                            : 'bg-coffee-500 text-white'
                                    }`}
                                >
                                    {day}
                                </th>
                            ))}
                            {!readOnly && (
                                <th className="bg-coffee-500 px-2 py-2 text-xs font-bold text-white uppercase print:hidden">
                                    Fill
                                </th>
                            )}
                        </tr>
                    </thead>

                    <tbody className="divide-y divide-coffee-100">
                        {employees.length === 0 && (
                            <tr>
                                <td
                                    colSpan={daysInMonth + 2}
                                    className="px-4 py-10 text-center text-coffee-800/60"
                                >
                                    Nobody was employed during this month.
                                </td>
                            </tr>
                        )}

                        {employees.map((employee) => (
                            <tr key={employee.id}>
                                <th
                                    scope="row"
                                    className="sticky left-0 z-10 bg-white px-3 py-1.5 text-left font-medium whitespace-nowrap text-coffee-900"
                                >
                                    {employee.name}
                                    <span className="ml-2 text-xs font-normal text-coffee-800/50">
                                        {employee.employeeCode}
                                    </span>
                                </th>

                                {days.map((day) => {
                                    const value = valueOf(employee.id, day);
                                    const canMark = markable(employee, day);
                                    const outOfRange =
                                        day < employee.firstDay ||
                                        day > employee.lastDay;

                                    return (
                                        <td key={day} className="p-0.5">
                                            <button
                                                type="button"
                                                disabled={!canMark}
                                                onClick={() =>
                                                    advance(employee, day)
                                                }
                                                title={
                                                    outOfRange
                                                        ? 'Not employed on this day'
                                                        : nonWorking.has(day)
                                                          ? 'Not a working day'
                                                          : undefined
                                                }
                                                className={`flex size-7 items-center justify-center rounded text-xs font-semibold transition-colors ${
                                                    outOfRange
                                                        ? 'cursor-not-allowed bg-coffee-50/60 text-transparent'
                                                        : nonWorking.has(day)
                                                          ? 'cursor-not-allowed bg-coffee-100/70 text-coffee-800/40'
                                                          : value === ''
                                                            ? 'bg-coffee-50 text-coffee-800/30 hover:bg-coffee-100'
                                                            : statusStyles[
                                                                  value
                                                              ]
                                                }`}
                                            >
                                                {value === ''
                                                    ? '·'
                                                    : (initials.get(value) ??
                                                      '?')}
                                            </button>
                                        </td>
                                    );
                                })}

                                {!readOnly && (
                                    <td className="px-2 print:hidden">
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => fillRow(employee)}
                                        >
                                            Fill
                                        </Button>
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <ul className="flex flex-wrap items-center gap-4 text-xs text-coffee-800/70">
                {statuses.map((status) => (
                    <li
                        key={status.value}
                        className="flex items-center gap-1.5"
                    >
                        <span
                            className={`flex size-5 items-center justify-center rounded text-[10px] font-semibold ${statusStyles[status.value]}`}
                        >
                            {status.initial}
                        </span>
                        {status.label}
                    </li>
                ))}
                <li className="flex items-center gap-1.5">
                    <span className="flex size-5 items-center justify-center rounded bg-coffee-100/70 text-[10px] text-coffee-800/40">
                        —
                    </span>
                    Weekend or holiday
                </li>
            </ul>
        </div>
    );
}
