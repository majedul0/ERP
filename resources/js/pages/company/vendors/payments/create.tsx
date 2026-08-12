import { Head, Link, usePage } from '@inertiajs/react';
import VendorPaymentController from '@/actions/App/Http/Controllers/Vendors/VendorPaymentController';
import { Button } from '@/components/ui/button';
import type { BankOption } from '@/modules/payments';
import type { VendorOption } from '@/modules/vendors';
import { VendorPaymentForm } from '@/modules/vendors';
import { index } from '@/routes/vendor-payments';
import { create as newVendor } from '@/routes/vendors';

export default function CreateVendorPayment({
    vendors,
    banks,
}: {
    vendors: VendorOption[];
    banks: BankOption[];
}) {
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    return (
        <>
            <Head title="Pay Vendor" />

            <div className="mx-auto w-full max-w-xl">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-bold text-coffee-900">
                        Pay Vendor
                    </h1>
                    <Button asChild variant="outline">
                        <Link href={index(teamSlug)}>Cancel</Link>
                    </Button>
                </div>

                {vendors.length === 0 ? (
                    <p className="mt-8 rounded-lg border border-coffee-100 bg-white p-8 text-center text-coffee-800/70 shadow-sm">
                        Add a vendor first — a payment has to go to someone.{' '}
                        <Link
                            href={newVendor(teamSlug)}
                            className="underline underline-offset-4"
                        >
                            Add one
                        </Link>
                        .
                    </p>
                ) : (
                    <VendorPaymentForm
                        form={VendorPaymentController.store.form(teamSlug)}
                        vendors={vendors}
                        banks={banks}
                        submitLabel="Record Payment"
                        testId="add-vendor-payment-button"
                    />
                )}
            </div>
        </>
    );
}
