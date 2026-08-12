import { Form, Head, Link, usePage } from '@inertiajs/react';
import VendorBillController from '@/actions/App/Http/Controllers/Vendors/VendorBillController';
import { Button } from '@/components/ui/button';
import type { VendorBill, VendorOption } from '@/modules/vendors';
import { BillForm } from '@/modules/vendors';
import { index } from '@/routes/bills';

export default function EditVendorBill({
    bill,
    vendors,
}: {
    bill: VendorBill;
    vendors: VendorOption[];
}) {
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    return (
        <>
            <Head title="Update Bill" />

            <div className="mx-auto w-full max-w-xl">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-bold text-coffee-900">
                        Update Bill
                    </h1>

                    <div className="flex items-center gap-2">
                        {/* Deleting replays the vendor's account, so what they
                            are owed goes back to what it was. */}
                        <Form
                            {...VendorBillController.destroy.form({
                                current_team: teamSlug,
                                bill: bill.id,
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
                                        data-test="delete-bill"
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

                <BillForm
                    form={VendorBillController.update.form({
                        current_team: teamSlug,
                        bill: bill.id,
                    })}
                    vendors={vendors}
                    bill={bill}
                    submitLabel="Save changes"
                    testId="update-bill-button"
                />
            </div>
        </>
    );
}
