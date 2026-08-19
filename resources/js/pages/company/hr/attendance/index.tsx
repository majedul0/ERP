import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useCan } from '@/modules/company';
import type {
    AttendanceEmployee,
    AttendanceStatusOption,
    PendingMark,
} from '@/modules/hr';
import { AttendanceGrid } from '@/modules/hr';
import { index, summary, update } from '@/routes/attendance';
import { index as holidaysIndex } from '@/routes/holidays';

const selectClasses =
    'h-9 rounded-md border border-coffee-200 bg-white px-3 text-sm text-coffee-900 shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none';

const months = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

type Props = {
    month: string;
    monthLabel: string;
    daysInMonth: number;
    nonWorkingDays: number[];
    workingDays: number;
    statuses: AttendanceStatusOption[];
    employees: AttendanceEmployee[];
    marks: Record<string, Record<string, string>>;
};

export default function Attendance({
    month,
    monthLabel,
    daysInMonth,
    nonWorkingDays,
    workingDays,
    statuses,
    employees,
    marks,
}: Props) {
    const can = useCan();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const manages = can('attendance:manage');

    const [pending, setPending] = useState<PendingMark[]>([]);
    const [saving, setSaving] = useState(false);

    const [year, setYear] = useState(Number(month.slice(0, 4)));
    const [monthNumber, setMonthNumber] = useState(Number(month.slice(5, 7)));

    const openMonth = (nextYear: number, nextMonth: number) => {
        const target = `${nextYear}-${String(nextMonth).padStart(2, '0')}`;

        // Changing month while holding unsaved cells would drop them silently.
        if (
            pending.length > 0 &&
            !window.confirm(
                `${pending.length} unsaved day(s) will be discarded. Continue?`,
            )
        ) {
            return;
        }

        setPending([]);
        router.get(
            index(teamSlug).url,
            { month: target },
            { preserveState: false },
        );
    };

    const save = () => {
        setSaving(true);

        router.put(
            update(teamSlug).url,
            { month, marks: pending },
            {
                preserveScroll: true,
                onSuccess: () => setPending([]),
                onFinish: () => setSaving(false),
            },
        );
    };

    const latest = new Date().getFullYear() + 1;
    const years = Array.from({ length: 8 }, (_, index) => latest - index);

    return (
        <>
            <Head title={`Attendance — ${monthLabel}`} />

            <div className="mb-5 flex flex-wrap items-end justify-between gap-3 print:hidden">
                <div className="flex flex-wrap items-end gap-2">
                    <div className="grid gap-1">
                        <Label htmlFor="month" className="text-xs">
                            Month
                        </Label>
                        <select
                            id="month"
                            className={selectClasses}
                            value={monthNumber}
                            onChange={(event) => {
                                const next = Number(event.target.value);
                                setMonthNumber(next);
                                openMonth(year, next);
                            }}
                        >
                            {months.map((name, position) => (
                                <option key={name} value={position + 1}>
                                    {name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-1">
                        <Label htmlFor="year" className="text-xs">
                            Year
                        </Label>
                        <select
                            id="year"
                            className={selectClasses}
                            value={year}
                            onChange={(event) => {
                                const next = Number(event.target.value);
                                setYear(next);
                                openMonth(next, monthNumber);
                            }}
                        >
                            {years.map((option) => (
                                <option key={option} value={option}>
                                    {option}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button asChild variant="outline">
                        <Link href={`${summary(teamSlug).url}?month=${month}`}>
                            Summary
                        </Link>
                    </Button>

                    {manages && (
                        <Button asChild variant="outline">
                            <Link href={holidaysIndex(teamSlug)}>
                                Working week
                            </Link>
                        </Button>
                    )}

                    {manages && (
                        <Button
                            onClick={save}
                            disabled={pending.length === 0 || saving}
                            className="bg-coffee-600 hover:bg-coffee-700"
                            data-test="save-attendance"
                        >
                            {pending.length === 0
                                ? 'Saved'
                                : `Save ${pending.length} change(s)`}
                        </Button>
                    )}
                </div>
            </div>

            <div className="mb-4">
                <h1 className="text-xl font-bold text-coffee-900">
                    Attendance — {monthLabel}
                </h1>
                <p className="text-sm text-coffee-800/60">
                    {workingDays} working days · {employees.length} employed
                    this month
                </p>
            </div>

            <AttendanceGrid
                employees={employees}
                daysInMonth={daysInMonth}
                nonWorkingDays={nonWorkingDays}
                statuses={statuses}
                marks={marks}
                pending={pending}
                onChange={setPending}
                readOnly={!manages}
            />

            <p className="mt-4 text-xs text-coffee-800/60">
                Salaried staff are marked by exception — an unmarked working day
                counts as present. A daily wage is the opposite: an unmarked day
                is a day not paid, so mark those in full.
            </p>
        </>
    );
}
