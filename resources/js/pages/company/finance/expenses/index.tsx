import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatMoney, formatSaleDate } from '@/lib/format';
import { useCompanyBrand } from '@/modules/company';
import type { Expense } from '@/modules/finance';
import { create, edit } from '@/routes/expenses';

const headCell =
    'bg-coffee-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase';
const bodyCell = 'px-4 py-3 whitespace-nowrap text-coffee-900';

export default function Expenses({
    expenses,
    total,
}: {
    expenses: Expense[];
    total: number;
}) {
    const brand = useCompanyBrand();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

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
                            {formatMoney(total, brand.currencySymbol)}
                        </span>
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
                            {expenses.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-coffee-800/60"
                                    >
                                        No expenses recorded yet.
                                    </td>
                                </tr>
                            )}

                            {expenses.map((expense) => (
                                <tr
                                    key={expense.id}
                                    className="transition-colors hover:bg-coffee-50/60"
                                >
                                    <td className={bodyCell}>
                                        {formatSaleDate(expense.spentOn)}
                                    </td>
                                    <td className={bodyCell}>
                                        <span className="rounded-full bg-coffee-50 px-2 py-0.5 text-xs font-semibold text-coffee-800">
                                            {expense.categoryLabel}
                                        </span>
                                    </td>
                                    <td className={`${bodyCell} font-medium`}>
                                        {expense.description}
                                    </td>
                                    <td className={bodyCell}>
                                        {expense.bankName ?? 'Cash'}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right font-medium tabular-nums`}
                                    >
                                        {formatMoney(
                                            expense.amount,
                                            brand.currencySymbol,
                                        )}
                                    </td>
                                    <td className={bodyCell}>
                                        <Link
                                            href={edit({
                                                current_team: teamSlug,
                                                expense: expense.id,
                                            })}
                                            className="text-coffee-700 underline underline-offset-4 hover:text-coffee-900"
                                        >
                                            Edit
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
