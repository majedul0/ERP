<?php

namespace App\Data;

use Carbon\CarbonInterface;

/**
 * One line of a distributor's running account.
 *
 * A debit is something they owe (an invoice); a credit is something that
 * settled it — money paid, or goods returned.
 * `balanceAfter` is what they owed once this line landed, which is what makes
 * the statement reconcile line by line rather than only at the bottom.
 */
final readonly class LedgerEntry
{
    public function __construct(
        /** `invoice`, `payment` or `return`. */
        public string $type,
        public int $id,
        // CarbonInterface, not Carbon: the app runs on CarbonImmutable
        // (see AppServiceProvider::configureDefaults).
        public CarbonInterface $occurredOn,
        /** `INV2587`, or the bank a payment arrived in. */
        public string $reference,
        public string $description,
        public int $debit,
        public int $credit,
        public int $balanceAfter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            // A ledger line is dated, not timed: the walk orders by document
            // date, and an invoice and a payment on the same day are separated
            // by rule, not by clock. See DistributorLedger.
            'occurredOn' => $this->occurredOn->toDateString(),
            'reference' => $this->reference,
            'description' => $this->description,
            'debit' => $this->debit,
            'credit' => $this->credit,
            'balanceAfter' => $this->balanceAfter,
        ];
    }
}
