import { Link } from '@inertiajs/react';
import { formatAmount, formatSaleDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { StatementEntry } from '../types';

const headCell =
    'bg-ocean-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase';
const bodyCell = 'px-4 py-3 whitespace-nowrap text-ocean-900';

type Props = {
    entries: StatementEntry[];
    /** Builds the link for an invoice row; payments have nowhere to go. */
    invoiceUrl: (id: number) => string;
};

/**
 * The running account: charges, payments, and the balance after each one.
 *
 * The closing balance is the distributor's due — the same figure that lands as
 * Previous Dues on their next invoice, because both come from the same walk.
 */
export default function StatementTable({ entries, invoiceUrl }: Props) {
    return (
        <div className="overflow-hidden rounded-lg border border-ocean-100 bg-white shadow-sm">
            <div className="overflow-x-auto">
                <table className="w-full min-w-[52rem] text-sm">
                    <thead>
                        <tr>
                            <th className={headCell}>Date</th>
                            <th className={headCell}>Reference</th>
                            <th className={headCell}>Details</th>
                            <th className={`${headCell} text-right`}>
                                Charged
                            </th>
                            <th className={`${headCell} text-right`}>Paid</th>
                            <th className={`${headCell} text-right`}>
                                Balance
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-ocean-100">
                        {entries.length === 0 && (
                            <tr>
                                <td
                                    colSpan={6}
                                    className="px-4 py-10 text-center text-ocean-800/60"
                                >
                                    Nothing on this account yet.
                                </td>
                            </tr>
                        )}

                        {entries.map((entry) => (
                            <tr
                                key={`${entry.type}-${entry.id}`}
                                className="transition-colors hover:bg-ocean-50/60"
                            >
                                <td className={bodyCell}>
                                    {formatSaleDateTime(entry.occurredOn).split(
                                        ' ',
                                    )[0] ?? ''}
                                </td>
                                <td className={cn(bodyCell, 'font-medium')}>
                                    {entry.type === 'invoice' ? (
                                        <Link
                                            href={invoiceUrl(entry.id)}
                                            className="text-ocean-700 underline underline-offset-4 hover:text-ocean-900"
                                        >
                                            {entry.reference}
                                        </Link>
                                    ) : (
                                        entry.reference
                                    )}
                                </td>
                                <td className={bodyCell}>
                                    {entry.description}
                                </td>
                                <td
                                    className={cn(
                                        bodyCell,
                                        'text-right tabular-nums',
                                    )}
                                >
                                    {entry.debit > 0
                                        ? formatAmount(entry.debit)
                                        : ''}
                                </td>
                                <td
                                    className={cn(
                                        bodyCell,
                                        'text-right text-emerald-700 tabular-nums',
                                    )}
                                >
                                    {entry.credit > 0
                                        ? formatAmount(entry.credit)
                                        : ''}
                                </td>
                                <td
                                    className={cn(
                                        bodyCell,
                                        'text-right font-semibold tabular-nums',
                                    )}
                                >
                                    {formatAmount(entry.balanceAfter)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
