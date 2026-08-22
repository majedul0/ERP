import { Head, usePage } from '@inertiajs/react';
import DocumentController from '@/actions/App/Http/Controllers/Documents/DocumentController';
import type { DocumentCategoryOption } from '@/modules/documents';
import { DocumentForm } from '@/modules/documents';

export default function AddDocument({
    categories,
}: {
    categories: DocumentCategoryOption[];
}) {
    const { currentTeam } = usePage().props;

    return (
        <>
            <Head title="Add Document" />

            <div className="mx-auto w-full max-w-2xl">
                <h1 className="text-center text-2xl font-bold text-coffee-900">
                    Add Document
                </h1>

                <DocumentForm
                    form={DocumentController.store.form(
                        currentTeam?.slug ?? '',
                    )}
                    categories={categories}
                    submitLabel="File document"
                    testId="save-document"
                    maxMegabytes={10}
                />
            </div>
        </>
    );
}
