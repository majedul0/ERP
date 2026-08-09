<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Returned = 'returned';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Statuses a user may switch an invoice to from the invoice screen.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function assignable(): array
    {
        return array_map(
            fn (self $status) => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }

    /**
     * Whether the sale still stands.
     *
     * A live invoice holds its stock out of the warehouse *and* its money on
     * the distributor's account; a void one holds neither. The two always move
     * together — goods coming back and the debt being cleared are the same
     * event — so this is one question, not two.
     *
     * Pending counts as live: it means "sold, not yet driven out", not "not
     * yet committed".
     */
    public function isLive(): bool
    {
        return match ($this) {
            self::Pending, self::Delivered => true,
            self::Cancelled, self::Returned => false,
        };
    }
}
