<?php

namespace App\Enums;

/**
 * Why somebody was given a bonus.
 *
 * A fixed list rather than free text, for the reason ExpenseCategory gives:
 * "Eid", "eid bonus" and "Festival" typed by three people would split one line
 * of the accounts into three.
 */
enum BonusType: string
{
    /** Eid, Puja — the ones a Bangladeshi payroll pays twice a year. */
    case Festival = 'festival';

    case Performance = 'performance';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Festival => 'Festival bonus',
            self::Performance => 'Performance bonus',
            self::Other => 'Other',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type) => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
