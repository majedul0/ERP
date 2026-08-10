import { Head, Link, usePage } from '@inertiajs/react';
import { formatAmount, formatSaleDate } from '@/lib/format';
import type { PaymentRow } from '@/modules/payments';
import { edit } from '@/routes/payments';

const headCell =
    'bg-coffee-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase';
const bodyCell = 'px-4 py-3 whitespace-nowrap text-coffee-900';

export default function Payments({ payments }: { payments: PaymentRow[] }) {
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    return (
        <>
            <Head title="Payments Received" />

            <h1 className="mb-1 text-2xl font-bold text-coffee-900">
                Payments Received
            </h1>
            <p className="mb-6 font-display text-sm text-coffee-800/60">
                Money in from distributors. Each one reduces that distributor's
                due and appears on their statement.
            </p>

            <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[52rem] text-sm">
                        <thead>
                            <tr>
                                <th className={headCell}>Date</th>
                                <th className={headCell}>Distributor</th>
                                <th className={headCell}>Bank</th>
                                <th className={`${headCell} text-right`}>
                                    Amount
                                </th>
                                <th className={headCell}>Comment</th>
                                <th className={headCell}>
                                    <span className="sr-only">Edit</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-coffee-100">
                            {payments.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-coffee-800/60"
                                    >
                                        No payments recorded yet.
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
                                    <td className={bodyCell}>
                                        <Link
                                            href={payment.distributorUrl}
                                            className="font-medium text-coffee-700 underline underline-offset-4 hover:text-coffee-900"
                                        >
                                            {payment.distributorName}
                                        </Link>
                                    </td>
                                    <td className={bodyCell}>
                                        {payment.bankName ?? '—'}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right font-medium text-emerald-700 tabular-nums`}
                                    >
                                        {formatAmount(payment.amount)}
                                    </td>
                                    <td className={bodyCell}>
                                        {payment.comment ?? ''}
                                    </td>
                                    <td className={bodyCell}>
                                        <Link
                                            href={edit({
                                                current_team: teamSlug,
                                                payment: payment.id,
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
