import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { TodaysSalesTable } from '@/modules/dashboard';
import type { TodaySale } from '@/modules/dashboard';
import { create } from '@/routes/invoices';

export default function Invoices({ invoices }: { invoices: TodaySale[] }) {
    const { currentTeam } = usePage().props;

    return (
        <>
            <Head title="Sales Invoices" />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-bold text-coffee-900">
                    Sales Invoices
                </h1>
                <Button asChild className="bg-coffee-600 hover:bg-coffee-700">
                    <Link href={create(currentTeam?.slug ?? '')}>
                        + Add Invoice
                    </Link>
                </Button>
            </div>

            <TodaysSalesTable
                sales={invoices}
                title="All Invoices"
                emptyMessage="No invoices yet."
            />
        </>
    );
}
