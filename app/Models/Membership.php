<?php

namespace App\Models;

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $user_id
 * @property TeamRole $role
 * @property array<int, string>|null $permissions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User $user
 */
#[Fillable(['team_id', 'user_id', 'role', 'permissions'])]
class Membership extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'team_members';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Get the team that the membership belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user that belongs to this membership.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => TeamRole::class,
            // Null and [] mean different things here — see the migration that
            // added the column.
            'permissions' => 'array',
        ];
    }

    /**
     * What this member may actually do.
     *
     * Their own list when one has been chosen, otherwise the role's defaults.
     * Unknown strings are dropped rather than trusted: a permission removed
     * from the enum must stop granting anything, even if a stale row still
     * names it.
     *
     * @return array<int, TeamPermission>
     */
    public function resolvedPermissions(): array
    {
        if ($this->permissions === null) {
            return $this->role->permissions();
        }

        return array_values(array_filter(array_map(
            fn (string $value) => TeamPermission::tryFrom($value),
            $this->permissions,
        )));
    }
}
