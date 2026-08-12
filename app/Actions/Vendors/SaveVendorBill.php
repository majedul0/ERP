<?php

namespace App\Actions\Vendors;

use App\Models\Team;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SaveVendorBill
{
    public function __construct(
        private readonly ReplayVendorBalance $replayBalance,
    ) {}

    /**
     * Record what a vendor has charged, or correct a bill already recorded.
     *
     * The vendor is locked first, matching the order every other action takes,
     * so a bill and a payment landing together queue instead of deadlocking.
     *
     * A correction that moves the bill to a different vendor replays *both*
     * accounts — the one losing the charge and the one gaining it — exactly as
     * UpdatePayment does on the sales side.
     *
     * @param  array{vendor_id: int, billed_on: string, amount: int, reference?: string|null, description?: string|null}  $data
     */
    public function handle(Team $team, User $user, array $data, ?VendorBill $bill = null): VendorBill
    {
        return DB::transaction(function () use ($team, $user, $data, $bill): VendorBill {
            $vendors = $this->lockVendors($team, array_filter([
                (int) $data['vendor_id'],
                $bill?->vendor_id,
            ]));

            $attributes = [
                'vendor_id' => (int) $data['vendor_id'],
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'billed_on' => Carbon::parse($data['billed_on'])->startOfDay(),
                'amount' => Money::fromInput($data['amount']),
            ];

            if ($bill) {
                $bill = VendorBill::whereKey($bill->id)->lockForUpdate()->firstOrFail();
                $bill->update($attributes);
            } else {
                $bill = VendorBill::create([
                    'team_id' => $team->id,
                    'created_by' => $user->id,
                    ...$attributes,
                ]);
            }

            foreach ($vendors as $vendor) {
                $this->replayBalance->handle($vendor);
            }

            return $bill->refresh();
        });
    }

    /**
     * Remove a bill recorded by mistake, and put the account back.
     */
    public function delete(VendorBill $bill): void
    {
        DB::transaction(function () use ($bill): void {
            $bill = VendorBill::whereKey($bill->id)->lockForUpdate()->firstOrFail();

            $vendor = Vendor::whereKey($bill->vendor_id)->lockForUpdate()->firstOrFail();

            $bill->delete();

            $this->replayBalance->handle($vendor);
        });
    }

    /**
     * Lock every vendor the write touches, in ascending id order.
     *
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
