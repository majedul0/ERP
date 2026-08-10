import { cn } from '@/lib/utils';

/**
 * Night-sky backdrop for the company banner panels.
 *
 * Pure SVG, like the waves it replaces: no asset request, and it stretches to
 * any panel size. Deliberately sparse — the panel carries the day's figures,
 * and a busy background makes numbers harder to read, so the stars sit at low
 * opacity and cluster away from the centre where the text sits.
 *
 * A company that uploads its own banner image can layer one over this.
 */
export default function StarBackdrop({ className }: { className?: string }) {
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
                <linearGradient id="star-sky" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stopColor="#5a3f2d" />
                    <stop offset="45%" stopColor="#4a3427" />
                    <stop offset="100%" stopColor="#33241b" />
                </linearGradient>
                <radialGradient id="star-glow" cx="0.78" cy="0.2" r="0.62">
                    <stop offset="0%" stopColor="#e8b93c" stopOpacity="0.22" />
                    <stop offset="100%" stopColor="#e8b93c" stopOpacity="0" />
                </radialGradient>
                {/* One star, reused. Four cusps so it reads as a star rather
                    than a dot at small sizes. */}
                <path
                    id="star-shape"
                    d="M0 -10 C 1.6 -3.4, 3.4 -1.6, 10 0 C 3.4 1.6, 1.6 3.4, 0 10 C -1.6 3.4, -3.4 1.6, -10 0 C -3.4 -1.6, -1.6 -3.4, 0 -10 Z"
                />
            </defs>

            <rect width="1200" height="400" fill="url(#star-sky)" />
            <rect width="1200" height="400" fill="url(#star-glow)" />

            <g fill="#f6e0a3">
                <use
                    href="#star-shape"
                    transform="translate(120 70) scale(1.5)"
                    opacity="0.5"
                />
                <use
                    href="#star-shape"
                    transform="translate(255 190) scale(0.8)"
                    opacity="0.3"
                />
                <use
                    href="#star-shape"
                    transform="translate(70 300) scale(1.1)"
                    opacity="0.36"
                />
                <use
                    href="#star-shape"
                    transform="translate(360 88) scale(0.65)"
                    opacity="0.26"
                />
                <use
                    href="#star-shape"
                    transform="translate(196 344) scale(0.6)"
                    opacity="0.22"
                />
                <use
                    href="#star-shape"
                    transform="translate(905 62) scale(1.7)"
                    opacity="0.55"
                />
                <use
                    href="#star-shape"
                    transform="translate(1050 160) scale(0.9)"
                    opacity="0.34"
                />
                <use
                    href="#star-shape"
                    transform="translate(1142 300) scale(1.25)"
                    opacity="0.4"
                />
                <use
                    href="#star-shape"
                    transform="translate(820 268) scale(0.7)"
                    opacity="0.24"
                />
                <use
                    href="#star-shape"
                    transform="translate(985 352) scale(0.55)"
                    opacity="0.2"
                />
                <use
                    href="#star-shape"
                    transform="translate(640 36) scale(0.6)"
                    opacity="0.18"
                />
                <use
                    href="#star-shape"
                    transform="translate(700 372) scale(0.75)"
                    opacity="0.2"
                />
            </g>

            {/* A few plain specks, so the field does not read as a repeating
                pattern of identical shapes. */}
            <g fill="#faf6f2">
                <circle cx="440" cy="150" r="2" opacity="0.3" />
                <circle cx="560" cy="268" r="1.6" opacity="0.22" />
                <circle cx="760" cy="140" r="2.2" opacity="0.26" />
                <circle cx="300" cy="250" r="1.5" opacity="0.2" />
                <circle cx="1120" cy="90" r="2" opacity="0.28" />
                <circle cx="150" cy="180" r="1.7" opacity="0.24" />
                <circle cx="880" cy="188" r="1.5" opacity="0.2" />
                <circle cx="1010" cy="248" r="2.1" opacity="0.24" />
            </g>
        </svg>
    );
}
