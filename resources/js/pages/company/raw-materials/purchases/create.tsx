import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { PurchaseForm } from '@/modules/raw-materials';
import type {
    PurchaseMaterialOption,
    PurchasePayload,
} from '@/modules/raw-materials';
import { index as materialsIndex } from '@/routes/materials';
import { store } from '@/routes/purchases';

export default function RecordPurchase({
    materials,
}: {
    materials: PurchaseMaterialOption[];
}) {
    const { errors, currentTeam } = usePage().props;
    const [processing, setProcessing] = useState(false);
    const teamSlug = currentTeam?.slug ?? '';

    const submit = (payload: PurchasePayload) => {
        setProcessing(true);

        router.post(store(teamSlug).url, payload, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <>
            <Head title="Record Purchase" />

            <div className="mx-auto w-full max-w-5xl">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-bold text-ocean-900">
                        Record Purchase
                    </h1>
                    <Button asChild variant="outline">
                        <Link href={materialsIndex(teamSlug)}>Cancel</Link>
                    </Button>
                </div>

                {materials.length === 0 ? (
                    <p className="rounded-lg border border-ocean-100 bg-white p-8 text-center text-ocean-800/70 shadow-sm">
                        Register a raw material first — a purchase has to be
                        against something.
                    </p>
                ) : (
                    <PurchaseForm
                        materials={materials}
                        errors={errors}
                        processing={processing}
                        onSubmit={submit}
                    />
                )}
            </div>
        </>
    );
}
