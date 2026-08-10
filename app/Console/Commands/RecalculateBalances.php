<?php

namespace App\Console\Commands;

use App\Actions\Invoices\RecalculateDistributorBalance;
use App\Models\Distributor;
use Illuminate\Console\Command;

/**
 * Replay every distributor's account.
 *
 * Needed after a change to how the ledger is ordered or valued: the figures
 * stored on `invoices.previous_dues`, `invoices.total_amount` and
 * `distributors.balance` were written by the *old* walk, and nothing rewrites
 * them until someone happens to touch that distributor again. Until then a
 * statement and the dues printed on an invoice can disagree, which is the one
 * thing this system is built not to allow.
 *
 * Safe to run at any time: it recomputes from invoices and payments, which it
 * never modifies, so running it twice changes nothing the first run did not.
 */
class RecalculateBalances extends Command
{
    protected $signature = 'app:recalculate-balances
                            {--team= : Limit to one team id}';

    protected $description = "Replay every distributor's running account";

    public function handle(RecalculateDistributorBalance $recalculate): int
    {
        $query = Distributor::query()
            ->when($this->option('team'), fn ($q, $team) => $q->where('team_id', $team))
            ->orderBy('id');

        $total = $query->count();

        if ($total === 0) {
            $this->components->info('No distributors to replay.');

            return self::SUCCESS;
        }

        $changed = 0;
        $bar = $this->output->createProgressBar($total);

        // Chunked so a company with thousands of accounts does not load them
        // all at once; each replay is its own transaction inside the action.
        $query->chunkById(100, function ($distributors) use ($recalculate, $bar, &$changed): void {
            foreach ($distributors as $distributor) {
                $before = $distributor->balance;

                $recalculate->handle($distributor);

                if ($distributor->refresh()->balance !== $before) {
                    $changed++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->components->info("Replayed {$total} account(s); {$changed} balance(s) changed.");

        return self::SUCCESS;
    }
}
