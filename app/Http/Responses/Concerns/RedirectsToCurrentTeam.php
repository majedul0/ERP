<?php

namespace App\Http\Responses\Concerns;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

trait RedirectsToCurrentTeam
{
    protected function redirectPathForCurrentTeam(Request $request, string $redirect): string
    {
        $team = $this->currentTeam($request);

        URL::defaults(['current_team' => $team->slug]);

        return "/{$team->slug}{$redirect}";
    }

    /**
     * The company to drop this user into after signing in.
     *
     * `fallbackTeam()` matters for staff: an employee created by their employer
     * has no personal team, so a null current team would leave them with
     * nothing here.
     *
     * The choice is **persisted**, not just used for the redirect. Everything
     * else — the company name and logo in the header, which company a new
     * invoice belongs to — reads `current_team_id`, so leaving it null sends
     * someone to a working page with no company on it.
     */
    protected function currentTeam(Request $request): Team
    {
        $user = $request->user();

        abort_if(! $user, 403);

        $team = $user->currentTeam ?? $user->personalTeam() ?? $user->fallbackTeam();

        abort_if(! $team, 403);

        if (! $user->isCurrentTeam($team)) {
            $user->switchTeam($team);
        }

        return $team;
    }
}
