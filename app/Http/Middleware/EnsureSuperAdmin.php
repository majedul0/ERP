<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The platform panel is for whoever runs this system, not for the companies
 * using it.
 *
 * Unauthenticated visitors are sent to the platform login rather than the
 * company one, and a signed-in company user is refused outright — the panel
 * must not advertise itself to somebody who simply guessed the address.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('platform.login');
        }

        abort_unless($user->is_super_admin, 404);

        return $next($request);
    }
}
