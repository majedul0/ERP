<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Teams\DeleteCompanyLogo;
use App\Actions\Teams\UpdateCompanyLogo;
use App\Actions\Teams\UpdateTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CompanyLogoRequest;
use App\Http\Requests\Settings\CompanyProfileRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Company identity settings — name and logo — for the user's current team.
 *
 * Team membership and invitations stay in Teams\TeamController; this surface
 * is the branding half, reached from the dashboard's settings gear.
 */
class CompanyController extends Controller
{
    /**
     * Show the company settings page.
     */
    public function edit(Request $request): Response
    {
        $team = $this->currentTeam($request);

        return Inertia::render('settings/company', [
            'company' => [
                'name' => $team->name,
                'slug' => $team->slug,
                'logoUrl' => $team->logoUrl(),
                'address' => $team->address,
                'phone' => $team->phone,
            ],
            'canUpdate' => Gate::allows('update', $team),
            'maxLogoKilobytes' => (int) config('company.storage.logos.max_kilobytes'),
        ]);
    }

    /**
     * Update the company's name, address and phone.
     */
    public function update(CompanyProfileRequest $request, UpdateTeam $updateTeam): RedirectResponse
    {
        $team = $this->currentTeam($request);

        Gate::authorize('update', $team);

        $updateTeam->handle($team, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company details updated.')]);

        return to_route('company.edit');
    }

    /**
     * Replace the company logo.
     */
    public function updateLogo(CompanyLogoRequest $request, UpdateCompanyLogo $updateLogo): RedirectResponse
    {
        $updateLogo->handle($this->currentTeam($request), $request->file('logo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Logo updated.')]);

        return to_route('company.edit');
    }

    /**
     * Remove the company logo.
     */
    public function destroyLogo(Request $request, DeleteCompanyLogo $deleteLogo): RedirectResponse
    {
        $team = $this->currentTeam($request);

        Gate::authorize('update', $team);

        $deleteLogo->handle($team);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Logo removed.')]);

        return to_route('company.edit');
    }

    /**
     * Resolve the company this request acts on.
     */
    private function currentTeam(Request $request): Team
    {
        $team = $request->user()?->currentTeam;

        abort_if($team === null, 403);

        return $team;
    }
}
