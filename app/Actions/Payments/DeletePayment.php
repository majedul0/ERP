<?php

namespace App\Actions\Payments;

use App\Actions\Invoices\RecalculateDistributorBalance;
use App\Models\Distributor;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class DeletePayment
{
    public function __construct(
        private readonly RecalculateDistributorBalance $recalculateBalance,
    ) {}

    /**
     * Remove a payment entered by mistake — a duplicate, usually.
     *
     * The distributor owes the money again, and every invoice issued after this
     * date carries the higher figure forward, so the account is replayed rather
     * than adjusted. A soft delete: money that was recorded as received is
     * worth being able to recover.
     */
    public function handle(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            $distributor = Distributor::whereKey($payment->distributor_id)
                ->lockForUpdate()
                ->firstOrFail();

            $payment->delete();

            $this->recalculateBalance->handle($distributor);
        });
    }
}
