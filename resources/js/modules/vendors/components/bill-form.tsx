import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { VendorBill, VendorOption } from '../types';

const selectClasses =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none';

type FormProps = Omit<React.ComponentProps<typeof Form>, 'children'>;

function today(): string {
    const now = new Date();
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

/**
 * Recording what a vendor has charged.
 *
 * A native select, not the Radix one: this form is uncontrolled and submits by
 * input `name`, which a Radix Select does not provide without extra wiring.
 */
export function BillForm({
    form,
    vendors,
    bill,
    submitLabel,
    testId,
}: {
    form: FormProps;
    vendors: VendorOption[];
    bill?: VendorBill;
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
                                defaultValue={bill?.vendorId ?? ''}
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
                                    htmlFor="billed_on"
                                    className="text-coffee-900"
                                >
                                    Bill Date<span aria-hidden="true">*</span>
                                </Label>
                                <Input
                                    id="billed_on"
                                    name="billed_on"
                                    type="date"
                                    required
                                    defaultValue={bill?.billedOn ?? today()}
                                />
                                <InputError message={errors.billed_on} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="reference"
                                    className="text-coffee-900"
                                >
                                    Bill Number
                                </Label>
                                <Input
                                    id="reference"
                                    name="reference"
                                    defaultValue={bill?.reference ?? ''}
                                    placeholder="The vendor's own reference"
                                />
                                <InputError message={errors.reference} />
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
                                defaultValue={bill?.amount ?? ''}
                            />
                            <InputError message={errors.amount} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="description"
                                className="text-coffee-900"
                            >
                                Description
                            </Label>
                            <Input
                                id="description"
                                name="description"
                                defaultValue={bill?.description ?? ''}
                                placeholder="What it was for"
                            />
                            <InputError message={errors.description} />
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
