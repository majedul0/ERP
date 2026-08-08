<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Support\TenantFileStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class UpdateCompanyLogo
{
    /**
     * Replace a company's logo.
     *
     * Files are namespaced per tenant (`logos/{team_id}/`) so one company can
     * never read or overwrite another's uploads, and named `logo-1.png`,
     * `logo-2.png`, … so the directory is legible on the server.
     *
     * The new name is derived from the path already on the row, so the row is
     * locked before the file is written rather than after: two uploads racing
     * each other would otherwise both read version 1, both write `logo-2.png`,
     * and one would silently overwrite the other. The write happens inside the
     * transaction for the same reason, and a failure after it deletes the
     * orphan it would leave behind.
     */
    public function handle(Team $team, UploadedFile $logo): Team
    {
        $writtenPath = null;

        try {
            [$team, $previousPath, $writtenPath] = DB::transaction(function () use ($team, $logo, &$writtenPath): array {
                $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();
                $previousPath = $team->logo_path;

                $writtenPath = TenantFileStore::put('logos', $team->id, $logo, $previousPath);

                $team->update(['logo_path' => $writtenPath]);

                return [$team, $previousPath, $writtenPath];
            });
        } catch (Throwable $e) {
            TenantFileStore::delete('logos', $writtenPath);

            throw $e;
        }

        // Only once the new path is committed, so a crash mid-way leaves the
        // old logo in place rather than a row pointing at a deleted file.
        if ($previousPath !== $writtenPath) {
            TenantFileStore::delete('logos', $previousPath);
        }

        return $team;
    }
}
