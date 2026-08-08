<?php

namespace App\Http\Controllers\Distributors;

use App\Concerns\ResolvesCurrentTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Distributors\SaveDistributorRequest;
use App\Models\Distributor;
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
