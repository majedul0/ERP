<?php

namespace App\Http\Requests\RawMaterials;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaterialPurchaseRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            'supplier_name' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:64'],
            'purchased_at' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:255'],

            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.raw_material_id' => [
                'required',
                'integer',
                // Scoped to the company, so a guessed id from another tenant is
                // rejected at validation rather than relied on to fail later.
                Rule::exists('raw_materials', 'id')
                    ->where('team_id', $teamId)
                    ->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000000'],
            'items.*.unit_cost' => ['required', 'integer', 'min:0', 'max:99999999'],
        ];
    }

    /**
     * The validated request as the shape RecordMaterialPurchase expects.
     *
     * Validation guarantees these are present and whole, but hands them back
     * as `mixed`. Casting once here means the action never has to wonder
     * whether a quantity arrived as `"50"` or `50` — the same reasoning as
     * SaveInvoiceRequest::invoiceData().
     *
     * @return array{
     *     supplier_name: string,
     *     reference: string|null,
     *     purchased_at: string,
     *     note: string|null,
     *     items: list<array{raw_material_id: int, quantity: int, unit_cost: int}>
     * }
     */
    public function purchaseData(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        /** @var array<int, array<string, mixed>> $items */
        $items = $validated['items'];

        return [
            'supplier_name' => (string) $validated['supplier_name'],
            'reference' => isset($validated['reference']) ? (string) $validated['reference'] : null,
            'purchased_at' => (string) $validated['purchased_at'],
            'note' => isset($validated['note']) ? (string) $validated['note'] : null,
            'items' => array_values(array_map(fn (array $item) => [
                'raw_material_id' => (int) $item['raw_material_id'],
                'quantity' => (int) $item['quantity'],
                'unit_cost' => (int) $item['unit_cost'],
            ], $items)),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => __('Add at least one material.'),
            'items.min' => __('Add at least one material.'),
            'items.*.quantity.integer' => __('Quantities must be whole numbers. Use a smaller unit if you need finer amounts.'),
            'items.*.unit_cost.integer' => __('Costs must be whole amounts, with no decimals.'),
            'items.*.raw_material_id.exists' => __('One of the selected materials is no longer available.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'supplier_name' => 'supplier',
            'purchased_at' => 'purchase date',
            'reference' => 'bill number',
        ];
    }
}
