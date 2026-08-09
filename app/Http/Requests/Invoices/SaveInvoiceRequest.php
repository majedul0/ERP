<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateInvoiceRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            'sold_at' => ['required', 'date'],

            // Scoped to the company: an id from another tenant must not
            // resolve, or an invoice could be written against a stranger.
            'distributor_id' => [
                'required',
                'integer',
                Rule::exists('distributors', 'id')
                    ->where('team_id', $teamId)
                    ->whereNull('deleted_at'),
            ],

            'comment' => ['nullable', 'string', 'max:2000'],
            'scheme_description' => ['nullable', 'string', 'max:255'],
            // Whole amounts throughout — see App\Support\Money.
            'scheme_amount' => ['nullable', 'integer', 'min:0', 'max:99999999'],

            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where('team_id', $teamId)
                    ->whereNull('deleted_at'),
            ],
            'items.*.carton_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'items.*.total_quantity' => ['required', 'integer', 'min:1', 'max:10000000'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'items.*.discount' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'items.*.remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The validated request as the shape CreateInvoice expects.
     *
     * Validation guarantees these values are present and numeric, but returns
     * them as `mixed`. Casting once here means the action never has to wonder
     * whether a quantity arrived as `"12"` or `12`.
     *
     * @return array{
     *     distributor_id: int,
     *     sold_at: string,
     *     comment: string|null,
     *     scheme_description: string|null,
     *     scheme_amount: int,
     *     items: list<array{product_id: int, carton_quantity: int, total_quantity: int, unit_price: int|null, discount: int, remarks: string|null}>
     * }
     */
    public function invoiceData(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        /** @var array<int, array<string, mixed>> $items */
        $items = $validated['items'];

        return [
            'distributor_id' => (int) $validated['distributor_id'],
            'sold_at' => (string) $validated['sold_at'],
            'comment' => isset($validated['comment']) ? (string) $validated['comment'] : null,
            'scheme_description' => isset($validated['scheme_description'])
                ? (string) $validated['scheme_description']
                : null,
            'scheme_amount' => (int) ($validated['scheme_amount'] ?? 0),
            'items' => array_values(array_map(fn (array $item): array => [
                'product_id' => (int) $item['product_id'],
                'carton_quantity' => (int) ($item['carton_quantity'] ?? 0),
                'total_quantity' => (int) $item['total_quantity'],
                'unit_price' => isset($item['unit_price']) ? (int) $item['unit_price'] : null,
                'discount' => (int) ($item['discount'] ?? 0),
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
            'items.required' => __('Add at least one product to the invoice.'),
            'items.min' => __('Add at least one product to the invoice.'),
            'items.*.product_id.required' => __('Choose a product for every row.'),
            'items.*.total_quantity.min' => __('Quantity must be at least 1.'),
        ];
    }
}
