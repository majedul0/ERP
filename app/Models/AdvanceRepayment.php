<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * How much of an advance one payroll run recovered.
 *
 * Bookkeeping only. This is never a ledger line: the advance was the money
 * leaving, and withholding part of a later month's net moves nothing. Its
 * purpose is to let ReplayAdvanceOutstanding derive what is left to recover, so
 * reopening a run gives the outstanding back rather than losing it.
 *
 * @property int $id
 * @property int $team_id
 * @property int $salary_payment_id
 * @property int|null $payroll_line_id
 * @property Carbon $repaid_on
 * @property int $amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SalaryPayment $advance
 */
#[Fillable([
    'team_id',
    'salary_payment_id',
    'payroll_line_id',
    'repaid_on',
    'amount',
])]
class AdvanceRepayment extends Model
{
    /**
     * @return BelongsTo<SalaryPayment, $this>
     */
    public function advance(): BelongsTo
    {
        return $this->belongsTo(SalaryPayment::class, 'salary_payment_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'repaid_on' => 'date',
        ];
    }
}
