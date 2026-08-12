<?php

namespace App\Actions\Vendors;

use App\Models\Team;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SaveVendorPayment
{
    public function __construct(
        private readonly ReplayVendorBalance $replayBalance,
    ) {}

    /**
     * Record money sent to a vendor, or correct a payment already recorded.
     *
     * Like a payment received, this lands on the running account rather than
     * against one bill — money goes out as a lump against what is owed, which
     * is how the trade settles.
     *
     * @param  array{vendor_id: int, paid_on: string, amount: int, bank_id?: int|null, comment?: string|null}  $data
     */
    public function handle(Team $team, User $user, array $data, ?VendorPayment $payment = null): VendorPayment
    {
        return DB::transaction(function () use ($team, $user, $data, $payment): VendorPayment {
            $vendors = $this->lockVendors($team, array_filter([
                (int) $data['vendor_id'],
                $payment?->vendor_id,
            ]));

            $attributes = [
                'vendor_id' => (int) $data['vendor_id'],
                'bank_id' => $data['bank_id'] ?? null,
                'paid_on' => Carbon::parse($data['paid_on'])->startOfDay(),
                'amount' => Money::fromInput($data['amount']),
                'comment' => $data['comment'] ?? null,
            ];

            if ($payment) {
                $payment = VendorPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
                $payment->update($attributes);
            } else {
                $payment = VendorPayment::create([
                    'team_id' => $team->id,
                    'created_by' => $user->id,
                    ...$attributes,
                ]);
            }

            foreach ($vendors as $vendor) {
                $this->replayBalance->handle($vendor);
            }

            return $payment->refresh();
        });
    }

    /**
     * Remove a payment recorded by mistake; the vendor is owed it again.
     */
    public function delete(VendorPayment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $payment = VendorPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            $vendor = Vendor::whereKey($payment->vendor_id)->lockForUpdate()->firstOrFail();

            $payment->delete();

            $this->replayBalance->handle($vendor);
        });
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, Vendor>
     */
    private function lockVendors(Team $team, array $ids): array
    {
        /** @var array<int, Vendor> */
        return Vendor::query()
            ->where('team_id', $team->id)
            ->whereIn('id', collect($ids)->unique()->sort()->values())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->all();
    }
}
