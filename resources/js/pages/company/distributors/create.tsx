import { Form, Head, usePage } from '@inertiajs/react';
import DistributorController from '@/actions/App/Http/Controllers/Distributors/DistributorController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

const fields = [
    { name: 'name', label: 'Distributor Name', required: true },
    { name: 'proprietor_name', label: 'Proprietor Name' },
    { name: 'phone', label: 'Phone' },
    { name: 'address', label: 'Address' },
    { name: 'thana', label: 'Thana' },
    { name: 'district', label: 'District' },
    { name: 'division', label: 'Division' },
] as const;

export default function RegisterDistributor() {
    const { currentTeam } = usePage().props;

    return (
        <>
            <Head title="Register New Distributor" />

            <h1 className="mt-2 text-center text-2xl font-bold text-ocean-900">
                Register New Distributor
            </h1>

            <Form
                {...DistributorController.store.form(currentTeam?.slug ?? '')}
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
                                    {'required' in field && (
                                        <span aria-hidden="true">*</span>
                                    )}
                                </Label>
                                <Input
                                    id={field.name}
                                    name={field.name}
                                    required={'required' in field}
                                />
                                <InputError message={errors[field.name]} />
                            </div>
                        ))}

                        <Button
                            type="submit"
                            disabled={processing}
                            className="w-full bg-ocean-600 hover:bg-ocean-700"
                            data-test="add-distributor-button"
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
