<?php

namespace App\Actions\Teams;

use App\Models\Team;
use Illuminate\Support\Facades\DB;

class UpdateTeam
{
    /**
     * Update a team's own details.
     *
     * The row is locked for the duration because renaming regenerates the
     * slug (see Team::boot), and two concurrent renames could otherwise
     * settle on the same one.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, array $attributes): Team
    {
        return DB::transaction(function () use ($team, $attributes): Team {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();

            $team->update($attributes);

            return $team;
        });
    }
}
