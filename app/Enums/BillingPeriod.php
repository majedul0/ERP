<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

/**
 * How long one subscription payment buys.
 *
 * A fixed list because the panel groups and totals by it: "monthly" and
 * "per month" arriving from two different plans would split one line of the
 * platform's own accounts in two.
 */
enum BillingPeriod: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Yearly => 'Yearly',
        };
    }

    /**
     * The end of one period that starts on the given day.
     *
     * Calendar months, not 30 days: somebody paying on the 5th expects to be
     * paid up to the 5th, and `addMonth()` handles the short months for us.
     */
    public function advance(Carbon $from): Carbon
    {
        return match ($this) {
            self::Monthly => $from->copy()->addMonth(),
            self::Yearly => $from->copy()->addYear(),
        };
    }

    /**
     * What one period is worth per month, for comparing plans side by side.
     *
     * Integer division, so a yearly price that does not divide evenly rounds
     * down rather than inventing fractions of a taka — see App\Support\Money.
     */
    public function monthlyEquivalent(int $price): int
    {
        return match ($this) {
            self::Monthly => $price,
            self::Yearly => intdiv($price, 12),
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $period) => ['value' => $period->value, 'label' => $period->label()],
            self::cases(),
        );
    }
}
