import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import SalesReturnController from '@/actions/App/Http/Controllers/SalesReturns/SalesReturnController';
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
 * Removing a return recorded by mistake.
 *
 * Spells out both consequences, because neither is guessable from a Delete
 * button: the credit disappears from the account, and the goods come back off
 * the shelf. If they have already been sold on, the delete is refused rather
 * than leaving stock negative.
 */
export function DeleteReturnDialog({
    teamSlug,
    returnId,
    returnNumber,
    restock,
}: {
    teamSlug: string;
    returnId: number;
    returnNumber: string;
    restock: boolean;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="destructive" data-test="delete-return">
                    <Trash2 className="size-4" />
                    Delete
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete {returnNumber}?</DialogTitle>
                <DialogDescription>
                    The distributor owes this amount again, and every line after
                    it on their statement moves.{' '}
                    {restock
                        ? 'The returned goods come back off stock — if they have since been sold on, this is refused rather than leaving stock negative.'
                        : 'Nothing moves in stock: these goods never went back on the shelf.'}{' '}
                    {returnNumber} is retired and will never be reused, so the
                    numbering keeps a gap where it was.
                </DialogDescription>

                <Form
                    {...SalesReturnController.destroy.form({
                        current_team: teamSlug,
                        return: returnId,
                    })}
                    options={{ preserveScroll: true }}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <InputError message={errors.items} />

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
                                        data-test="confirm-delete-return"
                                    >
                                        Delete return
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
