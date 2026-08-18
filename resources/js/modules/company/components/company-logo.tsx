import { cn } from '@/lib/utils';
import type { CompanyBrand } from '../types';

/**
 * The wave mark, used when a company has not uploaded a logo yet.
 *
 * Drawn from the palette rather than from fixed hexes, so a company that has
 * set its own colour gets a placeholder in that colour instead of the teal this
 * mark wore when the app still had an ocean palette — it was the last of it.
 */
function WaveMark({ className }: { className?: string }) {
    return (
        <svg
            aria-hidden="true"
            viewBox="0 0 48 48"
            fill="none"
            className={cn('h-9 w-9 shrink-0', className)}
        >
            <defs>
                <linearGradient id="mark-wave" x1="0" y1="1" x2="1" y2="0">
                    <stop
                        offset="0%"
                        style={{ stopColor: 'var(--color-coffee-800)' }}
                    />
                    <stop
                        offset="55%"
                        style={{ stopColor: 'var(--color-coffee-500)' }}
                    />
                    <stop
                        offset="100%"
                        style={{ stopColor: 'var(--color-coffee-300)' }}
                    />
                </linearGradient>
            </defs>
            <path
                d="M43 24c0 10.5-8.5 19-19 19S5 34.5 5 24 13.5 5 24 5c7.7 0 14.3 4.6 17.3 11.1"
                stroke="url(#mark-wave)"
                strokeWidth="5"
                strokeLinecap="round"
            />
            <path
                d="M11 27c3.3-4.6 6.6-4.6 9.9 0s6.6 4.6 9.9 0 6.6-4.6 9.9 0"
                stroke="url(#mark-wave)"
                strokeWidth="4"
                strokeLinecap="round"
            />
        </svg>
    );
}

type Props = {
    brand: CompanyBrand;
    /** `light` renders the wordmark for placement on a dark banner. */
    tone?: 'dark' | 'light';
    className?: string;
};

export default function CompanyLogo({
    brand,
    tone = 'dark',
    className,
}: Props) {
    return (
        <span className={cn('flex items-center gap-2', className)}>
            {brand.logoUrl ? (
                <img
                    src={brand.logoUrl}
                    alt=""
                    className="h-9 w-9 shrink-0 rounded-md object-contain"
                />
            ) : (
                <WaveMark />
            )}
            <span
                className={cn(
                    'truncate text-base leading-tight font-bold tracking-tight uppercase',
                    tone === 'light' ? 'text-white' : 'text-coffee-800',
                )}
            >
                {brand.name}
            </span>
        </span>
    );
}
