<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\RecordSubscriptionPayment;
use App\Enums\BillingPeriod;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * What a company has paid the platform.
 *
 * Recording a payment never writes `paid_through` directly — the action replays
 * it from the payments, so a correction here fixes the date automatically.
 */
class SubscriptionController extends Controller
{
    /**
     * Put a company on a plan, or move them to a different one.
     *
     * Changing a plan does not alter what they have already paid for: their
     * existing coverage stands, and the new price applies from the next
     * payment. Anything else would quietly bill somebody for a period they had
     * already settled.
     */
    public function assignPlan(Request $request, Team $team): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['nullable', 'integer', Rule::exists('plans', 'id')],
        ]);

        $team->update(['plan_id' => $validated['plan_id'] ?? null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan updated.')]);

        return to_route('platform.index');
    }

    public function store(
        Request $request,
        Team $team,
        RecordSubscriptionPayment $record,
    ): RedirectResponse {
        $data = $this->validated($request);

        $user = $request->user();
        abort_if($user === null, 403);

        $record->handle($team, $user, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment recorded.')]);

        return to_route('platform.index');
    }

    public function update(
        Request $request,
        SubscriptionPayment $payment,
        RecordSubscriptionPayment $record,
    ): RedirectResponse {
        $data = $this->validated($request);

        $user = $request->user();
        abort_if($user === null, 403);

        $record->handle($payment->team, $user, $data, $payment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment updated.')]);

        return to_route('platform.index');
    }

    public function destroy(
        SubscriptionPayment $payment,
        RecordSubscriptionPayment $record,
    ): RedirectResponse {
        $record->delete($payment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment deleted.')]);

        return to_route('platform.index');
    }

    /**
     * @return array{plan_id: int|null, amount: int, paid_on: string, method: string|null, note: string|null}
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'plan_id' => ['nullable', 'integer', Rule::exists('plans', 'id')],

            // `integer`, not `numeric`: whole amounts everywhere, and a typed
            // `2500.50` is a mistake to correct rather than round.
            'amount' => ['required', 'integer', 'min:1', 'max:999999999'],
            'paid_on' => ['required', 'date'],
            'method' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'amount.integer' => __('The amount must be a whole number, with no decimals.'),
        ]);

        return [
            'plan_id' => isset($validated['plan_id']) ? (int) $validated['plan_id'] : null,
            'amount' => (int) $validated['amount'],
            'paid_on' => (string) $validated['paid_on'],
            'method' => isset($validated['method']) ? (string) $validated['method'] : null,
            'note' => isset($validated['note']) ? (string) $validated['note'] : null,
        ];
    }

    /**
     * The plans a company can be put on, for the panel's selectors.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function planOptions(): array
    {
        return Plan::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'price' => $plan->price,
                'period' => $plan->period->value,
                'periodLabel' => $plan->period->label(),
                'isActive' => $plan->is_active,
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function periodOptions(): array
    {
        return BillingPeriod::options();
    }
}
