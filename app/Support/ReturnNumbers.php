<?php

namespace App\Support;

use App\Models\Team;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Allocates the next per-company sales-return number.
 *
 * The same three layers as `App\Support\InvoiceNumbers`, over
 * `return_sequences` instead of `invoice_sequences`: a Redis lock so
 * contention is cheap and per-company, `SELECT ... FOR UPDATE` on the sequence
 * row so it is correct even without Redis, and a unique index on
 * `(team_id, return_number)` as the backstop.
 *
 * Kept beside the invoice one rather than folded into a shared class. The two
 * series are independent — RNV1 and INV1 both exist — and the numbering is the
 * part of this system that has to be right under concurrency, so the cost of a
 * near-copy is preferred over a parameterised abstraction that would have to be
 * re-verified for both callers every time it changed.
 *
 * Call inside the transaction that writes the return: the sequence row stays
 * locked until that commits, so a rejected return gives its number back instead
 * of leaving a hole.
 */
final class ReturnNumbers
{
    /**
     * Reserve the next number for a company.
     *
     * @return array{number: string, sequence: int}
     *
     * @throws LockTimeoutException
     */
    public static function next(Team $team): array
    {
        return Cache::lock("return-number:team:{$team->id}", 10)
            ->block(5, function () use ($team): array {
                $sequence = static::allocate($team);

                return [
                    'number' => static::format($sequence),
                    'sequence' => $sequence,
                ];
            });
    }

    /**
     * What the next number *would* be, for display on the create screen.
     *
     * Advisory only — it is not reserved. The number that lands on the return
     * is the one allocated at save time.
     */
    public static function preview(Team $team): string
    {
        $next = DB::table('return_sequences')
            ->where('team_id', $team->id)
            ->value('next_number');

        return self::format((int) ($next ?? self::startingNumber()));
    }

    private static function allocate(Team $team): int
    {
        $current = DB::table('return_sequences')
            ->where('team_id', $team->id)
            ->lockForUpdate()
            ->value('next_number');

        if ($current === null) {
            // insertOrIgnore, not insert: if the Redis lock ever fails open,
            // two requests can reach this branch and the loser must not blow up
            // on the primary key.
            DB::table('return_sequences')->insertOrIgnore([
                'team_id' => $team->id,
                'next_number' => self::startingNumber(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $current = DB::table('return_sequences')
                ->where('team_id', $team->id)
                ->lockForUpdate()
                ->value('next_number');
        }

        $current = (int) $current;

        DB::table('return_sequences')
            ->where('team_id', $team->id)
            ->update([
                'next_number' => $current + 1,
                'updated_at' => now(),
            ]);

        return $current;
    }

    private static function format(int $sequence): string
    {
        return config('company.returns.number_prefix').$sequence;
    }

    private static function startingNumber(): int
    {
        return (int) config('company.returns.starting_number');
    }
}
