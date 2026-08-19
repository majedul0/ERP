import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatAmount } from '@/lib/format';
import type { AttendanceSummaryReport } from '@/modules/hr';
import { excel, index } from '@/routes/attendance';

const headCell =
    'bg-coffee-500 px-3 py-3 text-left text-xs font-bold tracking-wide text-white uppercase whitespace-nowrap';
const bodyCell = 'px-3 py-2.5 whitespace-nowrap text-coffee-900';
const numberCell = `${bodyCell} text-right tabular-nums`;

export default function AttendanceSummary({
    summary,
}: {
    summary: AttendanceSummaryReport;
}) {
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    const totals = summary.rows.reduce(
        (running, row) => ({
            present: running.present + row.present,
            halfDays: running.halfDays + row.halfDays,
            paidLeave: running.paidLeave + row.paidLeave,
            unpaidLeave: running.unpaidLeave + row.unpaidLeave,
            absent: running.absent + row.absent,
            unmarked: running.unmarked + row.unmarked,
        }),
        {
            present: 0,
            halfDays: 0,
            paidLeave: 0,
            unpaidLeave: 0,
            absent: 0,
            unmarked: 0,
        },
    );

    // Only daily-wage staff lose money to an unmarked day; for salaried staff
    // it is the normal state and warning about it would be noise.
    const unmarkedWages = summary.rows.filter(
        (row) => row.salaryType === 'daily' && row.unmarked > 0,
    );

    return (
        <>
            <Head title={`Attendance Summary — ${summary.monthLabel}`} />

            <div className="mb-5 flex flex-wrap items-center justify-between gap-3 print:hidden">
                <h1 className="text-xl font-bold text-coffee-900">
                    Attendance Summary
                </h1>

                <div className="flex flex-wrap items-center gap-2">
                    <Button asChild variant="outline">
                        <Link
                            href={`${index(teamSlug).url}?month=${summary.month}`}
                        >
                            Back to grid
                        </Link>
                    </Button>

                    {/* A file download, so a plain anchor: an Inertia visit
                        would try to parse the CSV as a page. */}
                    <Button asChild variant="outline">
                        <a
                            href={`${excel(teamSlug).url}?month=${summary.month}`}
                        >
                            Excel
                        </a>
                    </Button>

                    <Button
                        onClick={() => window.print()}
                        className="bg-coffee-600 hover:bg-coffee-700"
                    >
                        Print
                    </Button>
                </div>
            </div>

            <div className="mb-5 text-center">
                <h2 className="text-2xl font-bold text-coffee-900">
                    Attendance Summary
                </h2>
                <p className="text-lg font-semibold text-coffee-800">
                    {summary.monthLabel}
                </p>
                <p className="text-sm text-coffee-800/60">
                    {summary.workingDays} working days
                </p>
            </div>

            {unmarkedWages.length > 0 && (
                <p className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 print:hidden">
                    <strong>
                        {unmarkedWages.length} daily-wage employee(s)
                    </strong>{' '}
                    have unmarked working days. A daily wage is paid per day
                    worked, so those days will earn nothing when payroll runs.
                </p>
            )}

            <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm print:rounded-none print:border-0 print:shadow-none">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[52rem] text-sm">
                        <thead>
                            <tr>
                                <th className={headCell}>Staff No.</th>
                                <th className={headCell}>Name</th>
                                <th className={headCell}>Department</th>
                                <th className={`${headCell} text-right`}>
                                    Expected
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Present
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Half
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Paid leave
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Unpaid
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Absent
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Unmarked
                                </th>
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-coffee-100">
                            {summary.rows.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={10}
                                        className="px-4 py-10 text-center text-coffee-800/60"
                                    >
                                        Nobody was employed during this month.
                                    </td>
                                </tr>
                            )}

                            {summary.rows.map((row) => (
                                <tr
                                    key={row.id}
                                    className="transition-colors hover:bg-coffee-50/60"
                                >
                                    <td className={bodyCell}>
                                        {row.employeeCode}
                                    </td>
                                    <td className={`${bodyCell} font-medium`}>
                                        {row.name}
                                    </td>
                                    <td className={bodyCell}>
                                        {row.departmentName ?? '—'}
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(row.expectedDays)}
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(row.present)}
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(row.halfDays)}
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(row.paidLeave)}
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(row.unpaidLeave)}
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(row.absent)}
                                    </td>
                                    <td
                                        className={`${numberCell} ${
                                            row.salaryType === 'daily' &&
                                            row.unmarked > 0
                                                ? 'font-bold text-amber-700'
                                                : ''
                                        }`}
                                    >
                                        {formatAmount(row.unmarked)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>

                        {summary.rows.length > 0 && (
                            <tfoot>
                                <tr className="border-t-2 border-coffee-200 bg-coffee-50 font-bold">
                                    <td className={bodyCell} colSpan={4}>
                                        Total
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(totals.present)}
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(totals.halfDays)}
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(totals.paidLeave)}
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(totals.unpaidLeave)}
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(totals.absent)}
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(totals.unmarked)}
                                    </td>
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>
            </div>
        </>
    );
}
