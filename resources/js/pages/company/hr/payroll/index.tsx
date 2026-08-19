import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { formatAmount, formatMoney } from '@/lib/format';
import { useCan, useCompanyBrand } from '@/modules/company';
import type { PayrollRunRow } from '@/modules/hr';
import { index as bonusesIndex } from '@/routes/bonuses';
import { open as openRun, show } from '@/routes/payroll';
import { index as paymentsIndex } from '@/routes/salary-payments';
import { index as ratesIndex } from '@/routes/salary-rates';

const selectClasses =
    'h-9 rounded-md border border-coffee-200 bg-white px-3 text-sm text-coffee-900 shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none';

const headCell =
    'bg-coffee-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase whitespace-nowrap';
const bodyCell = 'px-4 py-3 whitespace-nowrap text-coffee-900';

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

export default function Payroll({
    runs,
    currentMonth,
}: {
    runs: PayrollRunRow[];
    currentMonth: string;
}) {
    const brand = useCompanyBrand();
    const can = useCan();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const manages = can('payroll:manage');
    const money = (amount: number) => formatMoney(amount, brand.currencySymbol);

    const [month, setMonth] = useState(Number(currentMonth.slice(5, 7)));
    const [year, setYear] = useState(Number(currentMonth.slice(0, 4)));

    const latest = new Date().getFullYear() + 1;
    const years = Array.from({ length: 8 }, (_, index) => latest - index);

    const open = () =>
        router.post(openRun(teamSlug).url, {
            month: `${year}-${String(month).padStart(2, '0')}`,
        });

    return (
        <>
            <Head title="Payroll" />

            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-bold text-coffee-900">Payroll</h1>

                <div className="flex flex-wrap items-center gap-2">
                    <Button asChild variant="outline">
                        <Link href={ratesIndex(teamSlug)}>Salary rates</Link>
                    </Button>
                    <Button asChild variant="outline">
                        <Link href={paymentsIndex(teamSlug)}>Payments</Link>
                    </Button>
                    <Button asChild variant="outline">
                        <Link href={bonusesIndex(teamSlug)}>Bonuses</Link>
                    </Button>
                </div>
            </div>

            {manages && (
                <div className="mb-6 flex flex-wrap items-end gap-2 rounded-lg border border-coffee-100 bg-white p-4 shadow-sm">
                    <div className="grid gap-1">
                        <Label htmlFor="month" className="text-xs">
                            Month
                        </Label>
                        <select
                            id="month"
                            className={selectClasses}
                            value={month}
                            onChange={(event) =>
                                setMonth(Number(event.target.value))
                            }
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
                            onChange={(event) =>
                                setYear(Number(event.target.value))
                            }
                        >
                            {years.map((option) => (
                                <option key={option} value={option}>
                                    {option}
                                </option>
                            ))}
                        </select>
                    </div>

                    <Button
                        onClick={open}
                        className="bg-coffee-600 hover:bg-coffee-700"
                        data-test="open-payroll"
                    >
                        Open payroll
                    </Button>

                    <p className="ml-2 max-w-md text-xs text-coffee-800/60">
                        Opening a month builds a draft from attendance and the
                        rates in force. A draft recalculates every time you open
                        it; approving freezes it.
                    </p>
                </div>
            )}

            <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[44rem] text-sm">
                        <thead>
                            <tr>
                                <th className={headCell}>Month</th>
                                <th className={headCell}>Status</th>
                                <th className={`${headCell} text-right`}>
                                    Employees
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Net payable
                                </th>
                                <th className={headCell}>Approved</th>
                                <th className={headCell}>
                                    <span className="sr-only">Open</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-coffee-100">
                            {runs.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-coffee-800/60"
                                    >
                                        No payroll has been run yet.
                                    </td>
                                </tr>
                            )}

                            {runs.map((run) => (
                                <tr
                                    key={run.id}
                                    className="transition-colors hover:bg-coffee-50/60"
                                >
                                    <td className={`${bodyCell} font-medium`}>
                                        {run.monthLabel}
                                    </td>
                                    <td className={bodyCell}>
                                        <span
                                            className={`rounded px-2 py-0.5 text-xs font-semibold ${
                                                run.status === 'approved'
                                                    ? 'bg-emerald-100 text-emerald-900'
                                                    : 'bg-amber-100 text-amber-900'
                                            }`}
                                        >
                                            {run.statusLabel}
                                        </span>
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right tabular-nums`}
                                    >
                                        {formatAmount(run.employeeCount)}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right font-medium tabular-nums`}
                                    >
                                        {money(run.netTotal)}
                                    </td>
                                    <td className={bodyCell}>
                                        {run.approvedAt ?? '—'}
                                    </td>
                                    <td className={bodyCell}>
                                        <Link
                                            href={show({
                                                current_team: teamSlug,
                                                run: run.id,
                                            })}
                                            className="text-coffee-700 underline underline-offset-4 hover:text-coffee-900"
                                        >
                                            Open
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
