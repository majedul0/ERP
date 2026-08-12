<?php

namespace App\Http\Middleware;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamMembership
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $minimumRole = null): Response
    {
        [$user, $team] = [$request->user(), $this->team($request)];

        abort_if(! $user || ! $team || ! $user->belongsToTeam($team), 403);

        /*
         * A suspended company is closed to everyone in it, owner included.
         * Nothing is deleted — the books wait for the suspension to be lifted
         * from the platform panel.
         */
        abort_if(
            $team->suspended_at !== null && ! $user->is_super_admin,
            403,
            __('This company is suspended. Please contact support.'),
        );

        $this->ensureTeamMemberHasRequiredRole($user, $team, $minimumRole);

        if ($request->route('current_team') && ! $user->isCurrentTeam($team)) {
            $user->switchTeam($team);
        }

        return $next($request);
    }

    /**
     * Ensure the given user has at least the given role, if applicable.
     */
    protected function ensureTeamMemberHasRequiredRole(User $user, Team $team, ?string $minimumRole): void
    {
        if ($minimumRole === null) {
            return;
        }

        $role = $user->teamRole($team);

        $requiredRole = TeamRole::tryFrom($minimumRole);

        abort_if(
            $requiredRole === null ||
            $role === null ||
            ! $role->isAtLeast($requiredRole),
            403,
        );
    }

    /**
     * Get the team associated with the request.
     */
    protected function team(Request $request): ?Team
    {
        $team = $request->route('current_team') ?? $request->route('team');

        if (is_string($team)) {
            $team = Team::where('slug', $team)->first();
        }

        return $team;
    }
}
