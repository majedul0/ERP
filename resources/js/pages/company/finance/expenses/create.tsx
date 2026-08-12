import { Head, Link, usePage } from '@inertiajs/react';
import ExpenseController from '@/actions/App/Http/Controllers/Finance/ExpenseController';
import { Button } from '@/components/ui/button';
import type { ExpenseCategoryOption } from '@/modules/finance';
import { ExpenseForm } from '@/modules/finance';
import type { BankOption } from '@/modules/payments';
import { index } from '@/routes/expenses';

export default function CreateExpense({
    categories,
    banks,
}: {
    categories: ExpenseCategoryOption[];
    banks: BankOption[];
}) {
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    return (
        <>
            <Head title="Add Expense" />

            <div className="mx-auto w-full max-w-xl">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-bold text-coffee-900">
                        Add Expense
                    </h1>
                    <Button asChild variant="outline">
                        <Link href={index(teamSlug)}>Cancel</Link>
                    </Button>
                </div>

                <ExpenseForm
                    form={ExpenseController.store.form(teamSlug)}
                    categories={categories}
                    banks={banks}
                    submitLabel="Add"
                    testId="add-expense-button"
                />
            </div>
        </>
    );
}
