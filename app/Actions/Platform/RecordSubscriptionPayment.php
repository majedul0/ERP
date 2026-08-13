<?php

namespace App\Actions\Platform;

use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\Team;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecordSubscriptionPayment
{
    public function __construct(
        private readonly ReplaySubscription $replay,
    ) {}

    /**
     * Record money received from a company, or correct a payment already
     * recorded.
     *
     * The period bought is worked out here rather than typed, so two people
     * recording payments cannot disagree about what a month is:
     *
     * - it starts from whichever is **later**, the company's current
     *   `paid_through` or the day they paid — so paying two months late does
     *   not silently hand back the two months they went without, and paying
     *   early stacks onto what they already have;
     * - it ends one plan period after that.
     *
     * The team row is locked for the duration because the replay reads every
     * payment and writes the date back; two payments landing together would
     * otherwise race.
     *
     * @param  array{plan_id: int|null, amount: int, paid_on: string, method?: string|null, note?: string|null}  $data
     */
    public function handle(
        Team $team,
        User $recorder,
        array $data,
        ?SubscriptionPayment $payment = null,
    ): SubscriptionPayment {
        return DB::transaction(function () use ($team, $recorder, $data, $payment): SubscriptionPayment {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();

            $plan = $data['plan_id'] !== null
                ? Plan::find($data['plan_id'])
                : $team->plan;

            $paidOn = Carbon::parse($data['paid_on'])->startOfDay();

            [$coversFrom, $coversTo] = $this->coverage($team, $plan, $paidOn, $payment);

            $attributes = [
                'plan_id' => $plan?->id,
                'amount' => Money::fromInput($data['amount']),
                'method' => $data['method'] ?? null,
                'paid_on' => $paidOn,
                'covers_from' => $coversFrom,
                'covers_to' => $coversTo,
                'note' => $data['note'] ?? null,
            ];

            if ($payment) {
                $payment = SubscriptionPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
                $payment->update($attributes);
            } else {
                $payment = SubscriptionPayment::create([
                    'team_id' => $team->id,
                    'recorded_by' => $recorder->id,
                    ...$attributes,
                ]);
            }

            $this->replay->handle($team);

            return $payment->refresh();
        });
    }

    /**
     * Remove a payment recorded by mistake; the company owes that period again.
     */
    public function delete(SubscriptionPayment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $team = Team::whereKey($payment->team_id)->lockForUpdate()->firstOrFail();

            SubscriptionPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail()->delete();

            $this->replay->handle($team);
        });
    }

    /**
     * The period this payment buys.
     *
     * When correcting an existing payment its own coverage is ignored, so the
     * calculation is made against what the *other* payments already cover —
     * otherwise editing a payment would stack a second period on top of the one
     * it already granted.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function coverage(Team $team, ?Plan $plan, Carbon $paidOn, ?SubscriptionPayment $editing): array
    {
        $coveredElsewhere = SubscriptionPayment::query()
            ->where('team_id', $team->id)
            ->when($editing, fn ($query) => $query->whereKeyNot($editing->id))
            ->max('covers_to');

        $from = $coveredElsewhere !== null
            ? Carbon::parse($coveredElsewhere)->startOfDay()->max($paidOn)
            : $paidOn;

        // No plan yet means nothing is known about how long this buys; record
        // the money and leave the coverage as a single day rather than invent
        // a period.
        $to = $plan
            ? $plan->period->advance($from)
            : $from->copy();

        return [$from, $to];
    }
}
