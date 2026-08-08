<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Support\TenantFileStore;
use Illuminate\Support\Facades\DB;

class DeleteCompanyLogo
{
    /**
     * Remove a company's logo, falling back to the generated wave mark.
     *
     * The path is read from the locked row so a delete racing an upload
     * removes the file that was actually committed.
     */
    public function handle(Team $team): Team
    {
        [$team, $deletedPath] = DB::transaction(function () use ($team): array {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();
            $deletedPath = $team->logo_path;

            if ($deletedPath) {
                $team->update(['logo_path' => null]);
            }

            return [$team, $deletedPath];
        });

        TenantFileStore::delete('logos', $deletedPath);

        return $team;
    }
}
