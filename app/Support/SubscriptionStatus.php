<?php

namespace App\Support;

use App\Models\Team;

/**
 * Where a company stands with the platform, derived from `paid_through`.
 *
 * One place, so the panel's table, the warning banner inside the company and
 * anything added later cannot disagree about what "overdue" means.
 *
 * Nothing here suspends anybody. Access is governed solely by
 * `teams.suspended_at`, which a person sets deliberately — a company must never
 * be locked out by a clock.
 */
final class SubscriptionStatus
{
    /** Days before the date at which the company is warned. */
    public const WARN_WITHIN_DAYS = 7;

    /**
     * @return array<string, mixed>
     */
    public static function for(Team $team): array
    {
        $paidThrough = $team->paid_through;

        if ($paidThrough === null) {
            return [
                'status' => $team->plan_id === null ? 'none' : 'unpaid',
                'paidThrough' => null,
                'daysRemaining' => null,
                'daysOverdue' => null,
                'isOverdue' => $team->plan_id !== null,
                'needsAttention' => $team->plan_id !== null,
            ];
        }

        /*
         * Whole days, counted from the start of today, so the answer does not
         * change with the time of day. Paid through today is still paid: the
         * last day someone bought is a day they get.
         */
        $today = today();
        $days = (int) $today->diffInDays($paidThrough, absolute: false);

        $isOverdue = $paidThrough->lt($today);

        return [
            'status' => $isOverdue ? 'overdue' : 'active',
            'paidThrough' => $paidThrough->toDateString(),
            'daysRemaining' => $isOverdue ? null : $days,
            'daysOverdue' => $isOverdue ? abs($days) : null,
            'isOverdue' => $isOverdue,

            // What the panel tints and the company banner reacts to.
            'needsAttention' => $isOverdue || $days <= self::WARN_WITHIN_DAYS,
        ];
    }
}
