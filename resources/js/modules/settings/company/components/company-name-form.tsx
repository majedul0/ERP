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
    canUpdate: boolean;
};

export default function CompanyNameForm({ name, slug, canUpdate }: Props) {
    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Company"
                description="The name shown on the dashboard, invoices, and emails"
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
