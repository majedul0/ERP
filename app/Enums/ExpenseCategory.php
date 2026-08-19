<?php

namespace App\Enums;

/**
 * What an expense was for.
 *
 * A fixed list rather than free text: the reports screen groups by this, and
 * "Salary", "salaries" and "Staff salary" arriving from three people would
 * split one line of the accounts into three.
 *
 * `Other` exists so nobody is forced into a wrong category to save something —
 * a large Other total is the signal that this list needs another case.
 */
enum ExpenseCategory: string
{
    case Rent = 'rent';
    case Salary = 'salary';
    case Utilities = 'utilities';
    case Transport = 'transport';
    case Marketing = 'marketing';
    case Maintenance = 'maintenance';
    case Office = 'office';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Rent => 'Rent',
            self::Salary => 'Salary & Wages (recorded in Payroll)',
            self::Utilities => 'Utilities',
            self::Transport => 'Transport & Delivery',
            self::Marketing => 'Marketing',
            self::Maintenance => 'Repairs & Maintenance',
            self::Office => 'Office & Supplies',
            self::Other => 'Other',
        };
    }

    /**
     * Whether this category may be chosen for a new expense.
     *
     * Only Salary is closed, and only since payroll arrived: wages are recorded
     * there and counted from `salary_payments`, so an expense under this
     * category would be the same money a second time. The case stays — rows
     * recorded before payroll existed keep their meaning, keep reporting under
     * this label, and can still be edited.
     */
    public function selectable(): bool
    {
        return $this !== self::Salary;
    }

    /**
     * The categories a form may offer.
     *
     * `$including` re-admits one that is otherwise closed, so an expense
     * already recorded under Salary can still be opened and saved without the
     * form silently changing what it was for.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(?self $including = null): array
    {
        return array_values(array_map(
            fn (self $category) => ['value' => $category->value, 'label' => $category->label()],
            array_filter(
                self::cases(),
                fn (self $category) => $category->selectable() || $category === $including,
            ),
        ));
    }
}
