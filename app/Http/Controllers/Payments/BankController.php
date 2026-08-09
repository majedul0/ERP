<?php

namespace App\Http\Controllers\Payments;

use App\Concerns\ResolvesCurrentTeam;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The accounts a company receives money into, offered on the payment form.
 */
class BankController extends Controller
{
    use ResolvesCurrentTeam;

    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);

        return Inertia::render('company/banks/index', [
            'banks' => $team->banks()
                ->orderBy('name')
                ->withCount('payments')
                ->get()
                ->map(fn (Bank $bank) => [
                    'id' => $bank->id,
                    'name' => $bank->name,
                    'accountNumber' => $bank->account_number,
                    'paymentsCount' => $bank->payments_count ?? 0,
                ])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $team = $this->currentTeam($request);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('banks', 'name')
                    ->where('team_id', $team->id)
                    ->whereNull('deleted_at'),
            ],
            'account_number' => ['nullable', 'string', 'max:64'],
        ]);

        $team->banks()->create($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bank added.')]);

        return to_route('banks.index', ['current_team' => $team->slug]);
    }
}
