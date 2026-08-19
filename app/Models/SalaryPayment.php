<?php

namespace App\Models;

use App\Enums\SalaryPaymentKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Money paid to somebody who works here.
 *
 * The one table wages leave by. Salary, advances and bonuses all sit here and
 * differ only in `kind`, so App\Support\FinancialReport takes a single sum and
 * cannot count the same taka twice — the same reason material purchases are
 * excluded from net cash there.
 *
 * @property int $id
 * @property int $team_id
 * @property int $employee_id
 * @property int|null $bank_id
 * @property int|null $payroll_run_id
 * @property int|null $created_by
 * @property SalaryPaymentKind $kind
 * @property Carbon $paid_on
 * @property int $amount
 * @property string|null $comment
 * @property int|null $installment_amount
 * @property int $outstanding
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read Employee $employee
 * @property-read Bank|null $bank
 */
#[Fillable([
    'team_id',
    'employee_id',
    'bank_id',
    'payroll_run_id',
    'created_by',
    'kind',
    'paid_on',
    'amount',
    'comment',
    'installment_amount',
    'outstanding',
])]
class SalaryPayment extends Model
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
     * Without the soft-delete scope, for the same reason a payroll line keeps
     * its employee: a payment must still name who received it.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Bank, $this>
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * An advance with money still to recover.
     */
    public function isOpenAdvance(): bool
    {
        return $this->kind === SalaryPaymentKind::Advance && $this->outstanding > 0;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'kind' => SalaryPaymentKind::class,
        ];
    }
}
