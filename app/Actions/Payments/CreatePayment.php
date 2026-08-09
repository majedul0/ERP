<?php

namespace App\Actions\Payments;

use App\Actions\Invoices\RecalculateDistributorBalance;
use App\Models\Distributor;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreatePayment
{
    public function __construct(
        private readonly RecalculateDistributorBalance $recalculateBalance,
    ) {}

    /**
     * Record money received from a distributor.
     *
     * The payment lands on their running account, not on one invoice — money
     * arrives as a lump against what is owed, which is how the trade settles.
     * Recording it replays the account, so the balance drops and every invoice
     * issued after this date carries the reduced figure forward.
     *
     * The distributor is locked first, matching the order CreateInvoice and
     * UpdateInvoice take, so a payment and an invoice landing together queue
     * instead of deadlocking.
     *
     * @param  array{distributor_id: int, paid_on: string, amount: int, bank_id?: int|null, comment?: string|null}  $data
     */
    public function handle(Team $team, User $user, array $data): Payment
    {
        return DB::transaction(function () use ($team, $user, $data): Payment {
            $distributor = Distributor::query()
                ->where('team_id', $team->id)
                ->whereKey($data['distributor_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $payment = Payment::create([
                'team_id' => $team->id,
                'distributor_id' => $distributor->id,
                'bank_id' => $data['bank_id'] ?? null,
                'created_by' => $user->id,
                'paid_on' => Carbon::parse($data['paid_on'])->startOfDay(),
                'amount' => Money::fromInput($data['amount']),
                'comment' => $data['comment'] ?? null,
            ]);

            $this->recalculateBalance->handle($distributor);

            return $payment;
        });
    }
}
