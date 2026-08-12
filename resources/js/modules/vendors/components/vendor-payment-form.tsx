import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { BankOption } from '@/modules/payments';
import type { VendorOption, VendorPayment } from '../types';

const selectClasses =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none';

type FormProps = Omit<React.ComponentProps<typeof Form>, 'children'>;

function today(): string {
    const now = new Date();
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

/**
 * Recording money sent to a vendor.
 *
 * Like a payment received, this lands on the running account rather than
 * against one bill — money goes out as a lump against what is owed.
 */
export function VendorPaymentForm({
    form,
    vendors,
    banks,
    payment,
    submitLabel,
    testId,
}: {
    form: FormProps;
    vendors: VendorOption[];
    banks: BankOption[];
    payment?: VendorPayment;
    submitLabel: string;
    testId: string;
}) {
    return (
        <Form
            {...form}
            options={{ preserveScroll: true }}
            className="mt-8 space-y-5 pb-16"
        >
            {({ processing, errors: formErrors }) => {
                const errors = formErrors as Record<string, string | undefined>;

                return (
                    <>
                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="vendor_id"
                                className="text-coffee-900"
                            >
                                Vendor<span aria-hidden="true">*</span>
                            </Label>
                            <select
                                id="vendor_id"
                                name="vendor_id"
                                required
                                className={selectClasses}
                                defaultValue={payment?.vendorId ?? ''}
                            >
                                <option value="">Select a vendor…</option>
                                {vendors.map((vendor) => (
                                    <option key={vendor.id} value={vendor.id}>
                                        {vendor.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.vendor_id} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="paid_on"
                                    className="text-coffee-900"
                                >
                                    Payment Date
                                    <span aria-hidden="true">*</span>
                                </Label>
                                <Input
                                    id="paid_on"
                                    name="paid_on"
                                    type="date"
                                    required
                                    defaultValue={payment?.paidOn ?? today()}
                                />
                                <InputError message={errors.paid_on} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="bank_id"
                                    className="text-coffee-900"
                                >
                                    Bank
                                </Label>
                                <select
                                    id="bank_id"
                                    name="bank_id"
                                    className={selectClasses}
                                    defaultValue={payment?.bankId ?? ''}
                                >
                                    <option value="">---------</option>
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
                            <Label htmlFor="amount" className="text-coffee-900">
                                Amount<span aria-hidden="true">*</span>
                            </Label>
                            <Input
                                id="amount"
                                name="amount"
                                type="number"
                                min={1}
                                step={1}
                                required
                                defaultValue={payment?.amount ?? ''}
                            />
                            <InputError message={errors.amount} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="comment"
                                className="text-coffee-900"
                            >
                                Comment
                            </Label>
                            <Input
                                id="comment"
                                name="comment"
                                defaultValue={payment?.comment ?? ''}
                            />
                            <InputError message={errors.comment} />
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
