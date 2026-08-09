<?php

namespace App\Http\Controllers\Distributors;

use App\Concerns\ResolvesCurrentTeam;
use App\Data\LedgerEntry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Distributors\SaveDistributorRequest;
use App\Models\Distributor;
use App\Support\DistributorLedger;
use App\Support\InvoicePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DistributorController extends Controller
{
    use ResolvesCurrentTeam;

    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);

        return Inertia::render('company/distributors/index', [
            'distributors' => $team->distributors()
                ->orderBy('name')
                ->get()
                ->map(fn (Distributor $distributor) => InvoicePresenter::distributor($distributor))
                ->all(),
        ]);
    }

    /**
     * A distributor's account: who they are, what they owe, and the statement
     * that explains it.
     *
     * See InvoiceController::show() for why `$current_team` must be declared.
     */
    public function show(Request $request, string $current_team, Distributor $distributor): Response
    {
        $team = $this->currentTeam($request);

        abort_unless($distributor->team_id === $team->id, 404);

        $entries = DistributorLedger::entries($distributor);

        return Inertia::render('company/distributors/show', [
            'distributor' => InvoicePresenter::distributor($distributor),

            /*
             * The statement is the same walk that wrote the dues onto each
             * invoice — see RecalculateDistributorBalance — so the closing
             * balance here always equals the distributor's stored balance and
             * the Previous Dues on their next invoice.
             */
            'statement' => array_map(
                fn (LedgerEntry $entry) => $entry->toArray(),
                $entries,
            ),
            'totals' => [
                'charged' => array_sum(array_map(fn (LedgerEntry $e) => $e->debit, $entries)),
                'paid' => array_sum(array_map(fn (LedgerEntry $e) => $e->credit, $entries)),
                'due' => $distributor->balance,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('company/distributors/create');
    }

    public function store(SaveDistributorRequest $request): RedirectResponse
    {
        $team = $this->currentTeam($request);

        $team->distributors()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Distributor added.')]);

        return to_route('distributors.index', ['current_team' => $team->slug]);
    }
}
