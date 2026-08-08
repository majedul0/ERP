import { Link } from '@inertiajs/react';
import { formatClockDate, formatClockTime, formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import { WaveBackdrop } from '@/modules/company';
import type { CompanyBrand } from '@/modules/company';
import { useLiveClock } from '../hooks/use-live-clock';
import type { DashboardStats } from '../types';

export type QuickAction = {
    label: string;
    /** Omitted until the module ships; the button then renders inert. */
    href?: string;
};

const defaultQuickActions: QuickAction[] = [
    { label: 'Add Invoice' },
    { label: 'Add Distributor' },
    { label: 'Add Vendor' },
    { label: 'Add Product' },
];

const quickActionClasses =
    'rounded-md bg-white/15 px-4 py-2 text-sm font-semibold text-white ring-1 ring-white/30 backdrop-blur-sm transition-colors';

function QuickActionButton({ action }: { action: QuickAction }) {
    if (!action.href) {
        return (
            <span
                aria-disabled="true"
                title="Coming soon"
                className={cn(
                    quickActionClasses,
                    'cursor-not-allowed opacity-80',
                )}
            >
                + {action.label}
            </span>
        );
    }

    return (
        <Link
            href={action.href}
            className={cn(quickActionClasses, 'hover:bg-white/25')}
        >
            + {action.label}
        </Link>
    );
}

function Stat({
    label,
    value,
    className,
}: {
    label: string;
    value: string;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'border-white/25 sm:border-l sm:pl-6 sm:first:border-l-0 sm:first:pl-0',
                className,
            )}
        >
            <dt className="text-sm font-medium text-white/85">{label}</dt>
            <dd className="mt-1 text-xl font-semibold whitespace-nowrap text-white">
                {value}
            </dd>
        </div>
    );
}

type Props = {
    brand: CompanyBrand;
    stats: DashboardStats;
    quickActions?: QuickAction[];
};

export default function DashboardHero({
    brand,
    stats,
    quickActions = defaultQuickActions,
}: Props) {
    const now = useLiveClock();
    const money = (amount: number) => formatMoney(amount, brand.currencySymbol);

    return (
        <section className="relative overflow-hidden rounded-2xl bg-ocean-500 shadow-sm">
            <WaveBackdrop />

            <div className="relative flex flex-col gap-7 p-6 lg:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-white/85">
                            Welcome,
                        </p>
                        <h1 className="mt-1.5 text-2xl font-bold text-white lg:text-3xl">
                            {brand.name}
                        </h1>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {quickActions.map((action) => (
                            <QuickActionButton
                                key={action.label}
                                action={action}
                            />
                        ))}
                    </div>
                </div>

                <div>
                    <p className="text-sm font-medium text-white/85">Total</p>
                    <p className="mt-1 text-4xl font-bold text-white lg:text-5xl">
                        {money(stats.total)}
                    </p>
                </div>

                <div className="flex flex-wrap items-end justify-between gap-6">
                    <dl className="grid grid-cols-2 gap-x-6 gap-y-4 sm:flex sm:gap-6">
                        <Stat label="Sales" value={money(stats.sales)} />
                        <Stat
                            label="Payments From Distributors"
                            value={money(stats.distributorPayments)}
                        />
                        <Stat label="Expenses" value={money(stats.expenses)} />
                        <Stat
                            label="Promotions"
                            value={money(stats.promotions)}
                        />
                    </dl>

                    <div className="ml-auto text-right">
                        <p className="text-3xl font-semibold text-white lg:text-4xl">
                            {formatClockTime(now)}
                        </p>
                        <p className="text-lg text-white/90 lg:text-2xl">
                            {formatClockDate(now)}
                        </p>
                    </div>
                </div>
            </div>
        </section>
    );
}
