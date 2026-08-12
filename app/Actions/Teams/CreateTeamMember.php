<?php

namespace App\Actions\Teams;

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateTeamMember
{
    /**
     * Create an account for a member of staff and put them in the company.
     *
     * The company hands out the credentials rather than emailing an invitation:
     * these are employees being set up by their employer, often at the next
     * desk, and waiting on an email nobody checks is a poor way to start a
     * shift. Invitations still exist for people who already have an account.
     *
     * Verified on creation, because there is nothing to verify — the address
     * was typed by the person creating the account, and the dashboard sits
     * behind the `verified` middleware.
     *
     * @param  array{name: string, email: string, password: string, role: string, permissions?: array<int, string>|null}  $data
     */
    public function handle(Team $team, array $data): User
    {
        return DB::transaction(function () use ($team, $data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            // forceFill, because `email_verified_at` is deliberately not
            // mass-assignable. Same reasoning as CreateAdminUser.
            $user->forceFill(['email_verified_at' => now()])->save();

            $team->memberships()->create([
                'user_id' => $user->id,
                'role' => TeamRole::from($data['role']),

                /*
                 * Null means "follow the role" — see the migration that added
                 * the column. An empty array is a deliberate "nothing yet",
                 * which is a reasonable way to create somebody before deciding
                 * what they may do.
                 */
                'permissions' => isset($data['permissions'])
                    ? array_values(array_filter(array_map(
                        fn (string $value) => TeamPermission::tryFrom($value)?->value,
                        $data['permissions'],
                    )))
                    : null,
            ]);

            /*
             * Their first sign-in should land in this company, not in the
             * personal team a fresh user would otherwise start in.
             */
            $user->forceFill(['current_team_id' => $team->id])->save();

            return $user;
        });
    }
}
