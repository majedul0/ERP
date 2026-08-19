<?php

namespace App\Enums;

/**
 * How a person's pay is worked out.
 *
 * The two bases behave differently on exactly one question — what a day off is
 * worth — and that difference runs all the way through payroll:
 *
 * - **Monthly** is a promise of a figure. Attendance can only reduce it, and
 *   weekends and holidays are already inside it, so a monthly employee working
 *   a Friday is not paid twice for it (that is what overtime records).
 * - **Daily** is a price per day worked. Nothing is owed for a day not worked,
 *   and a day worked is a day worked whichever day of the week it falls on.
 *
 * See App\Support\PayrollCalculator, which is the only place that asymmetry is
 * expressed.
 */
enum SalaryType: string
{
    case Monthly = 'monthly';
    case Daily = 'daily';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly salary',
            self::Daily => 'Daily wage',
        };
    }

    /**
     * What the rate figure means, for a form label or a column heading.
     */
    public function rateLabel(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly salary',
            self::Daily => 'Rate per day',
        };
    }

    /**
     * Whether a day nobody marked counts as worked.
     *
     * Salaried staff are marked by exception — an office of thirty people
     * cannot be ticked off every morning, so silence means they were here.
     * A daily wage is the opposite: no record of the day means no day's work
     * to pay for.
     */
    public function unmarkedDayCountsAsWorked(): bool
    {
        return $this === self::Monthly;
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
