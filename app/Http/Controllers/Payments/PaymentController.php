<?php

namespace App\Http\Controllers\Payments;

use App\Actions\Payments\CreatePayment;
use App\Actions\Payments\DeletePayment;
use App\Actions\Payments\UpdatePayment;
use App\Concerns\ResolvesCurrentTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\SavePaymentRequest;
use App\Models\Bank;
use App\Models\Distributor;
use App\Models\Payment;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    use ResolvesCurrentTeam;

    /**
     * Every payment the company has received, newest first.
     */
    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);

        return Inertia::render('company/payments/index', [
            'payments' => $team->payments()
                ->with(['distributor', 'bank'])
                ->latest('paid_on')
                ->latest('id')
                ->limit(200)
                ->get()
                ->map(fn (Payment $payment) => [
                    'id' => $payment->id,
                    'paidOn' => $payment->paid_on->toDateString(),
                    'distributorName' => $payment->distributor->name,
                    'distributorUrl' => route('distributors.show', [
                        'current_team' => $team->slug,
                        'distributor' => $payment->distributor_id,
                    ], absolute: false),
                    'bankName' => $payment->bank?->name,
                    'amount' => $payment->amount,
                    'comment' => $payment->comment,
                ])
                ->all(),
        ]);
    }

    /**
     * The add-payment form, for one distributor.
     *
     * See InvoiceController::show() for why `$current_team` must be declared.
     */
    public function create(Request $request, string $current_team, Distributor $distributor): Response
    {
        $team = $this->currentTeam($request);

        abort_unless($distributor->team_id === $team->id, 404);

        return Inertia::render('company/payments/create', [
            'distributor' => [
                'id' => $distributor->id,
                'name' => $distributor->name,
                'balance' => $distributor->balance,
            ],
            'banks' => $this->bankOptions($team),
        ]);
    }

    /**
     * Record the payment and send the user back to the distributor's account.
     */
    public function store(SavePaymentRequest $request, CreatePayment $createPayment): RedirectResponse
    {
        $team = $this->currentTeam($request);

        $payment = $createPayment->handle($team, $request->user(), $request->paymentData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment recorded.')]);

        return to_route('distributors.show', [
            'current_team' => $team->slug,
            'distributor' => $payment->distributor_id,
        ]);
    }

    /**
     * The edit form.
     *
     * Carries every distributor, not just the one on the payment: money
     * credited to the wrong account is one of the mistakes this screen exists
     * to fix.
     *
     * See InvoiceController::show() for why `$current_team` must be declared.
     */
    public function edit(Request $request, string $current_team, Payment $payment): Response
    {
        $team = $this->currentTeam($request);

        abort_unless($payment->team_id === $team->id, 404);

        return Inertia::render('company/payments/edit', [
            'payment' => [
                'id' => $payment->id,
                'distributorId' => $payment->distributor_id,
                'bankId' => $payment->bank_id,
                'paidOn' => $payment->paid_on->toDateString(),
                'amount' => $payment->amount,
                'comment' => $payment->comment,
            ],
            'distributors' => $team->distributors()
                ->orderBy('name')
                ->get()
                ->map(fn (Distributor $distributor) => [
                    'id' => $distributor->id,
                    'name' => $distributor->name,
                ])
                ->all(),
            'banks' => $this->bankOptions($team),
        ]);
    }

    /**
     * Save the correction and replay the account(s) it touches.
     */
    public function update(
        SavePaymentRequest $request,
        string $current_team,
        Payment $payment,
        UpdatePayment $updatePayment,
    ): RedirectResponse {
        $team = $this->currentTeam($request);

        abort_unless($payment->team_id === $team->id, 404);

        $payment = $updatePayment->handle($team, $payment, $request->paymentData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment updated.')]);

        return to_route('distributors.show', [
            'current_team' => $team->slug,
            'distributor' => $payment->distributor_id,
        ]);
    }

    /**
     * Remove a payment entered by mistake.
     */
    public function destroy(
        Request $request,
        string $current_team,
        Payment $payment,
        DeletePayment $deletePayment,
    ): RedirectResponse {
        $team = $this->currentTeam($request);

        abort_unless($payment->team_id === $team->id, 404);

        $distributorId = $payment->distributor_id;

        $deletePayment->handle($payment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment deleted.')]);

        return to_route('distributors.show', [
            'current_team' => $team->slug,
            'distributor' => $distributorId,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bankOptions(Team $team): array
    {
        return $team->banks()
            ->orderBy('name')
            ->get()
            ->map(fn (Bank $bank) => [
                'id' => $bank->id,
                'name' => $bank->name,
            ])
            ->all();
    }
}
