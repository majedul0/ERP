import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';

type Row = {
    label: string;
    value: number;
    /** Rendered heavier, with a rule above it. */
    emphasis?: boolean;
    hint?: string;
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
                        'flex items-baseline justify-between gap-6 border-t border-ocean-100 py-3',
                        row.emphasis && 'border-ocean-200',
                    )}
                >
                    <dt
                        className={cn(
                            'text-sm text-ocean-900',
                            row.emphasis && 'font-bold',
                        )}
                    >
                        {row.label}
                        {row.hint && (
                            <span className="mt-0.5 block text-xs font-normal text-ocean-800/55">
                                {row.hint}
                            </span>
                        )}
                    </dt>
                    <dd
                        className={cn(
                            'text-sm text-ocean-900 tabular-nums',
                            row.emphasis && 'text-base font-bold',
                        )}
                    >
                        {formatMoney(row.value, currencySymbol)}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
