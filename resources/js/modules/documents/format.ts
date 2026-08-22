/**
 * Display helpers for the document vault.
 */

/** `120000` -> `117 KB`, `5400000` -> `5.2 MB` */
export function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const kilobytes = bytes / 1024;

    if (kilobytes < 1024) {
        return `${Math.round(kilobytes)} KB`;
    }

    return `${(kilobytes / 1024).toFixed(1)} MB`;
}

/**
 * How a renewal date reads to somebody scanning the list.
 *
 * Plain language rather than a date arithmetic puzzle: "expired 12 days ago"
 * and "expires in 9 days" are what a person needs, and the exact date is in the
 * column beside it for anyone who wants it.
 */
export function formatExpiryDistance(days: number | null): string | null {
    if (days === null) {
        return null;
    }

    if (days < 0) {
        const ago = Math.abs(days);

        return ago === 1 ? 'expired yesterday' : `expired ${ago} days ago`;
    }

    if (days === 0) {
        return 'expires today';
    }

    return days === 1 ? 'expires tomorrow' : `expires in ${days} days`;
}

/** The colour a status badge wears. Meaning never rests on colour alone — the
 * label is always beside it. */
export function statusClasses(status: string): string {
    switch (status) {
        case 'expired':
            return 'bg-red-100 text-red-900';
        case 'expiring':
            return 'bg-amber-100 text-amber-900';
        case 'valid':
            return 'bg-emerald-100 text-emerald-900';
        default:
            return 'bg-coffee-100 text-coffee-800';
    }
}
