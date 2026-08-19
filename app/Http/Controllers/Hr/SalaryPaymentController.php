<?php

namespace App\Http\Controllers\Hr;

use App\Actions\Payroll\RecordSalaryPayment;
use App\Concerns\ResolvesCurrentTeam;
use App\Enums\SalaryPaymentKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\SaveSalaryPaymentRequest;
use App\Models\Bank;
use App\Models\Employee;
use App\Models\SalaryPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalaryPaymentController extends Controller
{
    use ResolvesCurrentTeam;

    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);

        $payments = $team->salaryPayments()
            ->with(['employee', 'bank'])
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return Inertia::render('company/hr/salary-payments/index', [
            'payments' => $payments->map(fn (SalaryPayment $payment) => [
                'id' => $payment->id,
                'employeeId' => $payment->employee_id,
                'employeeName' => $payment->employee->name,
                'employeeCode' => $payment->employee->employee_code,
                'kind' => $payment->kind->value,
                'kindLabel' => $payment->kind->label(),
                'paidOn' => $payment->paid_on->toDateString(),
                'amount' => $payment->amount,
                'bankName' => $payment->bank?->name,
                'comment' => $payment->comment,
                'outstanding' => $payment->kind === SalaryPaymentKind::Advance
                    ? $payment->outstanding
                    : null,
            ])->all(),
            'total' => (int) $team->salaryPayments()->sum('amount'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('company/hr/salary-payments/create', $this->formOptions($request));
    }

    public function store(SaveSalaryPaymentRequest $request, RecordSalaryPayment $record): RedirectResponse
    {
        $team = $this->currentTeam($request);

        $payment = $record->handle($team, $request->validated(), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':kind recorded for :name.', [
                'kind' => $payment->kind->label(),
                'name' => $payment->employee->name,
            ]),
        ]);

        return to_route('salary-payments.index', ['current_team' => $team->slug]);
    }

    /**
     * Remove a payment recorded by mistake.
     */
    public function destroy(
        Request $request,
        string $current_team,
        SalaryPayment $payment,
        RecordSalaryPayment $record,
    ): RedirectResponse {
        $team = $this->currentTeam($request);

        abort_unless($payment->team_id === $team->id, 404);

        $record->delete($payment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment removed.')]);

        return to_route('salary-payments.index', ['current_team' => $team->slug]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        $team = $this->currentTeam($request);

        return [
            'employees' => $team->employees()
                ->orderBy('name')
                ->get()
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'employeeCode' => $employee->employee_code,
                    'isActive' => $employee->isActive(),
                    // What the company still owes them, so whoever is paying
                    // can see it without opening another screen.
                    'balance' => $employee->balance,
                ])
                ->all(),
            'banks' => $team->banks()
                ->orderBy('name')
                ->get()
                ->map(fn (Bank $bank) => ['id' => $bank->id, 'name' => $bank->name])
                ->all(),
            'kinds' => SalaryPaymentKind::options(),
        ];
    }
}
