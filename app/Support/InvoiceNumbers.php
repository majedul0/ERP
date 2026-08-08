<?php

namespace App\Support;

use App\Models\Team;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Allocates the next per-company invoice number.
 *
 * Numbers must be sequential, unique per company, and never handed to two
 * people at once — several staff at one company create invoices at the same
 * time, which is exactly when a naive `MAX(sequence_number) + 1` produces
 * duplicates.
 *
 * Three layers, each doing a different job:
 *
 * 1. A Redis lock (`Cache::lock`) serialises allocation per company. Requests
 *    for *different* companies never contend, and the ones that do queue in
 *    Redis rather than piling onto a database row.
 * 2. `SELECT ... FOR UPDATE` on that company's `invoice_sequences` row is the
 *    real allocation. Redis makes contention cheap; this makes it correct, and
 *    still holds if Redis is unavailable, flushed, or the lock expires early.
 * 3. A unique index on `(team_id, invoice_number)` is the backstop. If both
 *    layers above were somehow bypassed, the second insert fails loudly
 *    rather than quietly duplicating an invoice number.
 *
 * Call this inside the transaction that writes the invoice. The Redis lock is
 * released as soon as the number is allocated, but the sequence row stays
 * locked until the surrounding transaction commits — so an invoice that rolls
 * back returns its number rather than burning it.
 */
final class InvoiceNumbers
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
        return Cache::lock("invoice-number:team:{$team->id}", 10)
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
     * Advisory only — it is not reserved, and two people opening the form at
     * the same moment will both see it. The number that lands on the invoice
     * is the one allocated at save time.
     */
    public static function preview(Team $team): string
    {
        $next = DB::table('invoice_sequences')
            ->where('team_id', $team->id)
            ->value('next_number');

        return self::format((int) ($next ?? self::startingNumber()));
    }

    /**
     * Increment the company's sequence row under a row lock, creating it the
     * first time round.
     */
    private static function allocate(Team $team): int
    {
        $current = DB::table('invoice_sequences')
            ->where('team_id', $team->id)
            ->lockForUpdate()
            ->value('next_number');

        if ($current === null) {
            // insertOrIgnore, not insert: if the Redis lock ever fails open,
            // two requests can reach this branch and the loser must not blow
            // up on the primary key.
            DB::table('invoice_sequences')->insertOrIgnore([
                'team_id' => $team->id,
                'next_number' => self::startingNumber(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $current = DB::table('invoice_sequences')
                ->where('team_id', $team->id)
                ->lockForUpdate()
                ->value('next_number');
        }

        $current = (int) $current;

        DB::table('invoice_sequences')
            ->where('team_id', $team->id)
            ->update([
                'next_number' => $current + 1,
                'updated_at' => now(),
            ]);

        return $current;
    }

    private static function format(int $sequence): string
    {
        return config('company.invoices.number_prefix').$sequence;
    }

    private static function startingNumber(): int
    {
        return (int) config('company.invoices.starting_number');
    }
}
