import { Head, usePage } from '@inertiajs/react';
import DocumentController from '@/actions/App/Http/Controllers/Documents/DocumentController';
import type {
    CompanyDocumentDetail,
    DocumentCategoryOption,
} from '@/modules/documents';
import { DocumentForm } from '@/modules/documents';

export default function EditDocument({
    document,
    categories,
}: {
    document: CompanyDocumentDetail;
    categories: DocumentCategoryOption[];
}) {
    const { currentTeam } = usePage().props;

    return (
        <>
            <Head title={`Edit ${document.title}`} />

            <div className="mx-auto w-full max-w-2xl">
                <h1 className="text-center text-2xl font-bold text-coffee-900">
                    Edit {document.title}
                </h1>

                <DocumentForm
                    form={DocumentController.update.form({
                        current_team: currentTeam?.slug ?? '',
                        document: document.id,
                    })}
                    categories={categories}
                    document={document}
                    submitLabel="Save changes"
                    testId="save-document"
                    maxMegabytes={10}
                />
            </div>
        </>
    );
}
