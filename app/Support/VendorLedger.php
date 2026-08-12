<?php

namespace App\Support;

use App\Data\LedgerEntry;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorPayment;

/**
 * A vendor's running account: every bill they charged and every payment sent.
 *
 * The mirror of `DistributorLedger`, and deliberately the same shape — bills
 * are debits (the company owes more), payments are credits (it owes less), and
 * the balance is what is still payable. Negative means the vendor is holding an
 * advance.
 *
 * Ordering is by **document date**, then by when the record was entered, then
 * by type and id to break a genuine tie. Entry time decides a shared day for
 * the same reason it does on the sales side: money paid in advance and billed
 * against the same day is ordinary, and forcing bills first would reorder it
 * into nonsense.
 *
 * There is no override and no adjustment line here. The account is a plain
 * running total, which is the property that keeps a statement reconcilable.
 */
final class VendorLedger
{
    /**
     * Build the account in order, with the balance after each line.
     *
     * `$lock` takes `FOR UPDATE` on the rows; pass it when the caller is about
     * to write the balance back, so a concurrent bill or payment cannot slip in
     * between the read and the write.
     *
     * @return list<LedgerEntry>
     */
    public static function entries(Vendor $vendor, bool $lock = false): array
    {
        $bills = VendorBill::query()
            ->where('vendor_id', $vendor->id)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get();

        $payments = VendorPayment::query()
            ->where('vendor_id', $vendor->id)
            ->with('bank')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get();

        $rows = [];

        foreach ($bills as $bill) {
            $rows[] = [
                'sortDate' => $bill->billed_on->format('Y-m-d'),
                'sortEnteredAt' => $bill->created_at?->format('Y-m-d H:i:s.u') ?? '',
                'sortGroup' => 0,
                'sortId' => $bill->id,
                'type' => 'bill',
                'id' => $bill->id,
                'occurredOn' => $bill->billed_on,
                'reference' => $bill->reference ?: 'Bill',
                'description' => $bill->description ?: 'Purchase bill',
                'debit' => $bill->amount,
                'credit' => 0,
            ];
        }

        foreach ($payments as $payment) {
            $rows[] = [
                'sortDate' => $payment->paid_on->format('Y-m-d'),
                'sortEnteredAt' => $payment->created_at?->format('Y-m-d H:i:s.u') ?? '',
                'sortGroup' => 1,
                'sortId' => $payment->id,
                'type' => 'payment',
                'id' => $payment->id,
                'occurredOn' => $payment->paid_on,
                // `bank_id` is nullable, so `??` covers cash in hand.
                'reference' => $payment->bank->name ?? 'Payment',
                'description' => $payment->comment ?: 'Payment made',
                'debit' => 0,
                'credit' => $payment->amount,
            ];
        }

        usort($rows, fn (array $a, array $b) => [$a['sortDate'], $a['sortEnteredAt'], $a['sortGroup'], $a['sortId']]
            <=> [$b['sortDate'], $b['sortEnteredAt'], $b['sortGroup'], $b['sortId']]);

        $balance = 0;
        $entries = [];

        foreach ($rows as $row) {
            $balance = $balance + $row['debit'] - $row['credit'];

            $entries[] = new LedgerEntry(
                type: $row['type'],
                id: $row['id'],
                occurredOn: $row['occurredOn'],
                reference: $row['reference'],
                description: $row['description'],
                debit: $row['debit'],
                credit: $row['credit'],
                balanceAfter: $balance,
            );
        }

        return $entries;
    }

    /**
     * What the vendor is owed right now.
     */
    public static function balance(Vendor $vendor, bool $lock = false): int
    {
        $entries = self::entries($vendor, $lock);

        return $entries === [] ? 0 : end($entries)->balanceAfter;
    }
}
