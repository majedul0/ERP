<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\CreateTeamMember;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\CreateTeamMemberRequest;
use App\Http\Requests\Teams\UpdateTeamMemberRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TeamMemberController extends Controller
{
    /**
     * Create an account for a member of staff and add them to the company.
     *
     * See CreateTeamMember for why this exists alongside invitations.
     */
    public function store(
        CreateTeamMemberRequest $request,
        Team $team,
        CreateTeamMember $createMember,
    ): RedirectResponse {
        Gate::authorize('addMember', $team);

        /** @var array{name: string, email: string, password: string, role: string, permissions?: array<int, string>} $data */
        $data = $request->validated();

        $user = $createMember->handle($team, $data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name can now sign in with that email and password.', ['name' => $user->name]),
        ]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Update the specified team member's role.
     */
    public function update(UpdateTeamMemberRequest $request, Team $team, User $user): RedirectResponse
    {
        Gate::authorize('updateMember', $team);

        $newRole = TeamRole::from($request->validated('role'));

        $membership = $team->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail();

        /*
         * The owner keeps every permission, always. Someone has to be able to
         * hand access back after a mistake, and locking the owner out of their
         * own company would need a database edit to undo.
         */
        abort_if($membership->role === TeamRole::Owner, 403, __('The owner\'s access cannot be changed.'));

        $membership->update([
            'role' => $newRole,
            // Absent means "follow the role" — see UpdateTeamMemberRequest.
            'permissions' => $request->has('permissions')
                ? $request->validated('permissions')
                : null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member access updated.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Remove the specified team member.
     */
    public function destroy(Team $team, User $user): RedirectResponse
    {
        Gate::authorize('removeMember', $team);

        abort_if($team->owner()?->is($user), 403, __('The team owner cannot be removed.'));

        $team->memberships()
            ->where('user_id', $user->id)
            ->delete();

        if ($user->isCurrentTeam($team)) {
            $user->switchTeam($user->personalTeam());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }
}
