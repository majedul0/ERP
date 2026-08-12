import { Head, Link, usePage } from '@inertiajs/react';
import VendorBillController from '@/actions/App/Http/Controllers/Vendors/VendorBillController';
import { Button } from '@/components/ui/button';
import type { VendorOption } from '@/modules/vendors';
import { BillForm } from '@/modules/vendors';
import { index } from '@/routes/bills';
import { create as newVendor } from '@/routes/vendors';

export default function CreateVendorBill({
    vendors,
}: {
    vendors: VendorOption[];
}) {
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    return (
        <>
            <Head title="Add Vendor Bill" />

            <div className="mx-auto w-full max-w-xl">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-bold text-coffee-900">
                        Add Vendor Bill
                    </h1>
                    <Button asChild variant="outline">
                        <Link href={index(teamSlug)}>Cancel</Link>
                    </Button>
                </div>

                {vendors.length === 0 ? (
                    <p className="mt-8 rounded-lg border border-coffee-100 bg-white p-8 text-center text-coffee-800/70 shadow-sm">
                        Add a vendor first — a bill has to be from someone.{' '}
                        <Link
                            href={newVendor(teamSlug)}
                            className="underline underline-offset-4"
                        >
                            Add one
                        </Link>
                        .
                    </p>
                ) : (
                    <BillForm
                        form={VendorBillController.store.form(teamSlug)}
                        vendors={vendors}
                        submitLabel="Add"
                        testId="add-bill-button"
                    />
                )}
            </div>
        </>
    );
}
