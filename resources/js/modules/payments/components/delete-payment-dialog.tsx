import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import PaymentController from '@/actions/App/Http/Controllers/Payments/PaymentController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

/**
 * Deleting a payment entered by mistake.
 *
 * Says plainly that the debt comes back, because that is the consequence people
 * do not expect from a delete button on a finance screen.
 */
export function DeletePaymentDialog({
    teamSlug,
    paymentId,
}: {
    teamSlug: string;
    paymentId: number;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="destructive" data-test="delete-payment">
                    <Trash2 className="size-4" />
                    Delete
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete this payment?</DialogTitle>
                <DialogDescription>
                    The distributor will owe this money again, and every invoice
                    dated after it carries the higher figure forward. Use this
                    for a payment entered twice — if the amount or the date is
                    simply wrong, correct it instead.
                </DialogDescription>

                <Form
                    {...PaymentController.destroy.form({
                        current_team: teamSlug,
                        payment: paymentId,
                    })}
                    options={{ preserveScroll: true }}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <InputError message={errors.payment} />

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button
                                    variant="destructive"
                                    disabled={processing}
                                    asChild
                                >
                                    <button
                                        type="submit"
                                        data-test="confirm-delete-payment"
                                    >
                                        Delete payment
                                    </button>
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
