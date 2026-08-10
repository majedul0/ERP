import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import InvoiceController from '@/actions/App/Http/Controllers/Invoices/InvoiceController';
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
 * Deleting an invoice raised by mistake.
 *
 * Spells out the two consequences people are usually surprised by — the stock
 * comes back, and the number is never reused — because both are decisions the
 * accounts depend on and neither is guessable from a "Delete" button.
 */
export function DeleteInvoiceDialog({
    teamSlug,
    invoiceId,
    invoiceNumber,
}: {
    teamSlug: string;
    invoiceId: number;
    invoiceNumber: string;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="destructive" data-test="delete-invoice">
                    <Trash2 className="size-4" />
                    Delete
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete {invoiceNumber}?</DialogTitle>
                <DialogDescription>
                    The goods on this invoice go back into stock and the
                    distributor stops owing for it. {invoiceNumber} is retired —
                    it will never be given to another invoice, so the numbering
                    keeps a gap where this one was. Use this for a duplicate;
                    for a sale that fell through, set the status to Cancelled
                    instead so the record stays.
                </DialogDescription>

                <Form
                    {...InvoiceController.destroy.form({
                        current_team: teamSlug,
                        invoice: invoiceId,
                    })}
                    options={{ preserveScroll: true }}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <InputError message={errors.invoice} />

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
                                        data-test="confirm-delete-invoice"
                                    >
                                        Delete invoice
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
