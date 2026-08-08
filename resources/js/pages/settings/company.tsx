import { Head } from '@inertiajs/react';
import { Separator } from '@/components/ui/separator';
import type { CompanySettings } from '@/modules/settings/company';
import { CompanyLogoForm, CompanyNameForm } from '@/modules/settings/company';
import { edit } from '@/routes/company';

type Props = {
    company: CompanySettings;
    canUpdate: boolean;
    maxLogoKilobytes: number;
};

export default function Company({
    company,
    canUpdate,
    maxLogoKilobytes,
}: Props) {
    return (
        <>
            <Head title="Company settings" />

            <h1 className="sr-only">Company settings</h1>

            <CompanyNameForm
                name={company.name}
                slug={company.slug}
                canUpdate={canUpdate}
            />

            <Separator />

            <CompanyLogoForm
                logoUrl={company.logoUrl}
                canUpdate={canUpdate}
                maxLogoKilobytes={maxLogoKilobytes}
            />

            {!canUpdate && (
                <p className="text-sm text-muted-foreground">
                    Only company admins can change these settings.
                </p>
            )}
        </>
    );
}

Company.layout = {
    breadcrumbs: [
        {
            title: 'Company settings',
            href: edit(),
        },
    ],
};
