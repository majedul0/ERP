<?php

namespace App\Actions\Payroll;

use App\Models\AdvanceRepayment;
use App\Models\SalaryPayment;

class ReplayAdvanceOutstanding
{
    /**
     * Recompute how much of an advance is still to be recovered.
     *
     * Derived from the repayments, never decremented — the same rule
     * `teams.paid_through` follows. It is what makes reopening a payroll run
     * safe: the run's lines cascade away, their repayment rows go with them,
     * and replaying hands the outstanding back rather than leaving somebody
     * permanently credited for a recovery that was undone.
     *
     * Never below zero: a repayment bigger than the advance is a bug worth
     * showing as zero rather than as a negative that then reads as a second
     * advance.
     */
    public function handle(SalaryPayment $advance): SalaryPayment
    {
        $repaid = (int) AdvanceRepayment::query()
            ->where('salary_payment_id', $advance->id)
            ->sum('amount');

        $advance->update([
            'outstanding' => max($advance->amount - $repaid, 0),
        ]);

        return $advance;
    }
}
