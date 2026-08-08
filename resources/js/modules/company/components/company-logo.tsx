import { cn } from '@/lib/utils';
import type { CompanyBrand } from '../types';

/**
 * The wave mark, used when a company has not uploaded a logo yet.
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
                    <stop offset="0%" stopColor="#2b4152" />
                    <stop offset="55%" stopColor="#3f8fae" />
                    <stop offset="100%" stopColor="#54c1c4" />
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
    /** `light` renders the wordmark for placement on an ocean background. */
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
                    tone === 'light' ? 'text-white' : 'text-ocean-800',
                )}
            >
                {brand.name}
            </span>
        </span>
    );
}
