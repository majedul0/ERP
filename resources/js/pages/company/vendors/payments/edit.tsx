import { Form, Head, Link, usePage } from '@inertiajs/react';
import VendorPaymentController from '@/actions/App/Http/Controllers/Vendors/VendorPaymentController';
import { Button } from '@/components/ui/button';
import type { BankOption } from '@/modules/payments';
import type { VendorOption, VendorPayment } from '@/modules/vendors';
import { VendorPaymentForm } from '@/modules/vendors';
import { index } from '@/routes/vendor-payments';

export default function EditVendorPayment({
    payment,
    vendors,
    banks,
}: {
    payment: VendorPayment;
    vendors: VendorOption[];
    banks: BankOption[];
}) {
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    return (
        <>
            <Head title="Update Payment" />

            <div className="mx-auto w-full max-w-xl">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-bold text-coffee-900">
                        Update Payment
                    </h1>

                    <div className="flex items-center gap-2">
                        {/* Deleting replays the account: the vendor is owed
                            this money again. */}
                        <Form
                            {...VendorPaymentController.destroy.form({
                                current_team: teamSlug,
                                payment: payment.id,
                            })}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <Button
                                    variant="destructive"
                                    disabled={processing}
                                    asChild
                                >
                                    <button
                                        type="submit"
                                        data-test="delete-vendor-payment"
                                    >
                                        Delete
                                    </button>
                                </Button>
                            )}
                        </Form>

                        <Button asChild variant="outline">
                            <Link href={index(teamSlug)}>Cancel</Link>
                        </Button>
                    </div>
                </div>

                <VendorPaymentForm
                    form={VendorPaymentController.update.form({
                        current_team: teamSlug,
                        payment: payment.id,
                    })}
                    vendors={vendors}
                    banks={banks}
                    payment={payment}
                    submitLabel="Save changes"
                    testId="update-vendor-payment-button"
                />
            </div>
        </>
    );
}
