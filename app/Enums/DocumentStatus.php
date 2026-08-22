<?php

namespace App\Enums;

/**
 * Where a document stands against its renewal date.
 *
 * **Derived, never stored.** A status column would be wrong the morning after
 * it was written and would need a scheduled job to keep true; computed from
 * `expires_on` against today, it is right every time it is read — the same
 * reason `SubscriptionStatus` derives active/overdue rather than storing it.
 */
enum DocumentStatus: string
{
    /** No renewal date — an incorporation certificate does not lapse. */
    case Permanent = 'permanent';

    case Valid = 'valid';

    /** Inside the warning window; still valid, but somebody should act. */
    case Expiring = 'expiring';

    case Expired = 'expired';

    /**
     * How long before a renewal date the warning starts.
     *
     * Thirty days because that is roughly what a Bangladeshi trade licence
     * renewal takes end to end — a warning shorter than the process it is
     * warning about arrives too late to be a warning.
     */
    public const WARNING_DAYS = 30;

    public function label(): string
    {
        return match ($this) {
            self::Permanent => 'No expiry',
            self::Valid => 'Valid',
            self::Expiring => 'Expiring soon',
            self::Expired => 'Expired',
        };
    }

    /**
     * Whether this status is something somebody has to do something about.
     */
    public function needsAttention(): bool
    {
        return $this === self::Expiring || $this === self::Expired;
    }

    /**
     * Sort weight, worst first — an expired licence belongs at the top of the
     * list whatever it is called or when it was uploaded.
     */
    public function urgency(): int
    {
        return match ($this) {
            self::Expired => 0,
            self::Expiring => 1,
            self::Valid => 2,
            self::Permanent => 3,
        };
    }
}
