<?php

namespace App\Support;

use App\Models\Team;
use Illuminate\Support\Facades\Storage;

/**
 * How much of the platform a company is actually using.
 *
 * Counted on request rather than stored: these figures are only ever read by
 * one person on one screen, and a stored counter that drifts from the truth is
 * worse than a query that takes a moment.
 */
final class CompanyUsage
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Team $team): array
    {
        return [
            'id' => $team->id,
            'name' => $team->name,
            'slug' => $team->slug,
            'createdAt' => $team->created_at?->toDateString(),
            'suspendedAt' => $team->suspended_at?->toDateString(),
            'isSuspended' => $team->suspended_at !== null,
            'owner' => $team->owner()?->only(['name', 'email']),

            'counts' => [
                'members' => $team->memberships()->count(),
                'products' => $team->products()->count(),
                'distributors' => $team->distributors()->count(),
                'invoices' => $team->invoices()->count(),
                'payments' => $team->payments()->count(),
                'vendors' => $team->vendors()->count(),
                'expenses' => $team->expenses()->count(),
                'materials' => $team->rawMaterials()->count(),
            ],

            // What the company is worth on paper, so a suspension decision is
            // not made blind to what it interrupts.
            'receivable' => (int) $team->distributors()->sum('balance'),

            'storageBytes' => self::storageBytes($team),
            'lastInvoiceAt' => $team->invoices()->max('created_at'),
        ];
    }

    /**
     * Bytes held on disk by this company's uploads.
     *
     * Uploads are namespaced per company — `logos/{team}/…`,
     * `products/{team}/…` — which is what makes this answerable at all. See
     * App\Support\TenantFileStore.
     */
    private static function storageBytes(Team $team): int
    {
        $bytes = 0;

        foreach (['logos', 'products'] as $folder) {
            $path = "{$folder}/{$team->id}";

            if (! Storage::disk('public')->exists($path)) {
                continue;
            }

            foreach (Storage::disk('public')->allFiles($path) as $file) {
                $bytes += Storage::disk('public')->size($file);
            }
        }

        return $bytes;
    }
}
