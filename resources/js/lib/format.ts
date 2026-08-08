/**
 * Display formatters for the company surface.
 *
 * Amounts are grouped but never rounded up: `13500` renders as `13,500`, and
 * `4560.25` as `4,560.25`.
 */

const amountFormatter = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
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

/** `2026-08-08T11:14:00Z` -> `08/08/2026 11:14 AM` */
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
