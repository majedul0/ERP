<?php

namespace App\Actions\Platform;

use App\Models\SubscriptionPayment;
use App\Models\Team;

class ReplaySubscription
{
    /**
     * Recompute how long a company is paid up for.
     *
     * The **only** writer of `teams.paid_through`. It is the latest `covers_to`
     * across their payments — derived, never incremented.
     *
     * Nudging the date forward on each payment would drift the moment one was
     * corrected or deleted, and the stored date would then disagree with the
     * payment list it was supposedly built from. That is the same failure the
     * sales ledger already paid for; see RecalculateDistributorBalance.
     *
     * A company with no payments falls back to null — not subscribed — rather
     * than keeping a date nothing supports.
     */
    public function handle(Team $team): Team
    {
        $paidThrough = SubscriptionPayment::query()
            ->where('team_id', $team->id)
            ->max('covers_to');

        $team->update(['paid_through' => $paidThrough]);

        return $team;
    }
}
