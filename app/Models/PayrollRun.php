<?php

namespace App\Models;

use App\Enums\PayrollRunStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One month's payroll.
 *
 * See App\Enums\PayrollRunStatus for what draft and approved each promise, and
 * App\Support\PayrollCalculator for how the figures are worked out.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property Carbon $period_month
 * @property PayrollRunStatus $status
 * @property Carbon|null $approved_at
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read User|null $approver
 * @property-read Collection<int, PayrollLine> $lines
 */
#[Fillable([
    'team_id',
    'created_by',
    'approved_by',
    'period_month',
    'status',
    'approved_at',
    'note',
])]
class PayrollRun extends Model
{
    use SoftDeletes;

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<PayrollLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    /**
     * @return HasMany<SalaryPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'approved_at' => 'datetime',
            'status' => PayrollRunStatus::class,
        ];
    }
}
