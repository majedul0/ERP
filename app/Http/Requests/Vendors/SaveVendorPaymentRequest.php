<?php

namespace App\Http\Requests\Vendors;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveVendorPaymentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            'vendor_id' => [
                'required',
                'integer',
                Rule::exists('vendors', 'id')
                    ->where('team_id', $teamId)
                    ->whereNull('deleted_at'),
            ],
            'bank_id' => [
                'nullable',
                'integer',
                Rule::exists('banks', 'id')
                    ->where('team_id', $teamId)
                    ->whereNull('deleted_at'),
            ],
            'paid_on' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999'],
            'comment' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.integer' => __('The amount must be a whole number, with no decimals.'),
            'amount.min' => __('Enter the amount paid.'),
        ];
    }

    /**
     * The validated request as the shape SaveVendorPayment expects.
     *
     * @return array{vendor_id: int, paid_on: string, amount: int, bank_id: int|null, comment: string|null}
     */
    public function paymentData(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return [
            'vendor_id' => (int) $validated['vendor_id'],
            'paid_on' => (string) $validated['paid_on'],
            'amount' => (int) $validated['amount'],
            'bank_id' => isset($validated['bank_id']) ? (int) $validated['bank_id'] : null,
            'comment' => isset($validated['comment']) ? (string) $validated['comment'] : null,
        ];
    }
}
