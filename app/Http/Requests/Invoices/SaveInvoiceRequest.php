<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInvoiceRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            'sold_at' => ['required', 'date_format:Y-m-d'],

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

            /*
             * The opening balance shown on the form. Normally the figure the
             * account produces, but a user may type their own — an opening
             * balance for a distributor coming off paper, say. Negative is
             * allowed: it means the company holds money on their behalf.
             */
            'previous_dues' => ['nullable', 'integer', 'min:-999999999', 'max:999999999'],

            /*
             * The opt-in. `previous_dues` is honoured only when this is true.
             *
             * Intent has to be stated, not inferred. Deciding from "the number
             * differs from the account" meant any client holding a stale figure
             * — an old bundle, a form opened before a payment landed — silently
             * pinned an invoice's opening balance and made the ledger replay an
             * adjustment nobody asked for. A client that does not send this
             * cannot pin anything, whatever else it sends.
             */
            'previous_dues_override' => ['nullable', 'boolean'],

            // Print this invoice without the running account on it. Affects
            // the paper only — see the migration that added the column.
            'hide_previous_dues' => ['nullable', 'boolean'],

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
     *     previous_dues: int|null,
     *     hide_previous_dues: bool,
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
            // Null unless the request explicitly asked to override, so the
            // actions downstream see "no opening balance was chosen" for every
            // other case — including a stale client that sent a figure.
            'previous_dues' => $this->boolean('previous_dues_override')
                && isset($validated['previous_dues'])
                    ? (int) $validated['previous_dues']
                    : null,
            'hide_previous_dues' => $this->boolean('hide_previous_dues'),
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
