<?php

namespace App\Actions\Vendors;

use App\Models\Vendor;
use App\Support\VendorLedger;

class ReplayVendorBalance
{
    /**
     * Replay a vendor's whole account and write the balance back.
     *
     * Every write on this side goes through here rather than adjusting a
     * figure, for the same reason the sales side replays: a bill can be
     * backdated, an amount corrected, a payment deleted — and each of those
     * invalidates the running total from that point on. Recomputing is the only
     * way the statement and the stored balance cannot disagree.
     *
     * Must be called inside a transaction that already holds the vendor row;
     * the bills and payments are locked here.
     */
    public function handle(Vendor $vendor): Vendor
    {
        $vendor->update(['balance' => VendorLedger::balance($vendor, lock: true)]);

        return $vendor;
    }
}
