<?php

namespace App\Support;

/**
 * The boundary where money entering the application becomes an integer.
 *
 * Every money column is a `bigInteger` of whole currency units — taka, not
 * poisha — and there are no fractions anywhere in the system. Amounts are
 * whole on the way in, whole in the database, and whole on the way out, so a
 * total is the plain sum of its lines with nothing to round.
 *
 * Validation rejects fractional input before it reaches here; this is the
 * second line of defence for anything that arrives another way.
 */
final class Money
{
    /**
     * Coerce input to a whole amount.
     *
     * Rounds half-up rather than truncating, so a `90.6` that slipped past
     * validation becomes 91 and not 90 — closer to what was meant, and it can
     * never quietly shave value off an invoice.
     */
    public static function fromInput(mixed $value): int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return 0;
        }

        return (int) round((float) $value);
    }

    /**
     * Multiply an amount by a quantity. Both whole, so the result is exact.
     */
    public static function multiply(int $amount, int $quantity): int
    {
        return $amount * $quantity;
    }
}
