import { Form, Head, Link, usePage } from '@inertiajs/react';
import ExpenseController from '@/actions/App/Http/Controllers/Finance/ExpenseController';
import { Button } from '@/components/ui/button';
import type { Expense, ExpenseCategoryOption } from '@/modules/finance';
import { ExpenseForm } from '@/modules/finance';
import type { BankOption } from '@/modules/payments';
import { index } from '@/routes/expenses';

export default function EditExpense({
    expense,
    categories,
    banks,
}: {
    expense: Expense;
    categories: ExpenseCategoryOption[];
    banks: BankOption[];
}) {
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    return (
        <>
            <Head title="Update Expense" />

            <div className="mx-auto w-full max-w-xl">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-bold text-coffee-900">
                        Update Expense
                    </h1>

                    <div className="flex items-center gap-2">
                        <Form
                            {...ExpenseController.destroy.form({
                                current_team: teamSlug,
                                expense: expense.id,
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
                                        data-test="delete-expense"
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

                <ExpenseForm
                    form={ExpenseController.update.form({
                        current_team: teamSlug,
                        expense: expense.id,
                    })}
                    categories={categories}
                    banks={banks}
                    expense={expense}
                    submitLabel="Save changes"
                    testId="update-expense-button"
                />
            </div>
        </>
    );
}
