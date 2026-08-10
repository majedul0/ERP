import type { ReactNode } from 'react';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';

type Row = {
    label: string;
    value: number;
    /** Rendered heavier, with a rule above it. */
    emphasis?: boolean;
    hint?: string;
    /** Replaces the formatted figure — an input, on the create screen. */
    control?: ReactNode;
};

export default function InvoiceTotals({
    rows,
    currencySymbol,
    className,
}: {
    rows: Row[];
    currencySymbol: string;
    className?: string;
}) {
    return (
        <dl className={cn('ml-auto w-full max-w-md', className)}>
            {rows.map((row) => (
                <div
                    key={row.label}
                    className={cn(
                        'flex items-baseline justify-between gap-6 border-t border-coffee-100 py-3',
                        row.emphasis && 'border-coffee-200',
                    )}
                >
                    <dt
                        className={cn(
                            'text-sm text-coffee-900',
                            row.emphasis && 'font-bold',
                        )}
                    >
                        {row.label}
                        {row.hint && (
                            <span className="mt-0.5 block text-xs font-normal text-coffee-800/55">
                                {row.hint}
                            </span>
                        )}
                    </dt>
                    <dd
                        className={cn(
                            'text-sm text-coffee-900 tabular-nums',
                            row.emphasis && 'text-base font-bold',
                        )}
                    >
                        {row.control ?? formatMoney(row.value, currencySymbol)}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
