<?php

namespace App\Enums;

/**
 * Why a product's stock moved, when it was not a sale or a return.
 *
 * The sign lives on the quantity, not here: an adjustment can go either way,
 * and asking two columns to agree about direction is how they stop agreeing.
 * What this answers is the question the stock report asks of each row — was
 * this stock arriving, or stock being written off.
 */
enum StockMovementReason: string
{
    /** Goods made. The common case, and what the "+ Add Stock" form records. */
    case Production = 'production';

    /** What a product held when it was first registered. */
    case Opening = 'opening';

    /** A recount: the shelf disagreed with the books and the shelf won. */
    case Adjustment = 'adjustment';

    /** Goods written off — breakage, spoilage, wastage. */
    case Damage = 'damage';

    public function label(): string
    {
        return match ($this) {
            self::Production => 'Production',
            self::Opening => 'Opening stock',
            self::Adjustment => 'Adjustment',
            self::Damage => 'Damaged',
        };
    }

    /**
     * The reasons a person may choose on the add / reduce stock forms.
     *
     * `Opening` is not among them — it is written once, when the product is
     * registered, and a second one would be a second beginning.
     *
     * @return list<self>
     */
    public static function selectable(): array
    {
        return [self::Production, self::Adjustment, self::Damage];
    }
}
