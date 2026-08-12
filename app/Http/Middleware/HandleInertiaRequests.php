<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        /*
         * The company every shared prop below describes.
         *
         * Falls back to any team the user belongs to, because a null current
         * team must not blank the header. Staff created by their employer have
         * no personal team, so there is nothing else to fall back to — and a
         * page with no company name or logo reads as a broken application
         * rather than a missing setting.
         */
        $team = $user === null
            ? null
            : $user->currentTeam ?? $user->fallbackTeam();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // `$team` is only ever set when `$user` is, so one check covers both.
            'currentTeam' => fn () => $team ? $user->toUserTeam($team) : null,

            /*
             * What this member may do, so the UI can hide what the server would
             * refuse. Hiding is a courtesy only — `EnsureTeamPermission` on the
             * route is what actually decides.
             */
            'can' => fn () => $team ? $user->toPermissionMap($team) : [],

            'companyBrand' => fn () => $team ? [
                'name' => $team->name,
                'logoUrl' => $team->logoUrl(),
                'address' => $team->address,
                'phone' => $team->phone,
                'currencySymbol' => config('company.currency_symbol'),
            ] : null,
            'teams' => fn () => $user?->toUserTeams(includeCurrent: true) ?? [],
        ];
    }
}
