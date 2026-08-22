/** One selectable kind of paper — App\Enums\DocumentCategory. */
export type DocumentCategoryOption = {
    value: string;
    label: string;
    /** Names the papers people actually hold, so nobody has to guess. */
    hint: string;
    /** Whether this kind usually renews — used to nudge, never to require. */
    usuallyExpires: boolean;
};

/**
 * A filed document.
 *
 * `status` is derived from `expiresOn` against today on every read, never
 * stored — see App\Enums\DocumentStatus.
 */
export type CompanyDocument = {
    id: number;
    title: string;
    category: string;
    categoryLabel: string;
    reference: string | null;
    issuedOn: string | null;
    expiresOn: string | null;
    /** `permanent` | `valid` | `expiring` | `expired` */
    status: string;
    statusLabel: string;
    needsAttention: boolean;
    /** Negative once it has lapsed; null when it never does. */
    daysUntilExpiry: number | null;
    originalName: string;
    mimeType: string;
    sizeBytes: number;
    version: number;
    /** Whether the browser may render it rather than download it. */
    canPreview: boolean;
    uploadedBy: string | null;
    uploadedAt: string | null;
};

/** A file this document used to be. */
export type DocumentVersion = {
    id: number;
    version: number;
    originalName: string;
    sizeBytes: number;
    supersededAt: string | null;
};

export type CompanyDocumentDetail = CompanyDocument & {
    note: string | null;
    versions: DocumentVersion[];
};

export type DocumentSummary = {
    total: number;
    expired: number;
    expiring: number;
    /** How many days before a renewal the warning starts. */
    warningDays: number;
};
