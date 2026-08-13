<?php

namespace App\Enums;

/**
 * What a suspended company is shown.
 *
 * Suspension itself is one thing — nobody gets in — but how that is presented
 * is a business decision, so the platform owner chooses per company.
 *
 * `Notice` is the honest option and the default: it says the account is
 * suspended, that nothing is lost, and who to contact. The other two are
 * deliberately opaque — the company simply meets a broken-looking page. That is
 * a legitimate lever, and worth knowing it usually produces a support call
 * anyway, from somebody who now believes the software failed.
 */
enum SuspensionMode: string
{
    case Notice = 'notice';
    case NotFound = 'not_found';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Notice => 'Suspend with notice',
            self::NotFound => 'Show "not found"',
            self::Error => 'Show a server error',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Notice => 'A page explaining the account is suspended and nothing is lost.',
            self::NotFound => 'A 404. The company sees nothing but a missing page.',
            self::Error => 'A 500. The company sees a server error.',
        };
    }

    /**
     * The HTTP status this mode answers with, or null when it renders the
     * explanation page instead.
     */
    public function status(): ?int
    {
        return match ($this) {
            self::Notice => null,
            self::NotFound => 404,
            self::Error => 500,
        };
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $mode) => [
                'value' => $mode->value,
                'label' => $mode->label(),
                'description' => $mode->description(),
            ],
            self::cases(),
        );
    }
}
