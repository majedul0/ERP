<?php

namespace App\Http\Requests\Products;

use App\Enums\StockMovementReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordStockRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /*
             * Which way the stock is going, sent as a word rather than as a
             * sign on the quantity. The two buttons are two different acts —
             * goods made, and goods gone — and a request that lost its minus
             * sign in transit would otherwise quietly do the opposite of what
             * was clicked. The action receives the sign; nothing else applies
             * one.
             */
            'direction' => ['required', 'string', Rule::in(['add', 'reduce'])],

            // Always positive. See above.
            'quantity' => ['required', 'integer', 'min:1', 'max:100000000'],

            'occurred_on' => ['required', 'date_format:Y-m-d'],
            'reason' => [
                'required',
                Rule::enum(StockMovementReason::class)->only(StockMovementReason::selectable()),
            ],
            'remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'occurred_on' => 'date',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.integer' => __('Enter a whole quantity.'),
            'quantity.min' => __('Enter a quantity of at least 1.'),
        ];
    }
}
