<?php

namespace App\Actions\Payments;

use App\Actions\Invoices\RecalculateDistributorBalance;
use App\Models\Distributor;
use App\Models\Payment;
use App\Models\Team;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UpdatePayment
{
    public function __construct(
        private readonly RecalculateDistributorBalance $recalculateBalance,
    ) {}

    /**
     * Correct a payment — a mistyped amount, the wrong date, the wrong bank,
     * or money credited to the wrong distributor.
     *
     * Every one of those invalidates the account from that point on, so this
     * never patches a balance: it replays. When the payment moves between
     * distributors, *both* accounts are replayed — the one losing the credit
     * and the one gaining it — exactly as UpdateInvoice does when an invoice
     * changes hands.
     *
     * Distributors are locked in ascending id order, the same order every other
     * action takes, so two corrections touching the same pair queue instead of
     * deadlocking.
     *
     * @param  array{distributor_id: int, paid_on: string, amount: int, bank_id?: int|null, comment?: string|null}  $data
     */
    public function handle(Team $team, Payment $payment, array $data): Payment
    {
        return DB::transaction(function () use ($team, $payment, $data): Payment {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            $distributors = $this->lockDistributors($team, [
                $payment->distributor_id,
                (int) $data['distributor_id'],
            ]);

            $payment->update([
                'distributor_id' => (int) $data['distributor_id'],
                'bank_id' => $data['bank_id'] ?? null,
                'paid_on' => Carbon::parse($data['paid_on'])->startOfDay(),
                'amount' => Money::fromInput($data['amount']),
                'comment' => $data['comment'] ?? null,
            ]);

            foreach ($distributors as $distributor) {
                $this->recalculateBalance->handle($distributor);
            }

            return $payment->refresh();
        });
    }

    /**
     * Lock every distributor the correction touches, in ascending id order.
     *
     * @param  array<int, int>  $ids
     * @return array<int, Distributor>
     */
    private function lockDistributors(Team $team, array $ids): array
    {
        /** @var array<int, Distributor> */
        return Distributor::query()
            ->where('team_id', $team->id)
            ->whereIn('id', collect($ids)->unique()->sort()->values())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->all();
    }
}
