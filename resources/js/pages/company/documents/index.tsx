import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatSaleDate } from '@/lib/format';
import { useCan } from '@/modules/company';
import type {
    CompanyDocument,
    DocumentCategoryOption,
    DocumentSummary,
} from '@/modules/documents';
import {
    formatExpiryDistance,
    formatFileSize,
    statusClasses,
} from '@/modules/documents';
import { create, download, show } from '@/routes/documents';

const headCell =
    'bg-coffee-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase whitespace-nowrap';
const bodyCell = 'px-4 py-3 whitespace-nowrap text-coffee-900';

const selectClasses =
    'h-9 rounded-md border border-coffee-200 bg-white px-3 text-sm text-coffee-900 shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none';

function Tile({
    label,
    value,
    tone,
}: {
    label: string;
    value: number;
    tone: 'plain' | 'warn' | 'bad';
}) {
    const toneClasses = {
        plain: 'text-coffee-900',
        warn: 'text-amber-700',
        bad: 'text-red-700',
    }[tone];

    return (
        <div className="rounded-lg border border-coffee-100 bg-white p-4 shadow-sm">
            <p className="text-xs font-semibold tracking-wide text-coffee-800/60 uppercase">
                {label}
            </p>
            <p className={`mt-1 text-2xl font-bold ${toneClasses}`}>{value}</p>
        </div>
    );
}

/**
 * The company's papers, worst first.
 *
 * Sorted by urgency on the server rather than by date, because somebody opening
 * this screen is usually asking "is anything about to lapse", not "what did we
 * file last".
 */
export default function Documents({
    documents,
    categories,
    summary,
}: {
    documents: CompanyDocument[];
    categories: DocumentCategoryOption[];
    summary: DocumentSummary;
}) {
    const can = useCan();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const manages = can('document:manage');

    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('');
    const [onlyAttention, setOnlyAttention] = useState(false);

    const term = search.trim().toLowerCase();

    const visible = documents.filter((document) => {
        if (category !== '' && document.category !== category) {
            return false;
        }

        if (onlyAttention && !document.needsAttention) {
            return false;
        }

        if (term === '') {
            return true;
        }

        return [
            document.title,
            document.reference ?? '',
            document.categoryLabel,
            document.originalName,
        ].some((field) => field.toLowerCase().includes(term));
    });

    return (
        <>
            <Head title="Documents" />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-coffee-900">
                        Documents
                    </h1>
                    <p className="text-sm text-coffee-800/60">
                        Licences, certificates and contracts — and when each one
                        lapses
                    </p>
                </div>

                {manages && (
                    <Button
                        asChild
                        className="bg-coffee-600 hover:bg-coffee-700"
                    >
                        <Link href={create(teamSlug)}>+ Add Document</Link>
                    </Button>
                )}
            </div>

            <div className="mb-5 grid gap-4 sm:grid-cols-3">
                <Tile label="Filed" value={summary.total} tone="plain" />
                <Tile
                    label={`Expiring in ${summary.warningDays} days`}
                    value={summary.expiring}
                    tone="warn"
                />
                <Tile label="Expired" value={summary.expired} tone="bad" />
            </div>

            {(summary.expired > 0 || summary.expiring > 0) && (
                <p className="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    {summary.expired > 0 && (
                        <>
                            <strong>
                                {summary.expired} document
                                {summary.expired === 1 ? '' : 's'} already
                                expired
                            </strong>
                            {summary.expiring > 0 && ', and '}
                        </>
                    )}
                    {summary.expiring > 0 && (
                        <>
                            {summary.expiring} due for renewal within{' '}
                            {summary.warningDays} days
                        </>
                    )}
                    . They are at the top of the list.
                </p>
            )}

            <div className="mb-4 flex flex-wrap items-center gap-3">
                <Input
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Search by title, reference…"
                    className="max-w-xs"
                    aria-label="Search documents"
                />

                <select
                    value={category}
                    onChange={(event) => setCategory(event.target.value)}
                    className={selectClasses}
                    aria-label="Filter by kind"
                >
                    <option value="">All kinds</option>
                    {categories.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>

                <label className="flex items-center gap-2 text-sm text-coffee-800/70">
                    <input
                        type="checkbox"
                        checked={onlyAttention}
                        onChange={(event) =>
                            setOnlyAttention(event.target.checked)
                        }
                        className="size-4 rounded border-coffee-300"
                    />
                    Only ones needing attention
                </label>
            </div>

            <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[60rem] text-sm">
                        <thead>
                            <tr>
                                <th className={headCell}>Document</th>
                                <th className={headCell}>Kind</th>
                                <th className={headCell}>Reference</th>
                                <th className={headCell}>Expires</th>
                                <th className={headCell}>Status</th>
                                <th className={headCell}>File</th>
                                <th className={headCell}>
                                    <span className="sr-only">Open</span>
                                </th>
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-coffee-100">
                            {visible.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-10 text-center text-coffee-800/60"
                                    >
                                        {documents.length === 0
                                            ? 'Nothing filed yet. Trade licence, TIN and BIN certificates are the usual first three.'
                                            : 'No document matches those filters.'}
                                    </td>
                                </tr>
                            )}

                            {visible.map((document) => (
                                <tr
                                    key={document.id}
                                    className={
                                        document.needsAttention
                                            ? 'bg-amber-50/50'
                                            : 'transition-colors hover:bg-coffee-50/60'
                                    }
                                >
                                    <td className={`${bodyCell} font-medium`}>
                                        {document.title}
                                        {document.version > 1 && (
                                            <span className="ml-2 text-xs font-normal text-coffee-800/50">
                                                v{document.version}
                                            </span>
                                        )}
                                    </td>
                                    <td className={bodyCell}>
                                        {document.categoryLabel}
                                    </td>
                                    <td className={bodyCell}>
                                        {document.reference ?? '—'}
                                    </td>
                                    <td className={bodyCell}>
                                        {document.expiresOn ? (
                                            <>
                                                {formatSaleDate(
                                                    document.expiresOn,
                                                )}
                                                <span className="ml-2 text-xs text-coffee-800/60">
                                                    {formatExpiryDistance(
                                                        document.daysUntilExpiry,
                                                    )}
                                                </span>
                                            </>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className={bodyCell}>
                                        <span
                                            className={`rounded px-2 py-0.5 text-xs font-semibold ${statusClasses(document.status)}`}
                                        >
                                            {document.statusLabel}
                                        </span>
                                    </td>
                                    <td className={bodyCell}>
                                        <span className="text-xs text-coffee-800/60">
                                            {formatFileSize(document.sizeBytes)}
                                        </span>
                                    </td>
                                    <td className={bodyCell}>
                                        <div className="flex gap-3">
                                            <Link
                                                href={show({
                                                    current_team: teamSlug,
                                                    document: document.id,
                                                })}
                                                className="text-coffee-700 underline underline-offset-4 hover:text-coffee-900"
                                            >
                                                Open
                                            </Link>
                                            {/* A file download, so a plain
                                                anchor: an Inertia visit would
                                                try to parse the bytes as a
                                                page. */}
                                            <a
                                                href={
                                                    download({
                                                        current_team: teamSlug,
                                                        document: document.id,
                                                    }).url
                                                }
                                                className="text-coffee-700 underline underline-offset-4 hover:text-coffee-900"
                                            >
                                                Download
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
