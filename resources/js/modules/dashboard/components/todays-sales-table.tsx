import { Link } from '@inertiajs/react';
import { formatAmount, formatSaleDate, formatSaleDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { DeliveryStatus, TodaySale } from '../types';

const statusStyles: Record<DeliveryStatus, { label: string; dot: string }> = {
    delivered: { label: 'Delivered', dot: 'bg-emerald-500' },
    pending: { label: 'Pending', dot: 'bg-amber-500' },
    cancelled: { label: 'Cancelled', dot: 'bg-red-500' },
    returned: { label: 'Returned', dot: 'bg-coffee-400' },
};

const headCell =
    'sticky top-0 z-10 bg-coffee-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase';
const bodyCell = 'px-4 py-3 whitespace-nowrap text-coffee-900';

function DeliveryStatusCell({ status }: { status: DeliveryStatus }) {
    const style = statusStyles[status];

    return (
        <span className="flex items-center gap-2">
            <span className={cn('size-1.5 rounded-full', style.dot)} />
            {style.label}
        </span>
    );
}

type Props = {
    sales: TodaySale[];
    title?: string;
    emptyMessage?: string;
};

export default function TodaysSalesTable({
    sales,
    title = "Today's Sales",
    emptyMessage = 'No sales recorded today.',
}: Props) {
    return (
        <section className="mt-6">
            <h2 className="mb-3 text-lg font-bold text-coffee-900">{title}</h2>

            <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm">
                <div className="max-h-[26rem] overflow-auto">
                    <table className="w-full min-w-[64rem] border-collapse text-sm">
                        <thead>
                            <tr>
                                <th scope="col" className={headCell}>
                                    Id
                                </th>
                                <th scope="col" className={headCell}>
                                    Distributor Name
                                </th>
                                <th scope="col" className={headCell}>
                                    Proprietor Name
                                </th>
                                <th scope="col" className={headCell}>
                                    Sale Datetime
                                </th>
                                <th
                                    scope="col"
                                    className={cn(headCell, 'text-right')}
                                >
                                    Amount
                                </th>
                                <th scope="col" className={headCell}>
                                    Delivery Status
                                </th>
                                <th scope="col" className={headCell}>
                                    Details
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-coffee-100">
                            {sales.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-10 text-center text-coffee-800/60"
                                    >
                                        {emptyMessage}
                                    </td>
                                </tr>
                            )}

                            {sales.map((sale) => (
                                <tr
                                    key={sale.id}
                                    className="transition-colors hover:bg-coffee-50/60"
                                >
                                    <td className={cn(bodyCell, 'font-medium')}>
                                        {sale.invoiceNumber}
                                    </td>
                                    <td className={bodyCell}>
                                        {sale.distributorUrl ? (
                                            <Link
                                                href={sale.distributorUrl}
                                                className="font-medium text-coffee-700 underline underline-offset-4 hover:text-coffee-900"
                                            >
                                                {sale.distributorName}
                                            </Link>
                                        ) : (
                                            <span className="font-medium">
                                                {sale.distributorName}
                                            </span>
                                        )}
                                    </td>
                                    <td className={bodyCell}>
                                        {sale.proprietorName}
                                    </td>
                                    <td className={bodyCell}>
                                        {sale.createdAt
                                            ? formatSaleDateTime(sale.createdAt)
                                            : formatSaleDate(sale.saleDate)}
                                    </td>
                                    <td
                                        className={cn(
                                            bodyCell,
                                            'text-right font-medium tabular-nums',
                                        )}
                                    >
                                        {formatAmount(sale.amount)}
                                    </td>
                                    <td className={bodyCell}>
                                        <DeliveryStatusCell
                                            status={sale.deliveryStatus}
                                        />
                                    </td>
                                    <td className={bodyCell}>
                                        {sale.detailUrl ? (
                                            <Link
                                                href={sale.detailUrl}
                                                className="text-coffee-700 underline underline-offset-4 hover:text-coffee-900"
                                            >
                                                Detail
                                            </Link>
                                        ) : (
                                            <span className="text-coffee-800/45">
                                                Detail
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    );
}
