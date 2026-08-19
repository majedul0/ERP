<?php

namespace App\Actions\Employees;

use App\Models\Employee;
use App\Support\EmployeeLedger;

class ReplayEmployeeBalance
{
    /**
     * Recompute what the company owes somebody, from their whole account.
     *
     * Never incremented, always replayed — the rule the sales and buying
     * ledgers already follow. Approving a run, paying somebody, awarding a
     * bonus and correcting any of those all end here, so there is one answer
     * and no path that forgets to adjust.
     *
     * Must be called inside a transaction that already holds the employee row;
     * the lines, bonuses and payments are locked here.
     */
    public function handle(Employee $employee): Employee
    {
        $employee->update(['balance' => EmployeeLedger::balance($employee, lock: true)]);

        return $employee;
    }
}
