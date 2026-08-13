<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What a suspended company sees instead of their books.
 *
 * A page rather than a 403, because "Forbidden" reads as broken software.
 * Somebody whose account is closed for non-payment needs to know that is what
 * happened, that their data is safe, and who to contact.
 */
class SuspendedController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        $team = $user === null
            ? null
            : $user->currentTeam ?? $user->fallbackTeam();

        // Nothing to explain if they are not actually suspended — send them
        // back rather than leave a dead page reachable.
        if ($team === null || $team->suspended_at === null) {
            return $team
                ? redirect("/{$team->slug}/dashboard")
                : redirect()->route('home');
        }

        return Inertia::render('suspended', [
            'company' => [
                'name' => $team->name,
                'suspendedAt' => $team->suspended_at->toDateString(),
            ],
        ]);
    }
}
