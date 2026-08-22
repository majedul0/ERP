<?php

namespace App\Http\Requests\Payroll;

use App\Enums\SalaryPaymentKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSalaryPaymentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')
                    ->where('team_id', $teamId)
                    ->whereNull('deleted_at'),
            ],

            'kind' => ['required', Rule::enum(SalaryPaymentKind::class)],

            'paid_on' => ['required', 'date_format:Y-m-d'],

            // `integer`, not `numeric`: money here is whole amounts like
            // everywhere else, so a typed 5000.50 is rejected rather than
            // quietly rounded.
            'amount' => ['required', 'integer', 'min:1', 'max:99999999'],

            'bank_id' => [
                'nullable',
                'integer',
                // Null means cash in hand, which is how most wages are paid
                // here — so the field is optional, not defaulted to a bank.
                Rule::exists('banks', 'id')
                    ->where('team_id', $teamId)
                    ->whereNull('deleted_at'),
            ],

            /*
             * How much of an advance each month takes back. Only meaningful for
             * an advance, and defaulted to the whole amount when left empty —
             * recovering it all next month is the safe reading of silence.
             */
            'installment_amount' => [
                'nullable',
                'integer',
                'min:1',
                'max:99999999',
                'lte:amount',
            ],

            /*
             * Which run this settles, when it settles one. Informational — the
             * report counts the payment either way — but it is what lets a run
             * refuse to reopen once it has been paid.
             */
            'payroll_run_id' => [
                'nullable',
                'integer',
                Rule::exists('payroll_runs', 'id')
                    ->where('team_id', $teamId)
                    ->whereNull('deleted_at'),
            ],

            'comment' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.integer' => __('Enter a whole amount, with no decimals.'),
            'installment_amount.lte' => __('The monthly installment cannot be more than the advance.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'employee_id' => 'employee',
            'paid_on' => 'payment date',
            'bank_id' => 'bank',
            'installment_amount' => 'monthly installment',
        ];
    }
}
