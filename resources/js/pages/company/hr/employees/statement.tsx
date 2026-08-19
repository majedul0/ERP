import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatMoney, formatSaleDate } from '@/lib/format';
import { useCompanyBrand } from '@/modules/company';
import type { EmployeeLedgerEntry } from '@/modules/hr';
import { show } from '@/routes/employees';

const headCell =
    'bg-coffee-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase whitespace-nowrap';
const bodyCell = 'px-4 py-2.5 whitespace-nowrap text-coffee-900';
const numberCell = `${bodyCell} text-right tabular-nums`;

/**
 * One person's account with the company.
 *
 * The same walk the balance is written from, so the two cannot disagree — the
 * property the distributor statement has for the same reason.
 */
export default function EmployeeStatement({
    employee,
    entries,
    totals,
}: {
    employee: {
        id: number;
        name: string;
        employeeCode: string;
        designation: string | null;
        balance: number;
    };
    entries: EmployeeLedgerEntry[];
    totals: { earned: number; paid: number; balance: number };
}) {
    const brand = useCompanyBrand();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const money = (amount: number) => formatMoney(amount, brand.currencySymbol);

    return (
        <>
            <Head title={`Account — ${employee.name}`} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
                <h1 className="text-xl font-bold text-coffee-900">
                    Employee account
                </h1>

                <div className="flex flex-wrap items-center gap-2">
                    <Button asChild variant="outline">
                        <Link
                            href={show({
                                current_team: teamSlug,
                                employee: employee.id,
                            })}
                        >
                            Back
                        </Link>
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
                    {employee.name}
                </h2>
                <p className="text-sm text-coffee-800/60">
                    {employee.employeeCode}
                    {employee.designation && ` · ${employee.designation}`}
                </p>
            </div>

            <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm print:rounded-none print:border-0 print:shadow-none">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[44rem] text-sm">
                        <thead>
                            <tr>
                                <th className={headCell}>Date</th>
                                <th className={headCell}>Particulars</th>
                                <th className={headCell}>Reference</th>
                                <th className={`${headCell} text-right`}>
                                    Earned
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Paid
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Balance
                                </th>
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-coffee-100">
                            {entries.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-coffee-800/60"
                                    >
                                        Nothing on this account yet.
                                    </td>
                                </tr>
                            )}

                            {entries.map((entry) => (
                                <tr
                                    key={`${entry.type}-${entry.id}`}
                                    className="transition-colors hover:bg-coffee-50/60"
                                >
                                    <td className={bodyCell}>
                                        {formatSaleDate(entry.occurredOn)}
                                    </td>
                                    <td className={bodyCell}>
                                        {entry.description}
                                    </td>
                                    <td className={bodyCell}>
                                        {entry.reference}
                                    </td>
                                    <td className={numberCell}>
                                        {entry.debit > 0
                                            ? money(entry.debit)
                                            : '—'}
                                    </td>
                                    <td className={numberCell}>
                                        {entry.credit > 0
                                            ? money(entry.credit)
                                            : '—'}
                                    </td>
                                    <td className={`${numberCell} font-medium`}>
                                        {money(entry.balanceAfter)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>

                        {entries.length > 0 && (
                            <tfoot>
                                <tr className="border-t-2 border-coffee-200 bg-coffee-50 font-bold">
                                    <td className={bodyCell} colSpan={3}>
                                        Total
                                    </td>
                                    <td className={numberCell}>
                                        {money(totals.earned)}
                                    </td>
                                    <td className={numberCell}>
                                        {money(totals.paid)}
                                    </td>
                                    <td className={numberCell}>
                                        {money(totals.balance)}
                                    </td>
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>
            </div>

            <p className="mt-4 text-xs text-coffee-800/60">
                A negative balance means they have drawn more than they have
                earned — an advance still to be recovered. Only approved payroll
                months appear here; a draft is a working figure, not a debt.
            </p>
        </>
    );
}
