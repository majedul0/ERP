import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatMoney, formatSaleDate } from '@/lib/format';
import { useCompanyBrand } from '@/modules/company';
import type { Expense, WageRow } from '@/modules/finance';
import { create, edit } from '@/routes/expenses';
import { index as salaryPaymentsIndex } from '@/routes/salary-payments';

const headCell =
    'bg-coffee-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase';
const bodyCell = 'px-4 py-3 whitespace-nowrap text-coffee-900';

/**
 * What the company has spent.
 *
 * Wages appear here alongside the rest, but they are **read from the payroll
 * screens, not stored twice**: a salary payment is recorded once, in Payroll,
 * and this page shows it so the spending picture is complete. That is why those
 * rows have no Edit link — the payment screen owns them, and a row editable in
 * two places is a row that will eventually disagree with itself.
 */
export default function Expenses({
    expenses,
    wages,
    total,
    wagesTotal,
}: {
    expenses: Expense[];
    wages: WageRow[];
    total: number;
    wagesTotal: number;
}) {
    const brand = useCompanyBrand();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const money = (amount: number) => formatMoney(amount, brand.currencySymbol);

    // One list, newest first, whichever table a row came from — the reader
    // wants "what did we spend", not "which screen recorded it".
    const rows = [
        ...expenses.map((expense) => ({
            key: `expense-${expense.id}`,
            spentOn: expense.spentOn,
            categoryLabel: expense.categoryLabel,
            description: expense.description,
            bankName: expense.bankName,
            amount: expense.amount,
            editHref: edit({ current_team: teamSlug, expense: expense.id }),
        })),
        ...wages.map((wage) => ({
            key: `wage-${wage.id}`,
            spentOn: wage.spentOn,
            categoryLabel: wage.categoryLabel,
            description: wage.description,
            bankName: wage.bankName,
            amount: wage.amount,
            editHref: null,
        })),
    ].sort((a, b) => b.spentOn.localeCompare(a.spentOn));

    return (
        <>
            <Head title="Expenses" />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-coffee-900">
                        Expenses
                    </h1>
                    <p className="mt-1 font-display text-sm text-coffee-800/60">
                        Spent to date:{' '}
                        <span className="font-semibold">
                            {money(total + wagesTotal)}
                        </span>
                        {wagesTotal > 0 && (
                            <> — including {money(wagesTotal)} in wages</>
                        )}
                    </p>
                </div>
                <Button asChild className="bg-coffee-600 hover:bg-coffee-700">
                    <Link href={create(teamSlug)}>+ Add Expense</Link>
                </Button>
            </div>

            <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[52rem] text-sm">
                        <thead>
                            <tr>
                                <th className={headCell}>Date</th>
                                <th className={headCell}>Category</th>
                                <th className={headCell}>Description</th>
                                <th className={headCell}>Paid From</th>
                                <th className={`${headCell} text-right`}>
                                    Amount
                                </th>
                                <th className={headCell}>
                                    <span className="sr-only">Edit</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-coffee-100">
                            {rows.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-coffee-800/60"
                                    >
                                        No expenses recorded yet.
                                    </td>
                                </tr>
                            )}

                            {rows.map((row) => (
                                <tr
                                    key={row.key}
                                    className="transition-colors hover:bg-coffee-50/60"
                                >
                                    <td className={bodyCell}>
                                        {formatSaleDate(row.spentOn)}
                                    </td>
                                    <td className={bodyCell}>
                                        <span className="rounded-full bg-coffee-50 px-2 py-0.5 text-xs font-semibold text-coffee-800">
                                            {row.categoryLabel}
                                        </span>
                                    </td>
                                    <td className={`${bodyCell} font-medium`}>
                                        {row.description}
                                    </td>
                                    <td className={bodyCell}>
                                        {row.bankName ?? 'Cash'}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right font-medium tabular-nums`}
                                    >
                                        {money(row.amount)}
                                    </td>
                                    <td className={bodyCell}>
                                        {row.editHref ? (
                                            <Link
                                                href={row.editHref}
                                                className="text-coffee-700 underline underline-offset-4 hover:text-coffee-900"
                                            >
                                                Edit
                                            </Link>
                                        ) : (
                                            <Link
                                                href={salaryPaymentsIndex(
                                                    teamSlug,
                                                )}
                                                className="text-coffee-700 underline underline-offset-4 hover:text-coffee-900"
                                            >
                                                In Payroll
                                            </Link>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <p className="mt-3 text-xs text-coffee-800/60">
                Wages are recorded in Payroll and shown here so the spending
                picture is complete. They are counted once — the financial
                report reads them from the payroll side, which is why the Salary
                category is no longer offered when adding an expense.
            </p>
        </>
    );
}
