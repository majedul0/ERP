import { Form } from '@inertiajs/react';
import CompanyController from '@/actions/App/Http/Controllers/Settings/CompanyController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    name: string;
    slug: string;
    address: string | null;
    phone: string | null;
    canUpdate: boolean;
};

export default function CompanyNameForm({
    name,
    slug,
    address,
    phone,
    canUpdate,
}: Props) {
    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Company"
                description="Printed in the header of every invoice and challan"
            />

            <Form
                {...CompanyController.update.form()}
                options={{ preserveScroll: true }}
                className="space-y-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="name">Company name</Label>

                            <Input
                                id="name"
                                name="name"
                                className="mt-1 block w-full"
                                defaultValue={name}
                                required
                                disabled={!canUpdate}
                                autoComplete="organization"
                                placeholder="Ocean Consumer Products"
                            />

                            <InputError
                                className="mt-2"
                                message={errors.name}
                            />

                            <p className="text-sm text-muted-foreground">
                                Renaming also updates the workspace address:{' '}
                                <code className="rounded bg-muted px-1 py-0.5">
                                    /{slug}
                                </code>
                            </p>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="address">Address</Label>

                            <Input
                                id="address"
                                name="address"
                                className="mt-1 block w-full"
                                defaultValue={address ?? ''}
                                disabled={!canUpdate}
                                autoComplete="street-address"
                                placeholder="Uttara, Dhaka, Bangladesh"
                            />

                            <InputError
                                className="mt-2"
                                message={errors.address}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="phone">Phone</Label>

                            <Input
                                id="phone"
                                name="phone"
                                className="mt-1 block w-full"
                                defaultValue={phone ?? ''}
                                disabled={!canUpdate}
                                autoComplete="tel"
                                placeholder="01712-932814"
                            />

                            <InputError
                                className="mt-2"
                                message={errors.phone}
                            />
                        </div>

                        <Button
                            disabled={processing || !canUpdate}
                            data-test="update-company-button"
                        >
                            Save
                        </Button>
                    </>
                )}
            </Form>
        </div>
    );
}
