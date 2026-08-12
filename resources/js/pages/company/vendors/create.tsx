import { Head, Link, usePage } from '@inertiajs/react';
import VendorController from '@/actions/App/Http/Controllers/Vendors/VendorController';
import { Button } from '@/components/ui/button';
import { VendorForm } from '@/modules/vendors';
import { index } from '@/routes/vendors';

export default function CreateVendor() {
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    return (
        <>
            <Head title="Add Vendor" />

            <div className="mx-auto w-full max-w-xl">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-bold text-coffee-900">
                        Add Vendor
                    </h1>
                    <Button asChild variant="outline">
                        <Link href={index(teamSlug)}>Cancel</Link>
                    </Button>
                </div>

                <VendorForm
                    form={VendorController.store.form(teamSlug)}
                    submitLabel="Add"
                    testId="add-vendor-button"
                />
            </div>
        </>
    );
}
