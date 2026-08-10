import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { MaterialUnitOption, RawMaterial } from '../types';

const fields = [
    {
        name: 'name',
        label: 'Material Name',
        type: 'text',
        key: 'name',
    },
    {
        name: 'code',
        label: 'Material Code',
        type: 'text',
        key: 'code',
        hint: 'Unique within your company — how purchases refer to this material.',
    },
    {
        name: 'stock_quantity',
        label: 'Stock Amount',
        type: 'number',
        min: 0,
        key: 'stockQuantity',
        hint: 'The amount in the store, not a change to it.',
    },
    {
        name: 'reorder_level',
        label: 'Reorder Level',
        type: 'number',
        min: 0,
        key: 'reorderLevel',
        hint: 'Stock at or below this is flagged low. Use 0 to never flag it.',
    },
    {
        name: 'unit_cost',
        label: 'Unit Cost',
        type: 'number',
        min: 0,
        key: 'unitCost',
        hint: 'What one unit last cost. Whole amounts only.',
    },
] as const;

type FormProps = Omit<React.ComponentProps<typeof Form>, 'children'>;

/**
 * The add/edit form for a raw material.
 *
 * One component for both screens: the fields, their hints and their validation
 * messages are identical, and the only difference is where the form posts and
 * what it starts filled in with. Two copies would drift the moment a field is
 * added.
 */
export function MaterialForm({
    form,
    units,
    material,
    submitLabel,
    testId,
}: {
    form: FormProps;
    units: MaterialUnitOption[];
    material?: RawMaterial;
    submitLabel: string;
    testId: string;
}) {
    return (
        <Form
            {...form}
            options={{ preserveScroll: true }}
            resetOnSuccess={!material}
            className="mt-8 space-y-5 pb-16"
        >
            {({ processing, errors: formErrors }) => {
                // Form types its errors from the action it was given; taking
                // that action as a prop erases the generic, so the field names
                // have to be named here instead of inferred.
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
                                    <span aria-hidden="true">*</span>
                                </Label>
                                <Input
                                    id={field.name}
                                    name={field.name}
                                    type={field.type}
                                    required
                                    min={'min' in field ? field.min : undefined}
                                    step={
                                        field.type === 'number' ? 1 : undefined
                                    }
                                    defaultValue={
                                        material
                                            ? String(material[field.key])
                                            : field.type === 'number'
                                              ? '0'
                                              : undefined
                                    }
                                />
                                {'hint' in field && !errors[field.name] && (
                                    <p className="text-xs text-coffee-800/60">
                                        {field.hint}
                                    </p>
                                )}
                                <InputError message={errors[field.name]} />
                            </div>
                        ))}

                        {/*
                        A native select, not the Radix one: this form is
                        uncontrolled and submits by input `name`, which a
                        Radix Select does not provide without extra wiring.
                    */}
                        <div className="grid gap-1.5">
                            <Label htmlFor="unit" className="text-coffee-900">
                                Unit<span aria-hidden="true">*</span>
                            </Label>
                            <select
                                id="unit"
                                name="unit"
                                required
                                defaultValue={material?.unit ?? 'kg'}
                                className="h-9 w-full rounded-md border border-coffee-200 bg-white px-3 py-1 text-sm text-coffee-900 shadow-xs focus-visible:border-coffee-400 focus-visible:ring-[3px] focus-visible:ring-coffee-200 focus-visible:outline-none"
                            >
                                {units.map((unit) => (
                                    <option key={unit.value} value={unit.value}>
                                        {unit.label}
                                    </option>
                                ))}
                            </select>
                            <p className="text-xs text-coffee-800/60">
                                Quantities are whole numbers. Pick a smaller
                                unit if you need finer amounts.
                            </p>
                            <InputError message={errors.unit} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="note" className="text-coffee-900">
                                Note
                            </Label>
                            <Input
                                id="note"
                                name="note"
                                type="text"
                                defaultValue={material?.note ?? ''}
                                placeholder="Optional — supplier, grade, storage"
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
