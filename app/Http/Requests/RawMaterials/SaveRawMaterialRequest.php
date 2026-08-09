<?php

namespace App\Http\Requests\RawMaterials;

use App\Enums\MaterialUnit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRawMaterialRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                // Unique within the company only, and `ignore` so an edit that
                // leaves the code alone does not collide with itself. Same
                // reasoning as SaveProductRequest.
                Rule::unique('raw_materials', 'code')
                    ->where('team_id', $teamId)
                    ->whereNull('deleted_at')
                    ->ignore($this->route('material')),
            ],
            'unit' => ['required', Rule::enum(MaterialUnit::class)],

            // `integer`, not `numeric`: quantities and costs are whole numbers
            // throughout this application, and a typed `2.5` is a mistake to
            // correct rather than something to round silently.
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:100000000'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:100000000'],
            'unit_cost' => ['required', 'integer', 'min:0', 'max:99999999'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_cost.integer' => __('Costs must be whole amounts, with no decimals.'),
            'stock_quantity.integer' => __('Quantities must be whole numbers. Use a smaller unit if you need finer amounts.'),
            'reorder_level.integer' => __('Quantities must be whole numbers. Use a smaller unit if you need finer amounts.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'material code',
            'unit_cost' => 'unit cost',
            'stock_quantity' => 'stock amount',
            'reorder_level' => 'reorder level',
        ];
    }
}
