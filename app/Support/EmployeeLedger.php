<?php

namespace App\Support;

use App\Data\LedgerEntry;
use App\Enums\PayrollRunStatus;
use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\PayrollLine;
use App\Models\SalaryPayment;

/**
 * One person's running account with the company.
 *
 * The mirror of App\Support\VendorLedger, and deliberately the same shape: what
 * they earned is a debit, what they were paid is a credit, and the balance is
 * what the company still owes them. **Negative means they have drawn more than
 * they have earned** — an outstanding advance — exactly as a negative vendor
 * balance means the vendor is holding one.
 *
 * ## What is a line, and what is not
 *
 * | Line          | Side   | Dated by                        |
 * |---------------|--------|---------------------------------|
 * | Salary earned | debit  | the last day of the run's month |
 * | Bonus awarded | debit  | `awarded_on`                    |
 * | Any payment   | credit | `paid_on`                       |
 *
 * Two deliberate absences:
 *
 * - **Only approved runs** contribute. A draft is a working figure recomputed
 *   every time it is opened; putting it on somebody's account would mean their
 *   balance moved because a clerk opened a screen.
 * - **Advance deductions are not lines.** The advance itself was the money
 *   leaving, recorded as a credit on the day it left. Withholding part of a
 *   later month's net moves nothing, so counting it again would show somebody
 *   paying for the same advance twice. That is why the debit is `earned()` —
 *   gross plus overtime plus additions — and never `net_payable`.
 *
 * The reconciliation that proves it: earn 10,000, take a 2,000 advance, be paid
 * 8,000, and the balance is zero.
 *
 * Ordering is VendorLedger's rule verbatim — document date, then entry time,
 * then type, then id — because a payment and the month it settles routinely
 * share a day and entry time is the only honest tie-break.
 */
final class EmployeeLedger
{
    /**
     * Build the account in order, with the balance after each line.
     *
     * `$lock` takes `FOR UPDATE` on the rows; pass it when the caller is about
     * to write the balance back.
     *
     * @return list<LedgerEntry>
     */
    public static function entries(Employee $employee, bool $lock = false): array
    {
        $rows = [];

        $lines = PayrollLine::query()
            ->where('employee_id', $employee->id)
            ->whereHas('run', fn ($query) => $query
                ->where('team_id', $employee->team_id)
                ->where('status', PayrollRunStatus::Approved->value)
                ->whereNull('deleted_at'))
            ->with('run')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get();

        foreach ($lines as $line) {
            // Dated to the end of the month it pays for, not to when the run
            // was approved: the work was done across the month.
            $earnedOn = $line->run->period_month->copy()->endOfMonth();

            $rows[] = [
                'sortDate' => $earnedOn->format('Y-m-d'),
                'sortEnteredAt' => $line->created_at?->format('Y-m-d H:i:s.u') ?? '',
                'sortGroup' => 0,
                'sortId' => $line->id,
                'type' => 'salary',
                'id' => $line->id,
                'occurredOn' => $earnedOn,
                'reference' => $line->run->period_month->format('M Y'),
                'description' => 'Salary earned',
                'debit' => $line->earned(),
                'credit' => 0,
            ];
        }

        $bonuses = EmployeeBonus::query()
            ->where('team_id', $employee->team_id)
            ->where('employee_id', $employee->id)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get();

        foreach ($bonuses as $bonus) {
            $rows[] = [
                'sortDate' => $bonus->awarded_on->format('Y-m-d'),
                'sortEnteredAt' => $bonus->created_at?->format('Y-m-d H:i:s.u') ?? '',
                'sortGroup' => 1,
                'sortId' => $bonus->id,
                'type' => 'bonus',
                'id' => $bonus->id,
                'occurredOn' => $bonus->awarded_on,
                'reference' => $bonus->bonus_type->label(),
                'description' => $bonus->note ?: 'Bonus awarded',
                'debit' => $bonus->amount,
                'credit' => 0,
            ];
        }

        $payments = SalaryPayment::query()
            ->where('team_id', $employee->team_id)
            ->where('employee_id', $employee->id)
            ->with('bank')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get();

        foreach ($payments as $payment) {
            $rows[] = [
                'sortDate' => $payment->paid_on->format('Y-m-d'),
                'sortEnteredAt' => $payment->created_at?->format('Y-m-d H:i:s.u') ?? '',
                'sortGroup' => 2,
                'sortId' => $payment->id,
                'type' => $payment->kind->value,
                'id' => $payment->id,
                'occurredOn' => $payment->paid_on,
                // `bank_id` is nullable, so `??` covers cash in hand.
                'reference' => $payment->bank->name ?? 'Cash',
                'description' => $payment->comment ?: $payment->kind->label().' paid',
                'debit' => 0,
                'credit' => $payment->amount,
            ];
        }

        usort($rows, fn (array $a, array $b) => [$a['sortDate'], $a['sortEnteredAt'], $a['sortGroup'], $a['sortId']]
            <=> [$b['sortDate'], $b['sortEnteredAt'], $b['sortGroup'], $b['sortId']]);

        $balance = 0;
        $entries = [];

        foreach ($rows as $row) {
            $balance = $balance + $row['debit'] - $row['credit'];

            $entries[] = new LedgerEntry(
                type: $row['type'],
                id: $row['id'],
                occurredOn: $row['occurredOn'],
                reference: $row['reference'],
                description: $row['description'],
                debit: $row['debit'],
                credit: $row['credit'],
                balanceAfter: $balance,
            );
        }

        return $entries;
    }

    /**
     * What the company still owes this person.
     */
    public static function balance(Employee $employee, bool $lock = false): int
    {
        $entries = self::entries($employee, $lock);

        return $entries === [] ? 0 : end($entries)->balanceAfter;
    }
}
