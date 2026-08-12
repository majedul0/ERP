import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { BankOption } from '@/modules/payments';
import type { Expense, ExpenseCategoryOption } from '../types';

const selectClasses =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none';

type FormProps = Omit<React.ComponentProps<typeof Form>, 'children'>;

function today(): string {
    const now = new Date();
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

/**
 * The add/edit form for an expense.
 *
 * The category list is fixed, and comes from the server: the report groups by
 * it, so free text would split one line of the accounts into several.
 */
export function ExpenseForm({
    form,
    categories,
    banks,
    expense,
    submitLabel,
    testId,
}: {
    form: FormProps;
    categories: ExpenseCategoryOption[];
    banks: BankOption[];
    expense?: Expense;
    submitLabel: string;
    testId: string;
}) {
    return (
        <Form
            {...form}
            options={{ preserveScroll: true }}
            resetOnSuccess={!expense}
            className="mt-8 space-y-5 pb-16"
        >
            {({ processing, errors: formErrors }) => {
                const errors = formErrors as Record<string, string | undefined>;

                return (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="category"
                                    className="text-coffee-900"
                                >
                                    Category<span aria-hidden="true">*</span>
                                </Label>
                                <select
                                    id="category"
                                    name="category"
                                    required
                                    className={selectClasses}
                                    defaultValue={expense?.category ?? 'other'}
                                >
                                    {categories.map((category) => (
                                        <option
                                            key={category.value}
                                            value={category.value}
                                        >
                                            {category.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.category} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="spent_on"
                                    className="text-coffee-900"
                                >
                                    Date<span aria-hidden="true">*</span>
                                </Label>
                                <Input
                                    id="spent_on"
                                    name="spent_on"
                                    type="date"
                                    required
                                    defaultValue={expense?.spentOn ?? today()}
                                />
                                <InputError message={errors.spent_on} />
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="description"
                                className="text-coffee-900"
                            >
                                Description<span aria-hidden="true">*</span>
                            </Label>
                            <Input
                                id="description"
                                name="description"
                                required
                                defaultValue={expense?.description ?? ''}
                                placeholder="What the money was spent on"
                            />
                            <InputError message={errors.description} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="amount"
                                    className="text-coffee-900"
                                >
                                    Amount<span aria-hidden="true">*</span>
                                </Label>
                                <Input
                                    id="amount"
                                    name="amount"
                                    type="number"
                                    min={1}
                                    step={1}
                                    required
                                    defaultValue={expense?.amount ?? ''}
                                />
                                <InputError message={errors.amount} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="bank_id"
                                    className="text-coffee-900"
                                >
                                    Paid From
                                </Label>
                                <select
                                    id="bank_id"
                                    name="bank_id"
                                    className={selectClasses}
                                    defaultValue={expense?.bankId ?? ''}
                                >
                                    <option value="">Cash</option>
                                    {banks.map((bank) => (
                                        <option key={bank.id} value={bank.id}>
                                            {bank.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.bank_id} />
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="note" className="text-coffee-900">
                                Note
                            </Label>
                            <Input
                                id="note"
                                name="note"
                                defaultValue={expense?.note ?? ''}
                            />
                            <InputError message={errors.note} />
                        </div>

                        <Button
                            type="submit"
                            disabled={processing}
                            className="w-full bg-coffee-600 hover:bg-coffee-700"
                            data-test={testId}
                        >
                            {processing && <Spinner />}
                            {submitLabel}
                        </Button>
                    </>
                );
            }}
        </Form>
    );
}
