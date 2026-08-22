<?php

namespace App\Http\Requests\Payments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePaymentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            // Scoped to the company: an id from another tenant must not
            // resolve, or money could be credited to a stranger's account.
            'distributor_id' => [
                'required',
                'integer',
                Rule::exists('distributors', 'id')
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

            // Whole amounts, and at least 1 — a zero payment is a no-op that
            // would only clutter the statement.
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
            'amount.min' => __('Enter the amount received.'),
        ];
    }

    /**
     * The validated request as the shape CreatePayment expects.
     *
     * @return array{distributor_id: int, paid_on: string, amount: int, bank_id: int|null, comment: string|null}
     */
    public function paymentData(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return [
            'distributor_id' => (int) $validated['distributor_id'],
            'paid_on' => (string) $validated['paid_on'],
            'amount' => (int) $validated['amount'],
            'bank_id' => isset($validated['bank_id']) ? (int) $validated['bank_id'] : null,
            'comment' => isset($validated['comment']) ? (string) $validated['comment'] : null,
        ];
    }
}
