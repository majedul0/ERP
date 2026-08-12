import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { Vendor } from '../types';

const fields = [
    { name: 'name', label: 'Vendor Name', key: 'name', required: true },
    {
        name: 'proprietor_name',
        label: 'Proprietor Name',
        key: 'proprietorName',
    },
    { name: 'phone', label: 'Phone', key: 'phone' },
    { name: 'address', label: 'Address', key: 'address' },
    { name: 'thana', label: 'Thana', key: 'thana' },
    { name: 'district', label: 'District', key: 'district' },
    { name: 'division', label: 'Division', key: 'division' },
] as const;

type FormProps = Omit<React.ComponentProps<typeof Form>, 'children'>;

/**
 * The add/edit form for a vendor.
 *
 * One component for both screens so an edit cannot quietly diverge from a
 * create, matching MaterialForm.
 */
export function VendorForm({
    form,
    vendor,
    submitLabel,
    testId,
}: {
    form: FormProps;
    vendor?: Vendor;
    submitLabel: string;
    testId: string;
}) {
    return (
        <Form
            {...form}
            options={{ preserveScroll: true }}
            resetOnSuccess={!vendor}
            className="mt-8 space-y-5 pb-16"
        >
            {({ processing, errors: formErrors }) => {
                // Form types its errors from the action it was given; taking
                // that action as a prop erases the generic.
                const errors = formErrors as Record<string, string | undefined>;

                return (
                    <>
                        {fields.map((field) => (
                            <div key={field.name} className="grid gap-1.5">
                                <Label
                                    htmlFor={field.name}
                                    className="text-coffee-900"
                                >
                                    {field.label}
                                    {'required' in field && (
                                        <span aria-hidden="true">*</span>
                                    )}
                                </Label>
                                <Input
                                    id={field.name}
                                    name={field.name}
                                    required={'required' in field}
                                    defaultValue={vendor?.[field.key] ?? ''}
                                />
                                <InputError message={errors[field.name]} />
                            </div>
                        ))}

                        {vendor && (
                            <p className="text-xs text-coffee-800/60">
                                The balance is not edited here — it is the
                                result of replaying this vendor&apos;s bills and
                                payments. Record a bill or a payment to change
                                it.
                            </p>
                        )}

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
