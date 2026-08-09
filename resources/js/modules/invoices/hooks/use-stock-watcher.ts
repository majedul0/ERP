import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

type Options = {
    /** The version this page was rendered with. */
    version: number;
    /** URL of the version endpoint — one Redis read, no database. */
    versionUrl: string;
    /** Off on screens that do not ship a version, such as the edit form. */
    enabled?: boolean;
    /** How often to check in the background. */
    intervalMs?: number;
};

/**
 * Keeps the stock figures on an open invoice form honest while other people
 * are selling.
 *
 * Checking asks the server for one number. Only when that number has moved is
 * the product list actually refetched, and then as a partial reload — the
 * page's other props, and everything typed into the form, stay exactly as they
 * are. Nobody has to reload anything by hand.
 *
 * It checks when the user adds a line (the moment stock matters most), when
 * the tab regains focus, and on a slow timer for a form left open.
 */
export function useStockWatcher({
    version,
    versionUrl,
    enabled = true,
    intervalMs = 20_000,
}: Options) {
    const [refreshedAt, setRefreshedAt] = useState<Date | null>(null);

    // Refs, so the interval and listeners never close over a stale version or
    // fire a second reload while the first is still in flight.
    const knownVersion = useRef(version);
    const checking = useRef(false);

    // In an effect, not during render: a partial reload replaces `version`,
    // and the running interval has to see the new one.
    useEffect(() => {
        knownVersion.current = version;
    }, [version]);

    const check = async () => {
        if (!enabled || checking.current) {
            return;
        }

        checking.current = true;

        try {
            const response = await fetch(versionUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const { version: latest } = (await response.json()) as {
                version: number;
            };

            if (latest !== knownVersion.current) {
                // `reload` keeps component state and scroll position by
                // default, so everything already typed into the form — and
                // wherever the user is on the page — survives the refresh.
                router.reload({
                    only: ['products', 'stockVersion'],
                    onSuccess: () => setRefreshedAt(new Date()),
                });
            }
        } catch {
            // A failed check is not worth interrupting anyone over: the form
            // keeps its last known figures, and the server re-checks stock
            // under a lock when the invoice is actually saved.
        } finally {
            checking.current = false;
        }
    };

    useEffect(() => {
        if (!enabled) {
            return;
        }

        const onFocus = () => void check();
        const timer = setInterval(onFocus, intervalMs);

        window.addEventListener('focus', onFocus);

        return () => {
            clearInterval(timer);
            window.removeEventListener('focus', onFocus);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [enabled, intervalMs, versionUrl]);

    return { checkNow: check, refreshedAt };
}
