<?php

namespace App\Actions\Payroll;

use App\Actions\Employees\ReplayEmployeeBalance;
use App\Enums\SalaryPaymentKind;
use App\Models\Employee;
use App\Models\SalaryPayment;
use App\Models\Team;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecordSalaryPayment
{
    public function __construct(
        private readonly ReplayEmployeeBalance $replayBalance,
        private readonly ReplayAdvanceOutstanding $replayAdvance,
    ) {}

    /**
     * Pay somebody — their salary, an advance, or a bonus.
     *
     * All three land in the same table, because they are the same event as far
     * as the company's cash is concerned, and one table is what lets the
     * financial report take a single sum and structurally never count wages
     * twice.
     *
     * An advance starts out fully outstanding. It is not a loan and gets no
     * schedule of its own beyond an installment figure: what it does is decide
     * how much of a later month's net is withheld, and the withholding moves no
     * money — see App\Support\EmployeeLedger.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Team $team, array $data, ?User $actor = null): SalaryPayment
    {
        return DB::transaction(function () use ($team, $data, $actor): SalaryPayment {
            $employee = Employee::query()
                ->where('team_id', $team->id)
                ->whereKey($data['employee_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $kind = SalaryPaymentKind::from($data['kind']);
            $amount = Money::fromInput($data['amount']);

            $payment = SalaryPayment::create([
                'team_id' => $team->id,
                'employee_id' => $employee->id,
                'bank_id' => $data['bank_id'] ?? null,
                'payroll_run_id' => $data['payroll_run_id'] ?? null,
                'created_by' => $actor?->id,
                'kind' => $kind,
                'paid_on' => Carbon::parse($data['paid_on'])->toDateString(),
                'amount' => $amount,
                'comment' => $data['comment'] ?? null,

                // Only an advance carries a schedule; the column stays null for
                // salary and bonus so nothing later mistakes one for the other.
                'installment_amount' => $kind === SalaryPaymentKind::Advance
                    ? Money::fromInput($data['installment_amount'] ?? $amount)
                    : null,

                // Fully outstanding until a payroll run recovers some of it.
                // Replayed immediately so the figure is derived even at birth.
                'outstanding' => 0,
            ]);

            if ($kind === SalaryPaymentKind::Advance) {
                $this->replayAdvance->handle($payment);
            }

            $this->replayBalance->handle($employee);

            return $payment->refresh();
        });
    }

    /**
     * Remove a payment recorded by mistake.
     *
     * Soft, like every other money record here: what was paid is worth being
     * able to recover. Deleting an advance replays it to nothing outstanding
     * and puts the person's balance back.
     */
    public function delete(SalaryPayment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $payment = SalaryPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            $employee = Employee::query()
                ->whereKey($payment->employee_id)
                ->lockForUpdate()
                ->firstOrFail();

            $payment->delete();

            $this->replayBalance->handle($employee);
        });
    }
}
