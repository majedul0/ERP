import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { DistributorOption } from '@/modules/invoices';
import type { ReturnProductOption } from '@/modules/sales-returns';
import { ReturnForm } from '@/modules/sales-returns';
import { index, store } from '@/routes/returns';

export default function CreateReturn({
    distributors,
    products,
    nextReturnNumber,
}: {
    distributors: DistributorOption[];
    products: ReturnProductOption[];
    nextReturnNumber: string;
}) {
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    return (
        <>
            <Head title="Add Return" />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold text-coffee-900">
                        Add Return
                    </h1>
                    {/* Advisory: the number that lands is allocated at save
                        time, so two people here at once both see this one. */}
                    <p className="mt-1 font-display text-sm text-coffee-800/60">
                        Next number {nextReturnNumber}
                    </p>
                </div>

                <Button asChild variant="outline">
                    <Link href={index(teamSlug)}>Cancel</Link>
                </Button>
            </div>

            {distributors.length === 0 || products.length === 0 ? (
                <p className="mt-8 rounded-lg border border-coffee-100 bg-white p-8 text-center text-coffee-800/70 shadow-sm">
                    A return needs a distributor and a product — add those
                    first.
                </p>
            ) : (
                <ReturnForm
                    distributors={distributors}
                    products={products}
                    action={store(teamSlug).url}
                    method="post"
                    submitLabel="Record Return"
                    testId="save-return-button"
                />
            )}
        </>
    );
}
