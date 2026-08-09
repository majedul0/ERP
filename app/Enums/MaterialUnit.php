<?php

namespace App\Enums;

/**
 * The unit a raw material is counted in.
 *
 * A fixed list rather than a free-text column: purchases add to the same
 * counter the stock screen reads, so "kg" and "Kg" and "kilo" arriving from
 * three different people would silently split one material's stock in two.
 *
 * Quantities are whole integers, exactly like money — a material measured in
 * fractions is recorded in its smaller unit (grams, not kilograms) rather than
 * given a decimal column.
 */
enum MaterialUnit: string
{
    case Kilogram = 'kg';
    case Gram = 'g';
    case Litre = 'l';
    case Millilitre = 'ml';
    case Piece = 'pcs';
    case Metre = 'm';
    case Bag = 'bag';
    case Roll = 'roll';

    public function label(): string
    {
        return match ($this) {
            self::Kilogram => 'Kilogram (kg)',
            self::Gram => 'Gram (g)',
            self::Litre => 'Litre (L)',
            self::Millilitre => 'Millilitre (ml)',
            self::Piece => 'Piece (pcs)',
            self::Metre => 'Metre (m)',
            self::Bag => 'Bag',
            self::Roll => 'Roll',
        };
    }

    /**
     * The short form printed beside a quantity in a table.
     */
    public function short(): string
    {
        return $this->value;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $unit) => ['value' => $unit->value, 'label' => $unit->label()],
            self::cases(),
        );
    }
}
