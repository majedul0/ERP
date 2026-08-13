<?php

namespace App\Http\Controllers\Platform;

use App\Enums\BillingPeriod;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The tiers the platform sells.
 *
 * Retiring a plan deactivates it rather than deleting it: companies may still
 * be on it, and their payments name it. A price change applies to the next
 * payment recorded, never to coverage already bought.
 */
class PlanController extends Controller
{
    public function index(): Response
    {
        $plans = Plan::query()
            ->withCount('teams')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'price' => $plan->price,
                'period' => $plan->period->value,
                'periodLabel' => $plan->period->label(),
                'monthlyValue' => $plan->monthlyValue(),
                'isActive' => $plan->is_active,
                'companies' => $plan->teams_count,
            ])
            ->all();

        return Inertia::render('platform/plans', [
            'plans' => $plans,
            'periods' => BillingPeriod::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Plan::create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan created.')]);

        return to_route('platform.plans.index');
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan updated.')]);

        return to_route('platform.plans.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],

            // Whole currency units, like every other amount in this system.
            'price' => ['required', 'integer', 'min:0', 'max:999999999'],
            'period' => ['required', Rule::enum(BillingPeriod::class)],
            'is_active' => ['required', 'boolean'],
        ], [
            'price.integer' => __('The price must be a whole number, with no decimals.'),
        ]);
    }
}
