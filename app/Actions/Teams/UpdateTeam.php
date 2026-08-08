<?php

namespace App\Actions\Teams;

use App\Models\Team;
use Illuminate\Support\Facades\DB;

class UpdateTeam
{
    /**
     * Rename a team.
     *
     * The row is locked for the duration because renaming regenerates the
     * slug (see Team::boot), and two concurrent renames could otherwise
     * settle on the same one.
     */
    public function handle(Team $team, string $name): Team
    {
        return DB::transaction(function () use ($team, $name): Team {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();

            $team->update(['name' => $name]);

            return $team;
        });
    }
}
