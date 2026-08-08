import { Form, Head, usePage } from '@inertiajs/react';
import ProductController from '@/actions/App/Http/Controllers/Products/ProductController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

const fields = [
    { name: 'name', label: 'Product Name', type: 'text', required: true },
    { name: 'sku', label: 'SKU', type: 'text', required: true },
    {
        name: 'carton_size',
        label: 'Carton Size',
        type: 'number',
        required: true,
        min: 1,
        hint: 'Pieces per carton. Typing cartons on an invoice multiplies by this.',
    },
    {
        name: 'distributor_price',
        label: 'Distributor Price',
        type: 'number',
        required: true,
        step: '0.01',
        hint: 'Used as the default unit price on new invoice lines.',
    },
    {
        name: 'trade_price',
        label: 'Trade Price',
        type: 'number',
        required: true,
        step: '0.01',
    },
    { name: 'mrp', label: 'MRP', type: 'number', required: true, step: '0.01' },
    {
        name: 'stock_quantity',
        label: 'Stock Amount',
        type: 'number',
        required: true,
        min: 0,
        defaultValue: '0',
    },
] as const;

export default function RegisterProduct() {
    const { currentTeam } = usePage().props;

    return (
        <>
            <Head title="Register New Product" />

            <h1 className="mt-2 text-center text-2xl font-bold text-ocean-900">
                Register New Product
            </h1>

            <Form
                {...ProductController.store.form(currentTeam?.slug ?? '')}
                options={{ preserveScroll: true }}
                resetOnSuccess
                className="mx-auto mt-8 w-full max-w-xl space-y-5 pb-16"
            >
                {({ processing, errors }) => (
                    <>
                        {fields.map((field) => (
                            <div key={field.name} className="grid gap-1.5">
                                <Label
                                    htmlFor={field.name}
                                    className="text-ocean-900"
                                >
                                    {field.label}
                                    {field.required && (
                                        <span aria-hidden="true">*</span>
                                    )}
                                </Label>
                                <Input
                                    id={field.name}
                                    name={field.name}
                                    type={field.type}
                                    required={field.required}
                                    min={'min' in field ? field.min : undefined}
                                    step={
                                        'step' in field ? field.step : undefined
                                    }
                                    defaultValue={
                                        'defaultValue' in field
                                            ? field.defaultValue
                                            : undefined
                                    }
                                />
                                {'hint' in field && !errors[field.name] && (
                                    <p className="text-xs text-ocean-800/60">
                                        {field.hint}
                                    </p>
                                )}
                                <InputError message={errors[field.name]} />
                            </div>
                        ))}

                        <div className="grid gap-1.5">
                            <Label htmlFor="photo" className="text-ocean-900">
                                Photo
                            </Label>
                            <Input
                                id="photo"
                                name="photo"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                            />
                            <InputError message={errors.photo} />
                        </div>

                        <Button
                            type="submit"
                            disabled={processing}
                            className="w-full bg-ocean-600 hover:bg-ocean-700"
                            data-test="add-product-button"
                        >
                            {processing && <Spinner />}
                            Add
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}
