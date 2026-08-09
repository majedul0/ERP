import { Form, Head, Link, usePage } from '@inertiajs/react';
import ProductController from '@/actions/App/Http/Controllers/Products/ProductController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { Product } from '@/modules/products';
import { index } from '@/routes/products';

const fields = [
    { name: 'name', label: 'Product Name', type: 'text', key: 'name' },
    { name: 'sku', label: 'SKU', type: 'text', key: 'sku' },
    {
        name: 'carton_size',
        label: 'Carton Size',
        type: 'number',
        min: 1,
        key: 'cartonSize',
    },
    {
        name: 'distributor_price',
        label: 'Distributor Price',
        type: 'number',
        min: 0,
        key: 'distributorPrice',
        hint: 'Applies to new invoices. Invoices already issued keep the price they were sold at.',
    },
    {
        name: 'trade_price',
        label: 'Trade Price',
        type: 'number',
        min: 0,
        key: 'tradePrice',
    },
    { name: 'mrp', label: 'MRP', type: 'number', min: 0, key: 'mrp' },
    {
        name: 'stock_quantity',
        label: 'Stock Amount',
        type: 'number',
        min: 0,
        key: 'stockQuantity',
        hint: 'The count on the shelf, not a change to it.',
    },
] as const;

export default function EditProduct({ product }: { product: Product }) {
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    return (
        <>
            <Head title={`Update ${product.name}`} />

            <div className="mx-auto w-full max-w-xl">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-bold text-ocean-900">
                        Update {product.name}
                    </h1>
                    <Button asChild variant="outline">
                        <Link href={index(teamSlug)}>Cancel</Link>
                    </Button>
                </div>

                <Form
                    {...ProductController.update.form({
                        current_team: teamSlug,
                        product: product.id,
                    })}
                    options={{ preserveScroll: true }}
                    className="mt-8 space-y-5 pb-16"
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
                                    </Label>
                                    <Input
                                        id={field.name}
                                        name={field.name}
                                        type={field.type}
                                        required
                                        step={
                                            field.type === 'number'
                                                ? 1
                                                : undefined
                                        }
                                        min={
                                            'min' in field
                                                ? field.min
                                                : undefined
                                        }
                                        defaultValue={String(
                                            product[field.key],
                                        )}
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
                                <Label
                                    htmlFor="photo"
                                    className="text-ocean-900"
                                >
                                    Photo
                                </Label>

                                {product.photoUrl && (
                                    <img
                                        src={product.photoUrl}
                                        alt=""
                                        className="size-20 rounded border border-ocean-100 object-contain"
                                    />
                                )}

                                <Input
                                    id="photo"
                                    name="photo"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                />
                                <p className="text-xs text-ocean-800/60">
                                    Leave empty to keep the current photo.
                                </p>
                                <InputError message={errors.photo} />
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full bg-ocean-600 hover:bg-ocean-700"
                                data-test="update-product-button"
                            >
                                {processing && <Spinner />}
                                Save changes
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
