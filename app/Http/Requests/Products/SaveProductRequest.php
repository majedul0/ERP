<?php

namespace App\Http\Requests\Products;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required',
                'string',
                'max:64',
                // Unique within the company only — two companies may both
                // sell something they each call "OFC-15".
                Rule::unique('products', 'sku')
                    ->where('team_id', $teamId)
                    ->whereNull('deleted_at'),
            ],
            'carton_size' => ['required', 'integer', 'min:1', 'max:100000'],
            'distributor_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'trade_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'mrp' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:100000000'],
            'photo' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.(int) config('company.storage.product_photos.max_kilobytes'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sku' => 'SKU',
            'mrp' => 'MRP',
            'carton_size' => 'carton size',
            'distributor_price' => 'distributor price',
            'trade_price' => 'trade price',
            'stock_quantity' => 'stock amount',
        ];
    }
}
