<?php

namespace App\Actions\Distributors;

use App\Models\Distributor;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteDistributor
{
    /**
     * Remove a distributor who has no trading history.
     *
     * A distributor with invoices or payments against them is refused, not
     * cascaded. Their invoices are documents already printed and handed over,
     * and their payments are money that actually moved; deleting the account
     * those hang from would leave a statement that cannot be reproduced. A
     * distributor who has stopped trading is a separate idea from one entered
     * by mistake, and only the second is a deletion.
     *
     * Soft delete, so even a mistaken removal of an empty account is
     * recoverable in the database.
     */
    public function handle(Distributor $distributor): void
    {
        DB::transaction(function () use ($distributor): void {
            $distributor = Distributor::whereKey($distributor->id)->lockForUpdate()->firstOrFail();

            $invoiceCount = $distributor->invoices()->count();
            $paymentCount = Payment::where('distributor_id', $distributor->id)->count();

            if ($invoiceCount > 0 || $paymentCount > 0) {
                throw ValidationException::withMessages([
                    'distributor' => __(
                        ':name has :invoices invoice(s) and :payments payment(s) on record and cannot be deleted.',
                        [
                            'name' => $distributor->name,
                            'invoices' => $invoiceCount,
                            'payments' => $paymentCount,
                        ],
                    ),
                ]);
            }

            $distributor->delete();
        });
    }
}
