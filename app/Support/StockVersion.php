<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * A counter, per company, that moves whenever any product's stock does.
 *
 * Several people sell at once, so the stock figures on an open invoice form go
 * stale the moment somebody else saves. Rather than re-query Postgres on a
 * timer to find out, the browser asks for this number: one Redis read, no
 * database, no joins. Only when it has moved does the client fetch the product
 * list again.
 *
 * The counter lives in the cache rather than a column because it is disposable.
 * If Redis is flushed it restarts at zero, every open form sees a difference
 * once, reloads, and carries on — the cost of losing it is one extra refresh,
 * never a wrong stock figure. The database remains the only authority on how
 * much stock there is, and `CreateInvoice` re-checks it under a row lock
 * regardless of what any browser was last told.
 */
final class StockVersion
{
    /**
     * The company's current stock version.
     */
    public static function current(int $teamId): int
    {
        return (int) Cache::get(self::key($teamId), 0);
    }

    /**
     * Record that stock moved.
     *
     * Deferred to after the transaction commits: bumping earlier would invite
     * a browser to refetch and read the old figures, then sit on them because
     * the version already matched.
     */
    public static function bump(int $teamId): void
    {
        DB::afterCommit(function () use ($teamId): void {
            $key = self::key($teamId);

            // `add` only writes when the key is absent, so a concurrent bump
            // cannot reset a live counter back to zero.
            Cache::add($key, 0);
            Cache::increment($key);
        });
    }

    private static function key(int $teamId): string
    {
        return "stock-version:team:{$teamId}";
    }
}
