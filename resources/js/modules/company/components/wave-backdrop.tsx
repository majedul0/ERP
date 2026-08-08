import { cn } from '@/lib/utils';

/**
 * Rippling-water backdrop for the ocean-branded panels.
 *
 * Pure SVG so it costs no asset request and stretches to any panel size. A
 * company that uploads its own banner image can layer one over this instead.
 */
export default function WaveBackdrop({ className }: { className?: string }) {
    return (
        <svg
            aria-hidden="true"
            className={cn(
                'pointer-events-none absolute inset-0 h-full w-full',
                className,
            )}
            viewBox="0 0 1200 400"
            preserveAspectRatio="none"
            fill="none"
        >
            <defs>
                <linearGradient id="wave-water" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stopColor="#5b90b6" />
                    <stop offset="45%" stopColor="#4d84ab" />
                    <stop offset="100%" stopColor="#3f6d92" />
                </linearGradient>
                <radialGradient id="wave-glare" cx="0.72" cy="0.18" r="0.6">
                    <stop offset="0%" stopColor="#ffffff" stopOpacity="0.28" />
                    <stop offset="100%" stopColor="#ffffff" stopOpacity="0" />
                </radialGradient>
            </defs>

            <rect width="1200" height="400" fill="url(#wave-water)" />
            <rect width="1200" height="400" fill="url(#wave-glare)" />

            <g stroke="#ffffff" fill="none" strokeLinecap="round">
                <path
                    d="M-40 62 C 150 24, 330 96, 520 58 S 900 12, 1240 74"
                    strokeWidth="10"
                    strokeOpacity="0.09"
                />
                <path
                    d="M-40 118 C 180 82, 340 150, 560 112 S 940 70, 1240 128"
                    strokeWidth="6"
                    strokeOpacity="0.13"
                />
                <path
                    d="M-40 176 C 210 214, 380 132, 620 178 S 980 224, 1240 168"
                    strokeWidth="14"
                    strokeOpacity="0.07"
                />
                <path
                    d="M-40 232 C 160 196, 400 268, 640 226 S 1000 182, 1240 240"
                    strokeWidth="5"
                    strokeOpacity="0.14"
                />
                <path
                    d="M-40 292 C 220 330, 420 252, 660 296 S 1020 340, 1240 286"
                    strokeWidth="12"
                    strokeOpacity="0.08"
                />
                <path
                    d="M-40 348 C 180 312, 380 380, 600 344 S 960 302, 1240 356"
                    strokeWidth="7"
                    strokeOpacity="0.11"
                />
            </g>
        </svg>
    );
}
