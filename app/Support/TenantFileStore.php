<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Writes an uploaded file to the disk and directory a file type declares in
 * `config/company.php`, under a readable versioned name.
 *
 * Every upload in the app goes through here so the per-tenant directory, the
 * naming convention, and the choice of public vs private disk are decided once
 * per file type instead of once per feature.
 */
final class TenantFileStore
{
    /**
     * Store a file for a tenant and return its disk-relative path.
     *
     * `$previousPath` is the path being replaced, if any — it decides the
     * version in the new name. Pass the value read from the *locked* row, not
     * a possibly stale model.
     *
     * `$nameSuffix` distinguishes files that share a directory. A company has
     * one logo, so `logo-1.png` is unambiguous; it has many products, so their
     * photos are keyed by SKU — `product-ofc-15gm-1.png` — or they would all
     * be `product-1.png` and overwrite one another.
     *
     * @param  string  $type  A key under `company.storage`, e.g. `logos`.
     */
    public static function put(
        string $type,
        int $teamId,
        UploadedFile $file,
        ?string $previousPath = null,
        ?string $nameSuffix = null,
    ): string {
        $config = self::config($type);

        $prefix = filled($nameSuffix)
            ? $config['name_prefix'].'-'.$nameSuffix
            : $config['name_prefix'];

        $path = $file->storeAs(
            $config['path']."/{$teamId}",
            StoredFileName::next($prefix, $previousPath, $file->extension()),
            $config['disk'],
        );

        if ($path === false) {
            throw new RuntimeException("Failed to store a {$type} file for team {$teamId}.");
        }

        return $path;
    }

    /**
     * Remove a stored file. A null or empty path is a no-op.
     */
    public static function delete(string $type, ?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk(self::config($type)['disk'])->delete($path);
    }

    /**
     * The public URL for a stored file, or null when there is none.
     */
    public static function url(string $type, ?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return Storage::disk(self::config($type)['disk'])->url($path);
    }

    /**
     * Read a file type's storage settings, checking rather than assuming.
     *
     * Config is untyped, and a typo here would put uploads somewhere nobody
     * looks — better to fail on boot-adjacent code than to silently write to
     * a directory named `''`.
     *
     * @return array{disk: string, path: string, name_prefix: string}
     */
    private static function config(string $type): array
    {
        $config = config("company.storage.{$type}");

        if (! is_array($config)) {
            throw new RuntimeException("No storage configured for file type [{$type}].");
        }

        $disk = $config['disk'] ?? null;
        $path = $config['path'] ?? null;
        $namePrefix = $config['name_prefix'] ?? null;

        if (! is_string($disk) || ! is_string($path) || ! is_string($namePrefix)) {
            throw new RuntimeException(
                "Storage config for file type [{$type}] needs a disk, path, and name_prefix.",
            );
        }

        return ['disk' => $disk, 'path' => $path, 'name_prefix' => $namePrefix];
    }
}
