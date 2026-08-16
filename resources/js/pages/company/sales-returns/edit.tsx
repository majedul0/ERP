import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { DistributorOption } from '@/modules/invoices';
import type {
    ReturnProductOption,
    SalesReturnDetail,
} from '@/modules/sales-returns';
import { ReturnForm } from '@/modules/sales-returns';
import { show, update } from '@/routes/returns';

export default function EditReturn({
    return: salesReturn,
    distributors,
    products,
}: {
    return: SalesReturnDetail;
    distributors: DistributorOption[];
    products: ReturnProductOption[];
}) {
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    return (
        <>
            <Head title={`Edit ${salesReturn.returnNumber}`} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold text-coffee-900">
                        Edit {salesReturn.returnNumber}
                    </h1>
                    {/* The number never changes: it is on paper the
                        distributor already has. */}
                    <p className="mt-1 font-display text-sm text-coffee-800/60">
                        The account is replayed from this return onwards.
                    </p>
                </div>

                <Button asChild variant="outline">
                    <Link
                        href={show({
                            current_team: teamSlug,
                            return: salesReturn.id,
                        })}
                    >
                        Cancel
                    </Link>
                </Button>
            </div>

            <ReturnForm
                distributors={distributors}
                products={products}
                action={
                    update({ current_team: teamSlug, return: salesReturn.id })
                        .url
                }
                method="put"
                submitLabel="Save Changes"
                seed={salesReturn}
                testId="save-return-button"
            />
        </>
    );
}
