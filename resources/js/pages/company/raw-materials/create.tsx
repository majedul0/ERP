import { Head, Link, usePage } from '@inertiajs/react';
import RawMaterialController from '@/actions/App/Http/Controllers/RawMaterials/RawMaterialController';
import { Button } from '@/components/ui/button';
import { MaterialForm } from '@/modules/raw-materials';
import type { MaterialUnitOption } from '@/modules/raw-materials';
import { index } from '@/routes/materials';

export default function RegisterMaterial({
    units,
}: {
    units: MaterialUnitOption[];
}) {
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    return (
        <>
            <Head title="Add Raw Material" />

            <div className="mx-auto w-full max-w-xl">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-bold text-ocean-900">
                        Add Raw Material
                    </h1>
                    <Button asChild variant="outline">
                        <Link href={index(teamSlug)}>Cancel</Link>
                    </Button>
                </div>

                <MaterialForm
                    form={RawMaterialController.store.form(teamSlug)}
                    units={units}
                    submitLabel="Add"
                    testId="add-material-button"
                />
            </div>
        </>
    );
}
