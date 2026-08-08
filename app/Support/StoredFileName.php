<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Readable names for files kept on a storage disk: `logo-1.png`, `logo-2.png`,
 * `invoice-2574-1.pdf`.
 *
 * The trailing number is a version, not an identity — the owning record is
 * already named by the directory (`logos/{team_id}/`). Bumping it on every
 * replacement means a new logo arrives at a new URL, so browsers and any CDN
 * in front of the VPS fetch it instead of serving the cached previous one. A
 * fixed name like `logo.png` would look tidier and show users a stale image
 * for as long as their cache holds it.
 */
final class StoredFileName
{
    /**
     * Build the name that supersedes `$previousPath`.
     *
     * `$prefix` and `$extension` are both slugified: these end up as real
     * paths, and neither the uploader's filename nor a config value should be
     * able to steer where the file lands.
     */
    public static function next(string $prefix, ?string $previousPath, string $extension): string
    {
        $prefix = self::slug($prefix, fallback: 'file');

        return sprintf(
            '%s-%d.%s',
            $prefix,
            self::versionOf($prefix, $previousPath) + 1,
            self::slug($extension, fallback: 'bin'),
        );
    }

    /**
     * Read the version out of a name this class produced, or 0 when the path
     * is absent or was written under some earlier scheme.
     */
    private static function versionOf(string $prefix, ?string $previousPath): int
    {
        if ($previousPath === null || $previousPath === '') {
            return 0;
        }

        $name = pathinfo($previousPath, PATHINFO_FILENAME);
        $pattern = '/^'.preg_quote($prefix, '/').'-(\d+)$/';

        return preg_match($pattern, $name, $matches) === 1
            ? (int) $matches[1]
            : 0;
    }

    /**
     * Reduce a segment to lowercase alphanumerics and dashes.
     */
    private static function slug(string $value, string $fallback): string
    {
        $slug = Str::slug($value);

        return $slug === '' ? $fallback : $slug;
    }
}
