/**
 * The page's current query string, with some of it changed.
 *
 * The report screen carries two independent periods — the trend band's and the
 * detail report's — in one URL. Either control sending only its own parameters
 * would silently reset the other one, which looked like the filter forgetting
 * what you had just asked it.
 */
export function mergeQuery(
    changes: Record<string, string | number>,
): Record<string, string | number> {
    const current = Object.fromEntries(
        new URLSearchParams(window.location.search).entries(),
    );

    return { ...current, ...changes };
}
