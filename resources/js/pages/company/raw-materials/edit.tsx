import { Head, Link, usePage } from '@inertiajs/react';
import RawMaterialController from '@/actions/App/Http/Controllers/RawMaterials/RawMaterialController';
import { Button } from '@/components/ui/button';
import { MaterialForm } from '@/modules/raw-materials';
import type { MaterialUnitOption, RawMaterial } from '@/modules/raw-materials';
import { index } from '@/routes/materials';

export default function EditMaterial({
    material,
    units,
}: {
    material: RawMaterial;
    units: MaterialUnitOption[];
}) {
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    return (
        <>
            <Head title={`Update ${material.name}`} />

            <div className="mx-auto w-full max-w-xl">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-bold text-coffee-900">
                        Update {material.name}
                    </h1>
                    <Button asChild variant="outline">
                        <Link href={index(teamSlug)}>Cancel</Link>
                    </Button>
                </div>

                <MaterialForm
                    form={RawMaterialController.update.form({
                        current_team: teamSlug,
                        material: material.id,
                    })}
                    units={units}
                    material={material}
                    submitLabel="Save changes"
                    testId="update-material-button"
                />
            </div>
        </>
    );
}
