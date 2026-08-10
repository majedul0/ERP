import { Form, Head, Link, usePage } from '@inertiajs/react';
import DistributorController from '@/actions/App/Http/Controllers/Distributors/DistributorController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { DistributorOption } from '@/modules/invoices';
import { show } from '@/routes/distributors';

const fields = [
    { name: 'name', label: 'Distributor Name', key: 'name', required: true },
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

export default function EditDistributor({
    distributor,
}: {
    distributor: DistributorOption;
}) {
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    return (
        <>
            <Head title={`Update ${distributor.name}`} />

            <div className="mx-auto w-full max-w-xl">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-bold text-coffee-900">
                        Update {distributor.name}
                    </h1>
                    <Button asChild variant="outline">
                        <Link
                            href={show({
                                current_team: teamSlug,
                                distributor: distributor.id,
                            })}
                        >
                            Cancel
                        </Link>
                    </Button>
                </div>

                <Form
                    {...DistributorController.update.form({
                        current_team: teamSlug,
                        distributor: distributor.id,
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
                                        defaultValue={
                                            distributor[field.key] ?? ''
                                        }
                                    />
                                    <InputError message={errors[field.name]} />
                                </div>
                            ))}

                            <p className="text-xs text-coffee-800/60">
                                The balance is not edited here — it is the
                                result of replaying this distributor&apos;s
                                invoices and payments. Record a payment to
                                change it.
                            </p>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full bg-coffee-600 hover:bg-coffee-700"
                                data-test="update-distributor-button"
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
