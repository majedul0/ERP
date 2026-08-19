import { Head, Link, router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatMoney, formatSaleDate } from '@/lib/format';
import { useCan, useCompanyBrand } from '@/modules/company';
import type { SalaryPaymentRow } from '@/modules/hr';
import { index as payrollIndex } from '@/routes/payroll';
import { create, destroy } from '@/routes/salary-payments';

const headCell =
    'bg-coffee-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase whitespace-nowrap';
const bodyCell = 'px-4 py-3 whitespace-nowrap text-coffee-900';

export default function SalaryPayments({
    payments,
    total,
}: {
    payments: SalaryPaymentRow[];
    total: number;
}) {
    const brand = useCompanyBrand();
    const can = useCan();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const manages = can('payroll:manage');
    const money = (amount: number) => formatMoney(amount, brand.currencySymbol);

    return (
        <>
            <Head title="Salary payments" />

            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-coffee-900">
                        Salary payments
                    </h1>
                    <p className="text-sm text-coffee-800/60">
                        {money(total)} paid in wages, advances and bonuses
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button asChild variant="outline">
                        <Link href={payrollIndex(teamSlug)}>Payroll</Link>
                    </Button>

                    {manages && (
                        <Button
                            asChild
                            className="bg-coffee-600 hover:bg-coffee-700"
                        >
                            <Link href={create(teamSlug)}>
                                + Record payment
                            </Link>
                        </Button>
                    )}
                </div>
            </div>

            <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[52rem] text-sm">
                        <thead>
                            <tr>
                                <th className={headCell}>Date</th>
                                <th className={headCell}>Employee</th>
                                <th className={headCell}>Kind</th>
                                <th className={`${headCell} text-right`}>
                                    Amount
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Outstanding
                                </th>
                                <th className={headCell}>Paid from</th>
                                <th className={headCell}>
                                    <span className="sr-only">Remove</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-coffee-100">
                            {payments.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-10 text-center text-coffee-800/60"
                                    >
                                        Nothing has been paid yet.
                                    </td>
                                </tr>
                            )}

                            {payments.map((payment) => (
                                <tr
                                    key={payment.id}
                                    className="transition-colors hover:bg-coffee-50/60"
                                >
                                    <td className={bodyCell}>
                                        {formatSaleDate(payment.paidOn)}
                                    </td>
                                    <td className={`${bodyCell} font-medium`}>
                                        {payment.employeeName}
                                        <span className="ml-2 text-xs font-normal text-coffee-800/50">
                                            {payment.employeeCode}
                                        </span>
                                    </td>
                                    <td className={bodyCell}>
                                        {payment.kindLabel}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right font-medium tabular-nums`}
                                    >
                                        {money(payment.amount)}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right tabular-nums`}
                                    >
                                        {payment.outstanding === null
                                            ? '—'
                                            : money(payment.outstanding)}
                                    </td>
                                    <td className={bodyCell}>
                                        {payment.bankName ?? 'Cash'}
                                    </td>
                                    <td className={bodyCell}>
                                        {manages && (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="text-red-700 hover:text-red-800"
                                                onClick={() =>
                                                    router.delete(
                                                        destroy({
                                                            current_team:
                                                                teamSlug,
                                                            payment: payment.id,
                                                        }).url,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Remove
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <p className="mt-4 text-xs text-coffee-800/60">
                This is the only place wages leave the company, which is why the
                financial report counts them from here and the expense form no
                longer offers a Salary category.
            </p>
        </>
    );
}
