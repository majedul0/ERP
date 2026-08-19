<?php

namespace App\Models;

use App\Enums\BonusType;
use Database\Factories\EmployeeBonusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A bonus somebody was awarded.
 *
 * The entitlement, not the money: awarding adds to what the company owes them,
 * and paying it out is a SalaryPayment like any other. A run folds in every
 * bonus dated inside its month, so backdating one corrects that month.
 *
 * @property int $id
 * @property int $team_id
 * @property int $employee_id
 * @property int|null $created_by
 * @property BonusType $bonus_type
 * @property Carbon $awarded_on
 * @property int $amount
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read Employee $employee
 */
#[Fillable([
    'team_id',
    'employee_id',
    'created_by',
    'bonus_type',
    'awarded_on',
    'amount',
    'note',
])]
class EmployeeBonus extends Model
{
    /** @use HasFactory<EmployeeBonusFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'awarded_on' => 'date',
            'bonus_type' => BonusType::class,
        ];
    }
}
