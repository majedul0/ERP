<?php

namespace App\Enums;

/**
 * What a payment to an employee was for.
 *
 * All three are money out of the same door on the same day, which is why they
 * share one table: the financial report takes a single sum and structurally
 * cannot count wages twice. The kind decides only what the payment means to the
 * person's account.
 */
enum SalaryPaymentKind: string
{
    /** Settling what a payroll run said they had earned. */
    case Salary = 'salary';

    /**
     * Salary paid early.
     *
     * Not a loan: it is one credit on the account, dated the day the cash left.
     * Its schedule decides how much of a later month's net is withheld, and
     * withholding moves no money — so it never touches the ledger a second
     * time. See App\Support\EmployeeLedger.
     */
    case Advance = 'advance';

    /** A festival or performance bonus, paid out. */
    case Bonus = 'bonus';

    public function label(): string
    {
        return match ($this) {
            self::Salary => 'Salary',
            self::Advance => 'Advance',
            self::Bonus => 'Bonus',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $kind) => ['value' => $kind->value, 'label' => $kind->label()],
            self::cases(),
        );
    }
}
