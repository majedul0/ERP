<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Money a company paid the platform, and the period it bought.
 *
 * `covers_to` is the load-bearing field: a team's `paid_through` is the latest
 * one across their payments, so this table is the only record of what has been
 * paid for and every other figure is derived from it.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $plan_id
 * @property int $recorded_by
 * @property int $amount
 * @property string|null $method
 * @property Carbon $paid_on
 * @property Carbon $covers_from
 * @property Carbon $covers_to
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read Plan|null $plan
 * @property-read User $recorder
 */
#[Fillable([
    'team_id',
    'plan_id',
    'recorded_by',
    'amount',
    'method',
    'paid_on',
    'covers_from',
    'covers_to',
    'note',
])]
class SubscriptionPayment extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'covers_from' => 'date',
            'covers_to' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
