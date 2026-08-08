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
     * Whether stock has left the building for an invoice in this state.
     *
     * Cancelled and returned invoices put their quantities back; pending and
     * delivered both hold stock, because pending means "sold, not yet driven
     * out", not "not yet committed".
     */
    public function holdsStock(): bool
    {
        return match ($this) {
            self::Pending, self::Delivered => true,
            self::Cancelled, self::Returned => false,
        };
    }
}
