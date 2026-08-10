/**
 * Display formatters for the company surface.
 *
 * Money is whole numbers throughout — see App\Support\Money — so amounts are
 * grouped but never given a decimal part: `13500` renders as `13,500`.
 */

const amountFormatter = new Intl.NumberFormat('en-US', {
    maximumFractionDigits: 0,
});

export function formatAmount(amount: number): string {
    return amountFormatter.format(amount);
}

export function formatMoney(amount: number, currencySymbol: string): string {
    return `${currencySymbol}${formatAmount(amount)}`;
}

const saleDateTimeFormatter = new Intl.DateTimeFormat('en-US', {
    month: '2-digit',
    day: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
});

const saleDateFormatter = new Intl.DateTimeFormat('en-US', {
    month: '2-digit',
    day: '2-digit',
    year: 'numeric',
    // The sale date is a plain calendar date with no time in it. Formatting it
    // in the viewer's timezone would shift `2026-08-08` to the 7th for anyone
    // west of the company, so it is read as UTC and printed as written.
    timeZone: 'UTC',
});

/**
 * `2026-08-08` -> `08/08/2026`
 *
 * Tolerates a full ISO timestamp by reading only its date part. The server
 * sends plain dates for date-only columns, but a presenter that slips back to
 * `toIso8601String()` should render a wrong-looking date at worst — never dump
 * a raw `2026-08-09T00:00:00+00:00` in front of a user, which is exactly what
 * an unguarded `new Date(...)` fallback did.
 */
export function formatSaleDate(date: string): string {
    const parsed = new Date(`${date.slice(0, 10)}T00:00:00Z`);

    if (Number.isNaN(parsed.getTime())) {
        return date;
    }

    return saleDateFormatter.format(parsed);
}

/**
 * A real timestamp — when a record was created — in the viewer's own timezone.
 *
 * Only ever given `created_at`. The sale date has no time of day, and printing
 * one alongside it invents a clock reading nobody entered.
 *
 * `2026-08-08T11:14:00+06:00` -> `08/08/2026 11:14 AM`
 */
export function formatSaleDateTime(isoTimestamp: string): string {
    const date = new Date(isoTimestamp);

    if (Number.isNaN(date.getTime())) {
        return isoTimestamp;
    }

    return saleDateTimeFormatter.format(date).replace(',', '');
}

const clockTimeFormatter = new Intl.DateTimeFormat('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
});

/** `4:35 PM` */
export function formatClockTime(date: Date): string {
    return clockTimeFormatter.format(date);
}

const clockDateFormatter = new Intl.DateTimeFormat('en-US', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
});

/** `Saturday, August 8` */
export function formatClockDate(date: Date): string {
    return clockDateFormatter.format(date);
}
