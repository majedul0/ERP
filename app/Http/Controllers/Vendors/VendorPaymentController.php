<?php

namespace App\Http\Controllers\Vendors;

use App\Actions\Vendors\SaveVendorPayment;
use App\Concerns\ResolvesCurrentTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendors\SaveVendorPaymentRequest;
use App\Models\Bank;
use App\Models\Team;
use App\Models\Vendor;
use App\Models\VendorPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorPaymentController extends Controller
{
    use ResolvesCurrentTeam;

    /**
     * Every payment the company has made, newest first.
     */
    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);

        $payments = $team->vendorPayments()
            ->with(['vendor', 'bank'])
            ->latest('paid_on')
            ->latest('id')
            ->limit(200)
            ->get();

        return Inertia::render('company/vendors/payments/index', [
            'payments' => $payments->map(fn (VendorPayment $payment) => $this->present($payment, $team))->all(),
            'total' => (int) $team->vendorPayments()->sum('amount'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('company/vendors/payments/create', [
            'vendors' => $this->vendorOptions($request),
            'banks' => $this->bankOptions($request),
        ]);
    }

    public function store(SaveVendorPaymentRequest $request, SaveVendorPayment $savePayment): RedirectResponse
    {
        $team = $this->currentTeam($request);
        $user = $request->user();

        abort_if($user === null, 403);

        $payment = $savePayment->handle($team, $user, $request->paymentData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment recorded.')]);

        return to_route('vendors.show', [
            'current_team' => $team->slug,
            'vendor' => $payment->vendor_id,
        ]);
    }

    /**
     * See InvoiceController::show() for why `$current_team` must be declared.
     */
    public function edit(Request $request, string $current_team, VendorPayment $payment): Response
    {
        $team = $this->currentTeam($request);

        abort_unless($payment->team_id === $team->id, 404);

        return Inertia::render('company/vendors/payments/edit', [
            'payment' => $this->present($payment, $team),
            'vendors' => $this->vendorOptions($request),
            'banks' => $this->bankOptions($request),
        ]);
    }

    public function update(
        SaveVendorPaymentRequest $request,
        string $current_team,
        VendorPayment $payment,
        SaveVendorPayment $savePayment,
    ): RedirectResponse {
        $team = $this->currentTeam($request);
        $user = $request->user();

        abort_unless($payment->team_id === $team->id, 404);
        abort_if($user === null, 403);

        $payment = $savePayment->handle($team, $user, $request->paymentData(), $payment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment updated.')]);

        return to_route('vendors.show', [
            'current_team' => $team->slug,
            'vendor' => $payment->vendor_id,
        ]);
    }

    public function destroy(
        Request $request,
        string $current_team,
        VendorPayment $payment,
        SaveVendorPayment $savePayment,
    ): RedirectResponse {
        $team = $this->currentTeam($request);

        abort_unless($payment->team_id === $team->id, 404);

        $vendorId = $payment->vendor_id;

        $savePayment->delete($payment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment deleted.')]);

        return to_route('vendors.show', [
            'current_team' => $team->slug,
            'vendor' => $vendorId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(VendorPayment $payment, Team $team): array
    {
        return [
            'id' => $payment->id,
            'vendorId' => $payment->vendor_id,
            'vendorName' => $payment->vendor->name,
            'vendorUrl' => route('vendors.show', [
                'current_team' => $team->slug,
                'vendor' => $payment->vendor_id,
            ], absolute: false),
            'bankId' => $payment->bank_id,
            'bankName' => $payment->bank?->name,
            'paidOn' => $payment->paid_on->toDateString(),
            'amount' => $payment->amount,
            'comment' => $payment->comment,
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bankOptions(Request $request): array
    {
        return $this->currentTeam($request)
            ->banks()
            ->orderBy('name')
            ->get()
            ->map(fn (Bank $bank) => ['id' => $bank->id, 'name' => $bank->name])
            ->all();
    }
}
