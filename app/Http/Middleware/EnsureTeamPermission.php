<?php

namespace App\Http\Middleware;

use App\Enums\TeamPermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse a request the member is not allowed to make.
 *
 * The UI hides what someone cannot do, but hiding a button does not remove the
 * URL behind it. This is where the answer is actually decided, which is why
 * every domain route names the permission it needs:
 *
 *     ->middleware(EnsureTeamPermission::class.':invoice:update')
 *
 * Runs after `EnsureTeamMembership`, so the current team is already resolved
 * and known to belong to this user.
 */
class EnsureTeamPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        $team = $user?->currentTeam;

        abort_if($user === null || $team === null, 403);

        foreach ($permissions as $value) {
            $permission = TeamPermission::tryFrom($value);

            // A typo in a route definition must fail loudly in development
            // rather than quietly granting access to everyone.
            abort_if($permission === null, 500, "Unknown permission [{$value}].");

            // Any one of the listed permissions is enough: a screen that both
            // viewers and managers reach names both.
            if ($user->hasTeamPermission($team, $permission)) {
                return $next($request);
            }
        }

        abort(403, __('You do not have permission to do that.'));
    }
}
