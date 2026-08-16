<?php

namespace App\Http\Requests\SalesReturns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSalesReturnRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            'returned_on' => ['required', 'date'],

            // Scoped to the company: an id from another tenant must not
            // resolve, or a credit could land on a stranger's account.
            'distributor_id' => [
                'required',
                'integer',
                Rule::exists('distributors', 'id')
                    ->where('team_id', $teamId)
                    ->whereNull('deleted_at'),
            ],

            'comment' => ['nullable', 'string', 'max:2000'],

            // Whether the goods go back on the shelf. Damaged stock is credited
            // but never becomes sellable again.
            'restock' => ['nullable', 'boolean'],

            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where('team_id', $teamId)
                    ->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000000'],
            // Whole amounts throughout — `integer`, not `numeric`, so a
            // fractional price is rejected rather than quietly rounded.
            'items.*.unit_price' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'items.*.remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The validated request as the shape SaveSalesReturn expects.
     *
     * @return array{
     *     distributor_id: int,
     *     returned_on: string,
     *     comment: string|null,
     *     restock: bool,
     *     items: list<array{product_id: int, quantity: int, unit_price: int|null, remarks: string|null}>
     * }
     */
    public function returnData(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        /** @var array<int, array<string, mixed>> $items */
        $items = $validated['items'];

        return [
            'distributor_id' => (int) $validated['distributor_id'],
            'returned_on' => (string) $validated['returned_on'],
            'comment' => isset($validated['comment']) ? (string) $validated['comment'] : null,
            // Defaults to restocking: a plain return is saleable goods coming
            // back, and damage is the case somebody has to state.
            'restock' => ! $this->has('restock') || $this->boolean('restock'),
            'items' => array_values(array_map(fn (array $item): array => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
                'unit_price' => isset($item['unit_price']) ? (int) $item['unit_price'] : null,
                'remarks' => isset($item['remarks']) ? (string) $item['remarks'] : null,
            ], $items)),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => __('Add at least one product to the return.'),
            'items.min' => __('Add at least one product to the return.'),
            'items.*.product_id.required' => __('Choose a product for every row.'),
            'items.*.quantity.min' => __('Quantity must be at least 1.'),
        ];
    }
}
