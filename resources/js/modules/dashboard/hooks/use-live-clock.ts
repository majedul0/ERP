import { useEffect, useState } from 'react';

/**
 * A `Date` that refreshes on an interval, for the dashboard clock.
 *
 * The clock only renders down to the minute, so it ticks every 10s rather than
 * every second — the displayed minute is never more than 10s stale.
 */
export function useLiveClock(intervalMs = 10_000): Date {
    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        const id = setInterval(() => setNow(new Date()), intervalMs);

        return () => clearInterval(id);
    }, [intervalMs]);

    return now;
}
