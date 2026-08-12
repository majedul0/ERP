import { Head, Link, usePage } from '@inertiajs/react';
import VendorController from '@/actions/App/Http/Controllers/Vendors/VendorController';
import { Button } from '@/components/ui/button';
import type { Vendor } from '@/modules/vendors';
import { VendorForm } from '@/modules/vendors';
import { show } from '@/routes/vendors';

export default function EditVendor({ vendor }: { vendor: Vendor }) {
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    return (
        <>
            <Head title={`Update ${vendor.name}`} />

            <div className="mx-auto w-full max-w-xl">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-bold text-coffee-900">
                        Update {vendor.name}
                    </h1>
                    <Button asChild variant="outline">
                        <Link
                            href={show({
                                current_team: teamSlug,
                                vendor: vendor.id,
                            })}
                        >
                            Cancel
                        </Link>
                    </Button>
                </div>

                <VendorForm
                    form={VendorController.update.form({
                        current_team: teamSlug,
                        vendor: vendor.id,
                    })}
                    vendor={vendor}
                    submitLabel="Save changes"
                    testId="update-vendor-button"
                />
            </div>
        </>
    );
}
