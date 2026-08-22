<?php

namespace App\Http\Requests\Vendors;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveVendorBillRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            // Scoped to the company: an id from another tenant must not
            // resolve, or a charge could land on a stranger's account.
            'vendor_id' => [
                'required',
                'integer',
                Rule::exists('vendors', 'id')
                    ->where('team_id', $teamId)
                    ->whereNull('deleted_at'),
            ],
            'reference' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:255'],
            'billed_on' => ['required', 'date_format:Y-m-d'],

            // Whole amounts, and at least 1 — a zero bill is a no-op that would
            // only clutter the statement.
            'amount' => ['required', 'integer', 'min:1', 'max:999999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.integer' => __('The amount must be a whole number, with no decimals.'),
            'amount.min' => __('Enter the amount billed.'),
        ];
    }

    /**
     * The validated request as the shape SaveVendorBill expects.
     *
     * @return array{vendor_id: int, billed_on: string, amount: int, reference: string|null, description: string|null}
     */
    public function billData(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return [
            'vendor_id' => (int) $validated['vendor_id'],
            'billed_on' => (string) $validated['billed_on'],
            'amount' => (int) $validated['amount'],
            'reference' => isset($validated['reference']) ? (string) $validated['reference'] : null,
            'description' => isset($validated['description']) ? (string) $validated['description'] : null,
        ];
    }
}
