import { Form, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import SalaryPaymentController from '@/actions/App/Http/Controllers/Hr/SalaryPaymentController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatMoney } from '@/lib/format';
import { useCompanyBrand } from '@/modules/company';
import type { PayableEmployee } from '@/modules/hr';

const selectClasses =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none';

function today(): string {
    const now = new Date();
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

export default function RecordSalaryPayment({
    employees,
    banks,
    kinds,
}: {
    employees: PayableEmployee[];
    banks: Array<{ id: number; name: string }>;
    kinds: Array<{ value: string; label: string }>;
}) {
    const brand = useCompanyBrand();
    const { currentTeam } = usePage().props;
    const money = (amount: number) => formatMoney(amount, brand.currencySymbol);

    const [kind, setKind] = useState('salary');
    const [employeeId, setEmployeeId] = useState(
        employees[0] ? String(employees[0].id) : '',
    );

    const selected = employees.find(
        (employee) => String(employee.id) === employeeId,
    );

    return (
        <>
            <Head title="Record payment" />

            <div className="mx-auto w-full max-w-xl">
                <h1 className="text-center text-2xl font-bold text-coffee-900">
                    Record payment
                </h1>

                {employees.length === 0 ? (
                    <p className="mt-8 text-center text-coffee-800/60">
                        Add an employee first.
                    </p>
                ) : (
                    <Form
                        {...SalaryPaymentController.store.form(
                            currentTeam?.slug ?? '',
                        )}
                        options={{ preserveScroll: true }}
                        className="mt-8 space-y-5 pb-16"
                    >
                        {({ processing, errors: formErrors }) => {
                            const errors = formErrors as Record<
                                string,
                                string | undefined
                            >;

                            return (
                                <>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="employee_id">
                                            Employee
                                        </Label>
                                        <select
                                            id="employee_id"
                                            name="employee_id"
                                            className={selectClasses}
                                            value={employeeId}
                                            onChange={(event) =>
                                                setEmployeeId(
                                                    event.target.value,
                                                )
                                            }
                                            required
                                        >
                                            {employees.map((employee) => (
                                                <option
                                                    key={employee.id}
                                                    value={employee.id}
                                                >
                                                    {employee.name} (
                                                    {employee.employeeCode})
                                                    {employee.isActive
                                                        ? ''
                                                        : ' — left'}
                                                </option>
                                            ))}
                                        </select>
                                        {selected && (
                                            <p className="text-xs text-coffee-800/60">
                                                {selected.balance >= 0
                                                    ? `Owed to them: ${money(selected.balance)}`
                                                    : `They have drawn ${money(-selected.balance)} ahead of what they have earned.`}
                                            </p>
                                        )}
                                        <InputError
                                            message={errors.employee_id}
                                        />
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label htmlFor="kind">For</Label>
                                        <select
                                            id="kind"
                                            name="kind"
                                            className={selectClasses}
                                            value={kind}
                                            onChange={(event) =>
                                                setKind(event.target.value)
                                            }
                                            required
                                        >
                                            {kinds.map((option) => (
                                                <option
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.kind} />
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="paid_on">
                                                Date
                                            </Label>
                                            <Input
                                                id="paid_on"
                                                name="paid_on"
                                                type="date"
                                                defaultValue={today()}
                                                required
                                            />
                                            <InputError
                                                message={errors.paid_on}
                                            />
                                        </div>

                                        <div className="grid gap-1.5">
                                            <Label htmlFor="amount">
                                                Amount
                                            </Label>
                                            <Input
                                                id="amount"
                                                name="amount"
                                                type="number"
                                                min={1}
                                                step={1}
                                                required
                                                data-test="payment-amount"
                                            />
                                            <InputError
                                                message={errors.amount}
                                            />
                                        </div>
                                    </div>

                                    {kind === 'advance' && (
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="installment_amount">
                                                Monthly installment
                                            </Label>
                                            <Input
                                                id="installment_amount"
                                                name="installment_amount"
                                                type="number"
                                                min={1}
                                                step={1}
                                                placeholder="Leave empty to recover it all next month"
                                            />
                                            <p className="text-xs text-coffee-800/60">
                                                How much comes off each month's
                                                net pay until it is recovered.
                                                Never more than they earned that
                                                month.
                                            </p>
                                            <InputError
                                                message={
                                                    errors.installment_amount
                                                }
                                            />
                                        </div>
                                    )}

                                    <div className="grid gap-1.5">
                                        <Label htmlFor="bank_id">
                                            Paid from
                                        </Label>
                                        <select
                                            id="bank_id"
                                            name="bank_id"
                                            className={selectClasses}
                                        >
                                            <option value="">
                                                Cash in hand
                                            </option>
                                            {banks.map((bank) => (
                                                <option
                                                    key={bank.id}
                                                    value={bank.id}
                                                >
                                                    {bank.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.bank_id} />
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label htmlFor="comment">Note</Label>
                                        <Input
                                            id="comment"
                                            name="comment"
                                            placeholder="Optional"
                                        />
                                        <InputError message={errors.comment} />
                                    </div>

                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        data-test="save-payment"
                                        className="bg-coffee-600 hover:bg-coffee-700"
                                    >
                                        {processing && <Spinner />}
                                        Record payment
                                    </Button>
                                </>
                            );
                        }}
                    </Form>
                )}
            </div>
        </>
    );
}
