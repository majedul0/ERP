import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatMoney, formatSaleDate } from '@/lib/format';
import { useCompanyBrand } from '@/modules/company';
import type { VendorPayment } from '@/modules/vendors';
import { create, edit } from '@/routes/vendor-payments';

const headCell =
    'bg-coffee-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase';
const bodyCell = 'px-4 py-3 whitespace-nowrap text-coffee-900';

export default function VendorPayments({
    payments,
    total,
}: {
    payments: VendorPayment[];
    total: number;
}) {
    const brand = useCompanyBrand();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    return (
        <>
            <Head title="Payments Made" />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-coffee-900">
                        Payments Made
                    </h1>
                    <p className="mt-1 font-display text-sm text-coffee-800/60">
                        Paid to date:{' '}
                        <span className="font-semibold">
                            {formatMoney(total, brand.currencySymbol)}
                        </span>
                    </p>
                </div>
                <Button asChild className="bg-coffee-600 hover:bg-coffee-700">
                    <Link href={create(teamSlug)}>+ Pay Vendor</Link>
                </Button>
            </div>

            <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[52rem] text-sm">
                        <thead>
                            <tr>
                                <th className={headCell}>Date</th>
                                <th className={headCell}>Vendor</th>
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
                                    <td className={`${bodyCell} font-medium`}>
                                        <Link
                                            href={payment.vendorUrl}
                                            className="text-coffee-700 underline underline-offset-4 hover:text-coffee-900"
                                        >
                                            {payment.vendorName}
                                        </Link>
                                    </td>
                                    <td className={bodyCell}>
                                        {payment.bankName ?? 'Cash'}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right font-medium tabular-nums`}
                                    >
                                        {formatMoney(
                                            payment.amount,
                                            brand.currencySymbol,
                                        )}
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
