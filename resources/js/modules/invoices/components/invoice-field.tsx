import type { ReactNode } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/**
 * The label-above-control pairing used across the invoice and product forms.
 */
export default function InvoiceField({
    label,
    htmlFor,
    hint,
    error,
    className,
    children,
}: {
    label: string;
    htmlFor?: string;
    hint?: string;
    error?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('grid gap-1.5', className)}>
            <Label htmlFor={htmlFor} className="text-coffee-900">
                {label}
            </Label>
            {children}
            {hint && !error && (
                <p className="text-xs text-coffee-800/60">{hint}</p>
            )}
            {error && <p className="text-xs text-red-600">{error}</p>}
        </div>
    );
}
