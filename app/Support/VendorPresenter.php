<?php

namespace App\Support;

use App\Models\Vendor;

/**
 * Vendor props, shaped once so every screen agrees.
 *
 * Amounts are whole integers from the database and stay whole integers in the
 * payload — see App\Support\Money.
 */
final class VendorPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(Vendor $vendor): array
    {
        return [
            'id' => $vendor->id,
            'name' => $vendor->name,
            'proprietorName' => $vendor->proprietor_name,
            'phone' => $vendor->phone,
            'address' => $vendor->address,
            'thana' => $vendor->thana,
            'district' => $vendor->district,
            'division' => $vendor->division,
            'fullAddress' => $vendor->fullAddress(),

            // What the company still owes. Negative means the vendor is
            // holding an advance.
            'balance' => $vendor->balance,
        ];
    }
}
