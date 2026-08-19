<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Database\Factories\AttendanceRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One person, one day, one mark.
 *
 * The absence of a row is meaningful and is why this table does not soft-delete:
 * for a monthly employee an unmarked day means nothing exceptional happened and
 * they are paid for it; for a daily-wage worker it means no day's work to pay
 * for. See App\Enums\SalaryType::unmarkedDayCountsAsWorked().
 *
 * @property int $id
 * @property int $team_id
 * @property int $employee_id
 * @property int|null $marked_by
 * @property Carbon $date
 * @property AttendanceStatus $status
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Employee $employee
 */
#[Fillable([
    'team_id',
    'employee_id',
    'marked_by',
    'date',
    'status',
    'note',
])]
class AttendanceRecord extends Model
{
    /** @use HasFactory<AttendanceRecordFactory> */
    use HasFactory;

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
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => AttendanceStatus::class,
        ];
    }
}
