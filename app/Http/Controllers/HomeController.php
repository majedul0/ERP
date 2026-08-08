<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class HomeController extends Controller
{
    /**
     * Show the branded login screen, or send a signed-in user to their
     * company dashboard.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $team = $user ? $user->currentTeam ?? $user->fallbackTeam() : null;

        if ($team) {
            return to_route('dashboard', ['current_team' => $team->slug]);
        }

        return Inertia::render('welcome', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]);
    }
}
