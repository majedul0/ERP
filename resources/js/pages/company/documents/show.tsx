import { Form, Head, Link, usePage } from '@inertiajs/react';
import DocumentController from '@/actions/App/Http/Controllers/Documents/DocumentController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { formatSaleDate } from '@/lib/format';
import { useCan } from '@/modules/company';
import type { CompanyDocumentDetail } from '@/modules/documents';
import {
    formatExpiryDistance,
    formatFileSize,
    statusClasses,
} from '@/modules/documents';
import { download, edit, index } from '@/routes/documents';

function Row({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="flex justify-between gap-4 border-b border-coffee-100 py-2.5 last:border-0">
            <dt className="text-coffee-800/70">{label}</dt>
            <dd className="text-right font-medium text-coffee-900">
                {value === null || value === '' ? '—' : value}
            </dd>
        </div>
    );
}

export default function DocumentRecord({
    document,
}: {
    document: CompanyDocumentDetail;
}) {
    const can = useCan();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const manages = can('document:manage');

    const fileUrl = (version?: number, inline = false) => {
        const url = download({
            current_team: teamSlug,
            document: document.id,
        }).url;
        const params = new URLSearchParams();

        if (version !== undefined) {
            params.set('version', String(version));
        }

        if (inline) {
            params.set('inline', '1');
        }

        return params.toString() === '' ? url : `${url}?${params.toString()}`;
    };

    return (
        <>
            <Head title={document.title} />

            <div className="mx-auto w-full max-w-3xl">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold text-coffee-900">
                            {document.title}
                        </h1>
                        <p className="mt-1 text-sm text-coffee-800/60">
                            {document.categoryLabel}
                            {document.reference && ` · ${document.reference}`}
                            {document.version > 1 &&
                                ` · version ${document.version}`}
                        </p>
                        <p className="mt-2">
                            <span
                                className={`rounded px-2 py-0.5 text-xs font-semibold ${statusClasses(document.status)}`}
                            >
                                {document.statusLabel}
                            </span>
                            {document.daysUntilExpiry !== null && (
                                <span className="ml-2 text-xs text-coffee-800/60">
                                    {formatExpiryDistance(
                                        document.daysUntilExpiry,
                                    )}
                                </span>
                            )}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild variant="outline">
                            <Link href={index(teamSlug)}>Back</Link>
                        </Button>

                        {document.canPreview && (
                            <Button asChild variant="outline">
                                <a
                                    href={fileUrl(undefined, true)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    View
                                </a>
                            </Button>
                        )}

                        <Button
                            asChild
                            className="bg-coffee-600 hover:bg-coffee-700"
                        >
                            <a href={fileUrl()}>Download</a>
                        </Button>

                        {manages && (
                            <>
                                <Button asChild variant="outline">
                                    <Link
                                        href={edit({
                                            current_team: teamSlug,
                                            document: document.id,
                                        })}
                                    >
                                        Edit
                                    </Link>
                                </Button>

                                <Dialog>
                                    <DialogTrigger asChild>
                                        <Button
                                            variant="destructive"
                                            data-test="delete-document"
                                        >
                                            Remove
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogTitle>
                                            Remove {document.title}?
                                        </DialogTitle>
                                        <DialogDescription>
                                            The record is removed from the list.
                                            The stored files are left on disk
                                            rather than shredded, so this can be
                                            recovered — but nobody in the
                                            company will be able to reach them
                                            from here.
                                        </DialogDescription>

                                        <Form
                                            {...DocumentController.destroy.form(
                                                {
                                                    current_team: teamSlug,
                                                    document: document.id,
                                                },
                                            )}
                                        >
                                            {({ processing }) => (
                                                <DialogFooter className="gap-2">
                                                    <DialogClose asChild>
                                                        <Button variant="secondary">
                                                            Cancel
                                                        </Button>
                                                    </DialogClose>
                                                    <Button
                                                        variant="destructive"
                                                        disabled={processing}
                                                        asChild
                                                    >
                                                        <button
                                                            type="submit"
                                                            data-test="confirm-delete-document"
                                                        >
                                                            Remove document
                                                        </button>
                                                    </Button>
                                                </DialogFooter>
                                            )}
                                        </Form>
                                    </DialogContent>
                                </Dialog>
                            </>
                        )}
                    </div>
                </div>

                <div className="rounded-lg border border-coffee-100 bg-white p-5 shadow-sm">
                    <h2 className="mb-3 text-base font-bold text-coffee-900">
                        Details
                    </h2>
                    <dl className="text-sm">
                        <Row label="Kind" value={document.categoryLabel} />
                        <Row label="Reference" value={document.reference} />
                        <Row
                            label="Issued"
                            value={
                                document.issuedOn
                                    ? formatSaleDate(document.issuedOn)
                                    : null
                            }
                        />
                        <Row
                            label="Expires"
                            value={
                                document.expiresOn
                                    ? formatSaleDate(document.expiresOn)
                                    : 'Never'
                            }
                        />
                        <Row label="File" value={document.originalName} />
                        <Row
                            label="Size"
                            value={formatFileSize(document.sizeBytes)}
                        />
                        <Row
                            label="Filed by"
                            value={
                                document.uploadedBy && document.uploadedAt
                                    ? `${document.uploadedBy} on ${formatSaleDate(document.uploadedAt)}`
                                    : document.uploadedAt
                                      ? formatSaleDate(document.uploadedAt)
                                      : null
                            }
                        />
                    </dl>

                    {document.note && (
                        <p className="mt-4 rounded-md bg-coffee-50 p-3 text-sm whitespace-pre-line text-coffee-800">
                            {document.note}
                        </p>
                    )}
                </div>

                {document.versions.length > 0 && (
                    <div className="mt-5 rounded-lg border border-coffee-100 bg-white p-5 shadow-sm">
                        <h2 className="mb-1 text-base font-bold text-coffee-900">
                            Earlier versions
                        </h2>
                        <p className="mb-3 text-sm text-coffee-800/60">
                            Replacing a file keeps the old one. Last year's
                            licence is what proves the company was licensed last
                            year.
                        </p>

                        <ul className="divide-y divide-coffee-100">
                            {document.versions.map((version) => (
                                <li
                                    key={version.id}
                                    className="flex flex-wrap items-center gap-3 py-2.5 text-sm"
                                >
                                    <span className="font-medium text-coffee-900">
                                        v{version.version}
                                    </span>
                                    <span className="text-coffee-800/70">
                                        {version.originalName}
                                    </span>
                                    <span className="text-xs text-coffee-800/50">
                                        {formatFileSize(version.sizeBytes)}
                                    </span>
                                    {version.supersededAt && (
                                        <span className="text-xs text-coffee-800/50">
                                            replaced{' '}
                                            {formatSaleDate(
                                                version.supersededAt,
                                            )}
                                        </span>
                                    )}
                                    <a
                                        href={fileUrl(version.version)}
                                        className="ml-auto text-coffee-700 underline underline-offset-4 hover:text-coffee-900"
                                    >
                                        Download
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </>
    );
}
