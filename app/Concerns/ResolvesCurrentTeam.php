<?php

namespace App\Concerns;

use App\Models\Team;
use Illuminate\Http\Request;

trait ResolvesCurrentTeam
{
    /**
     * The company this request acts on.
     *
     * Team-scoped routes carry the slug in the path and EnsureTeamMembership
     * has already checked membership and switched the user's current team to
     * match, so reading it here cannot pick up a company the user is not in.
     */
    protected function currentTeam(Request $request): Team
    {
        $team = $request->user()?->currentTeam;

        abort_if($team === null, 403);

        return $team;
    }
}
