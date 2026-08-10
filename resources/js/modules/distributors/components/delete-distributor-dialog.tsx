import { Form } from '@inertiajs/react';
import DistributorController from '@/actions/App/Http/Controllers/Distributors/DistributorController';
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
 * Deleting a distributor, with the server's refusal shown in place.
 *
 * The check that matters is on the server — DeleteDistributor refuses anyone
 * with invoices or payments, because their statement has to stay reproducible.
 * This dialog does not try to predict that answer; it asks, and shows what came
 * back, so the rule lives in exactly one place.
 */
export function DeleteDistributorDialog({
    teamSlug,
    distributorId,
    distributorName,
}: {
    teamSlug: string;
    distributorId: number;
    distributorName: string;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="destructive" data-test="delete-distributor">
                    Delete
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete {distributorName}?</DialogTitle>
                <DialogDescription>
                    This removes the distributor from your lists. A distributor
                    who has ever been invoiced or has made a payment cannot be
                    deleted — their statement has to stay reproducible.
                </DialogDescription>

                <Form
                    {...DistributorController.destroy.form({
                        current_team: teamSlug,
                        distributor: distributorId,
                    })}
                    options={{ preserveScroll: true }}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <InputError message={errors.distributor} />

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
                                        data-test="confirm-delete-distributor"
                                    >
                                        Delete
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
