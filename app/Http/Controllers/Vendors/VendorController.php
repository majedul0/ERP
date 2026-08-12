<?php

namespace App\Http\Controllers\Vendors;

use App\Concerns\ResolvesCurrentTeam;
use App\Data\LedgerEntry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendors\SaveVendorRequest;
use App\Models\Vendor;
use App\Support\VendorLedger;
use App\Support\VendorPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    use ResolvesCurrentTeam;

    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);

        return Inertia::render('company/vendors/index', [
            'vendors' => $team->vendors()
                ->orderBy('name')
                ->get()
                ->map(fn (Vendor $vendor) => VendorPresenter::summary($vendor))
                ->all(),
            'totalPayable' => (int) $team->vendors()->sum('balance'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('company/vendors/create');
    }

    public function store(SaveVendorRequest $request): RedirectResponse
    {
        $team = $this->currentTeam($request);

        $team->vendors()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vendor added.')]);

        return to_route('vendors.index', ['current_team' => $team->slug]);
    }

    /**
     * A vendor's account: who they are, what is owed, and the statement that
     * explains it.
     *
     * See InvoiceController::show() for why `$current_team` must be declared.
     */
    public function show(Request $request, string $current_team, Vendor $vendor): Response
    {
        $team = $this->currentTeam($request);

        abort_unless($vendor->team_id === $team->id, 404);

        $entries = VendorLedger::entries($vendor);

        return Inertia::render('company/vendors/show', [
            'vendor' => VendorPresenter::summary($vendor),
            'statement' => array_map(fn (LedgerEntry $entry) => $entry->toArray(), $entries),
            'totals' => [
                'charged' => array_sum(array_map(fn (LedgerEntry $e) => $e->debit, $entries)),
                'paid' => array_sum(array_map(fn (LedgerEntry $e) => $e->credit, $entries)),
                'due' => $vendor->balance,
            ],
        ]);
    }

    public function edit(Request $request, string $current_team, Vendor $vendor): Response
    {
        $team = $this->currentTeam($request);

        abort_unless($vendor->team_id === $team->id, 404);

        return Inertia::render('company/vendors/edit', [
            'vendor' => VendorPresenter::summary($vendor),
        ]);
    }

    /**
     * Contact details only. The balance is the result of replaying the bills
     * and payments, so a figure typed over it would disagree with the
     * statement on the very next screen.
     */
    public function update(
        SaveVendorRequest $request,
        string $current_team,
        Vendor $vendor,
    ): RedirectResponse {
        $team = $this->currentTeam($request);

        abort_unless($vendor->team_id === $team->id, 404);

        $vendor->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vendor updated.')]);

        return to_route('vendors.show', [
            'current_team' => $team->slug,
            'vendor' => $vendor->id,
        ]);
    }

    /**
     * Remove a vendor who never traded.
     *
     * Refused rather than cascaded when anything hangs off them: their bills
     * are documents received and their payments are money that actually moved,
     * so deleting the account would leave a statement nobody can reproduce. The
     * same rule as DeleteDistributor.
     */
    public function destroy(Request $request, string $current_team, Vendor $vendor): RedirectResponse
    {
        $team = $this->currentTeam($request);

        abort_unless($vendor->team_id === $team->id, 404);

        $bills = $vendor->bills()->count();
        $payments = $vendor->payments()->count();

        if ($bills > 0 || $payments > 0) {
            throw ValidationException::withMessages([
                'vendor' => __(':name has :bills bill(s) and :payments payment(s) on record and cannot be deleted.', [
                    'name' => $vendor->name,
                    'bills' => $bills,
                    'payments' => $payments,
                ]),
            ]);
        }

        $vendor->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vendor deleted.')]);

        return to_route('vendors.index', ['current_team' => $team->slug]);
    }
}
