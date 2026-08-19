<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * How one company's working week is shaped.
 *
 * Exactly one row per company, keyed by `team_id`, so nothing ever has to
 * choose between two. `forTeam()` is the only way to read it and returns an
 * unsaved default rather than null — a company that has never opened the
 * settings screen still has a working week, and every caller would otherwise
 * repeat the same null check.
 *
 * @property int $team_id
 * @property array<int, int> $weekend_days
 * @property int|null $overtime_hourly_rate
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 */
#[Fillable([
    'team_id',
    'weekend_days',
    'overtime_hourly_rate',
])]
class PayrollSetting extends Model
{
    /** Friday and Saturday, as ISO-8601 weekday numbers. */
    public const DEFAULT_WEEKEND_DAYS = [5, 6];

    protected $primaryKey = 'team_id';

    public $incrementing = false;

    protected $keyType = 'int';

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * This company's settings, or the house defaults if it has never saved any.
     *
     * Deliberately not persisted on read: a row written as a side effect of
     * looking at a screen makes "has this company configured itself" an
     * unanswerable question.
     */
    public static function forTeam(Team $team): self
    {
        return static::query()->find($team->id) ?? new self([
            'team_id' => $team->id,
            'weekend_days' => self::DEFAULT_WEEKEND_DAYS,
            'overtime_hourly_rate' => null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekend_days' => 'array',
        ];
    }
}
