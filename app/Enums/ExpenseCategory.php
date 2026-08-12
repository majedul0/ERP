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
            self::Salary => 'Salary & Wages',
            self::Utilities => 'Utilities',
            self::Transport => 'Transport & Delivery',
            self::Marketing => 'Marketing',
            self::Maintenance => 'Repairs & Maintenance',
            self::Office => 'Office & Supplies',
            self::Other => 'Other',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $category) => ['value' => $category->value, 'label' => $category->label()],
            self::cases(),
        );
    }
}
