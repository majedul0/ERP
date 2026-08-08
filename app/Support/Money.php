<?php

namespace App\Support;

/**
 * Conversion between the decimal amounts people type and the integer minor
 * units the database stores.
 *
 * Every money column is a bigint of minor units (poisha for BDT). Floats are
 * not exact in binary — 0.1 + 0.2 is famously not 0.3 — and an invoicing
 * ledger that drifts by a fraction per line is worse than useless. Integers
 * add up exactly, so totals reconcile no matter how many lines an invoice has.
 */
final class Money
{
    /**
     * Minor units per major unit. 100 poisha to the taka.
     */
    public const SCALE = 100;

    /**
     * Parse user or API input into minor units.
     *
     * Rounds half-up at the minor unit, so `90.005` becomes 9001 rather than
     * being truncated. Anything unparseable is 0 — validation, not this, is
     * responsible for rejecting bad input.
     */
    public static function fromInput(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_int($value)) {
            return $value * self::SCALE;
        }

        return (int) round(((float) $value) * self::SCALE);
    }

    /**
     * Render minor units as a decimal number for display or JSON.
     */
    public static function toDecimal(int $minor): float
    {
        return round($minor / self::SCALE, 2);
    }

    /**
     * Multiply an amount by a whole quantity, staying in minor units.
     */
    public static function multiply(int $minor, int $quantity): int
    {
        return $minor * $quantity;
    }
}
