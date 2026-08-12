<?php

namespace App\Http\Controllers\Vendors;

use App\Actions\Vendors\SaveVendorBill;
use App\Concerns\ResolvesCurrentTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendors\SaveVendorBillRequest;
use App\Models\Team;
use App\Models\Vendor;
use App\Models\VendorBill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorBillController extends Controller
{
    use ResolvesCurrentTeam;

    /**
     * Every bill the company has received, newest first.
     */
    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);

        $bills = $team->vendorBills()
            ->with('vendor')
            ->latest('billed_on')
            ->latest('id')
            ->limit(200)
            ->get();

        return Inertia::render('company/vendors/bills/index', [
            'bills' => $bills->map(fn (VendorBill $bill) => $this->present($bill, $team))->all(),
            'total' => (int) $team->vendorBills()->sum('amount'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('company/vendors/bills/create', [
            'vendors' => $this->vendorOptions($request),
        ]);
    }

    public function store(SaveVendorBillRequest $request, SaveVendorBill $saveBill): RedirectResponse
    {
        $team = $this->currentTeam($request);
        $user = $request->user();

        abort_if($user === null, 403);

        $bill = $saveBill->handle($team, $user, $request->billData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bill recorded.')]);

        return to_route('vendors.show', [
            'current_team' => $team->slug,
            'vendor' => $bill->vendor_id,
        ]);
    }

    /**
     * See InvoiceController::show() for why `$current_team` must be declared.
     */
    public function edit(Request $request, string $current_team, VendorBill $bill): Response
    {
        $team = $this->currentTeam($request);

        abort_unless($bill->team_id === $team->id, 404);

        return Inertia::render('company/vendors/bills/edit', [
            'bill' => $this->present($bill, $team),
            'vendors' => $this->vendorOptions($request),
        ]);
    }

    public function update(
        SaveVendorBillRequest $request,
        string $current_team,
        VendorBill $bill,
        SaveVendorBill $saveBill,
    ): RedirectResponse {
        $team = $this->currentTeam($request);
        $user = $request->user();

        abort_unless($bill->team_id === $team->id, 404);
        abort_if($user === null, 403);

        $bill = $saveBill->handle($team, $user, $request->billData(), $bill);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bill updated.')]);

        return to_route('vendors.show', [
            'current_team' => $team->slug,
            'vendor' => $bill->vendor_id,
        ]);
    }

    public function destroy(
        Request $request,
        string $current_team,
        VendorBill $bill,
        SaveVendorBill $saveBill,
    ): RedirectResponse {
        $team = $this->currentTeam($request);

        abort_unless($bill->team_id === $team->id, 404);

        $vendorId = $bill->vendor_id;

        $saveBill->delete($bill);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bill deleted.')]);

        return to_route('vendors.show', [
            'current_team' => $team->slug,
            'vendor' => $vendorId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(VendorBill $bill, Team $team): array
    {
        return [
            'id' => $bill->id,
            'vendorId' => $bill->vendor_id,
            'vendorName' => $bill->vendor->name,
            'vendorUrl' => route('vendors.show', [
                'current_team' => $team->slug,
                'vendor' => $bill->vendor_id,
            ], absolute: false),
            'reference' => $bill->reference,
            'description' => $bill->description,
            'billedOn' => $bill->billed_on->toDateString(),
            'amount' => $bill->amount,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function vendorOptions(Request $request): array
    {
        return $this->currentTeam($request)
            ->vendors()
            ->orderBy('name')
            ->get()
            ->map(fn (Vendor $vendor) => [
                'id' => $vendor->id,
                'name' => $vendor->name,
                'balance' => $vendor->balance,
            ])
            ->all();
    }
}
