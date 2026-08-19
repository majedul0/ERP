import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import HolidayController from '@/actions/App/Http/Controllers/Hr/HolidayController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatSaleDate } from '@/lib/format';
import { useCan } from '@/modules/company';
import type { HolidayRow } from '@/modules/hr';
import { index as attendanceIndex } from '@/routes/attendance';

type Weekday = { value: number; label: string };

/**
 * The working week, and the days off inside it.
 *
 * Both on one screen because they answer the same question. Saving either
 * re-derives every month that has already passed — the attendance summary and
 * the next payroll run recount from these, rather than from a figure frozen
 * when the month closed.
 */
export default function Holidays({
    year,
    weekdays,
    weekendDays,
    overtimeHourlyRate,
    holidays,
}: {
    year: number;
    weekdays: Weekday[];
    weekendDays: number[];
    overtimeHourlyRate: number | null;
    holidays: HolidayRow[];
}) {
    const can = useCan();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const manages = can('attendance:manage');

    const [weekend, setWeekend] = useState<number[]>(weekendDays);
    const [overtime, setOvertime] = useState(
        overtimeHourlyRate === null ? '' : String(overtimeHourlyRate),
    );

    const toggle = (day: number) =>
        setWeekend((current) =>
            current.includes(day)
                ? current.filter((value) => value !== day)
                : [...current, day],
        );

    const saveSettings = () =>
        router.put(
            HolidayController.updateSettings.url(teamSlug),
            {
                weekend_days: weekend,
                overtime_hourly_rate: overtime === '' ? null : Number(overtime),
            },
            { preserveScroll: true },
        );

    const remove = (holiday: HolidayRow) =>
        router.delete(
            HolidayController.destroy.url({
                current_team: teamSlug,
                holiday: holiday.id,
            }),
            { preserveScroll: true },
        );

    return (
        <>
            <Head title="Working week" />

            <div className="mx-auto w-full max-w-3xl">
                <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-xl font-bold text-coffee-900">
                        Working week
                    </h1>
                    <Button asChild variant="outline">
                        <Link href={attendanceIndex(teamSlug)}>Attendance</Link>
                    </Button>
                </div>

                <div className="mb-6 rounded-lg border border-coffee-100 bg-white p-5 shadow-sm">
                    <h2 className="mb-1 text-base font-bold text-coffee-900">
                        Weekend
                    </h2>
                    <p className="mb-3 text-sm text-coffee-800/60">
                        Days nobody is expected to work. Payroll divides a
                        monthly salary by the working days that remain.
                    </p>

                    <div className="flex flex-wrap gap-2">
                        {weekdays.map((day) => (
                            <button
                                key={day.value}
                                type="button"
                                disabled={!manages}
                                onClick={() => toggle(day.value)}
                                className={`rounded-md border px-3 py-1.5 text-sm transition-colors ${
                                    weekend.includes(day.value)
                                        ? 'border-coffee-600 bg-coffee-600 text-white'
                                        : 'border-coffee-200 bg-white text-coffee-800 hover:bg-coffee-50'
                                } disabled:cursor-not-allowed disabled:opacity-60`}
                            >
                                {day.label}
                            </button>
                        ))}
                    </div>

                    <div className="mt-4 grid max-w-xs gap-1.5">
                        <Label htmlFor="overtime" className="text-coffee-900">
                            Overtime rate per hour
                        </Label>
                        <Input
                            id="overtime"
                            type="number"
                            min={0}
                            step={1}
                            value={overtime}
                            disabled={!manages}
                            onChange={(event) =>
                                setOvertime(event.target.value)
                            }
                            placeholder="Optional"
                        />
                        <p className="text-xs text-coffee-800/60">
                            The default used when overtime hours are typed on a
                            payroll line. Whole amounts only.
                        </p>
                    </div>

                    {manages && (
                        <Button
                            onClick={saveSettings}
                            className="mt-4 bg-coffee-600 hover:bg-coffee-700"
                            data-test="save-working-week"
                        >
                            Save working week
                        </Button>
                    )}
                </div>

                <div className="rounded-lg border border-coffee-100 bg-white p-5 shadow-sm">
                    <h2 className="mb-1 text-base font-bold text-coffee-900">
                        Holidays in {year}
                    </h2>
                    <p className="mb-4 text-sm text-coffee-800/60">
                        Days off beyond the weekend. Adding or removing one
                        recounts every month it falls in.
                    </p>

                    {manages && (
                        <Form
                            {...HolidayController.store.form(teamSlug)}
                            options={{ preserveScroll: true }}
                            resetOnSuccess
                            className="mb-5"
                        >
                            {({ processing, errors }) => (
                                <div className="flex flex-wrap items-end gap-3">
                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor="date"
                                            className="text-coffee-900"
                                        >
                                            Date
                                        </Label>
                                        <Input
                                            id="date"
                                            name="date"
                                            type="date"
                                            required
                                        />
                                        <InputError message={errors.date} />
                                    </div>

                                    <div className="grid flex-1 gap-1.5">
                                        <Label
                                            htmlFor="name"
                                            className="text-coffee-900"
                                        >
                                            Occasion
                                        </Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            placeholder="Eid-ul-Fitr"
                                            data-test="holiday-name"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        data-test="add-holiday"
                                        className="bg-coffee-600 hover:bg-coffee-700"
                                    >
                                        Add
                                    </Button>
                                </div>
                            )}
                        </Form>
                    )}

                    <ul className="divide-y divide-coffee-100 border-t border-coffee-100">
                        {holidays.length === 0 && (
                            <li className="py-6 text-center text-sm text-coffee-800/60">
                                No holidays recorded for {year}.
                            </li>
                        )}

                        {holidays.map((holiday) => (
                            <li
                                key={holiday.id}
                                className="flex items-center gap-3 py-2.5"
                            >
                                <span className="w-28 text-sm text-coffee-800/70 tabular-nums">
                                    {formatSaleDate(holiday.date)}
                                </span>
                                <span className="font-medium text-coffee-900">
                                    {holiday.name}
                                </span>

                                {manages && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        className="ml-auto text-red-700 hover:text-red-800"
                                        onClick={() => remove(holiday)}
                                    >
                                        Remove
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </>
    );
}
