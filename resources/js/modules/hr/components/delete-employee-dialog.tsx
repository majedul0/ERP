import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import EmployeeController from '@/actions/App/Http/Controllers/Employees/EmployeeController';
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
 * Removing an employee record.
 *
 * Says plainly that this is not how somebody leaves, because that is the
 * mistake this button invites: a leaving date keeps the person's payslips,
 * attendance and account intact and simply stops payroll counting them, which
 * is what "they don't work here any more" actually means.
 */
export function DeleteEmployeeDialog({
    teamSlug,
    employeeId,
    name,
}: {
    teamSlug: string;
    employeeId: number;
    name: string;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="destructive" data-test="delete-employee">
                    <Trash2 className="size-4" />
                    Remove
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Remove {name}?</DialogTitle>
                <DialogDescription>
                    This is for a record added by mistake.{' '}
                    <strong>
                        If they have left the company, set a leaving date
                        instead
                    </strong>{' '}
                    — that keeps their payslips and their account, and stops
                    payroll counting them from that month. Nothing already paid
                    is deleted either way.
                </DialogDescription>

                <Form
                    {...EmployeeController.destroy.form({
                        current_team: teamSlug,
                        employee: employeeId,
                    })}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
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
                                    data-test="confirm-delete-employee"
                                >
                                    Remove record
                                </button>
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
