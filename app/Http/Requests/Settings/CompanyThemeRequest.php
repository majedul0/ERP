<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompanyThemeRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /*
         * Red, green and blue as three numbers, which is how the setting is
         * presented and how somebody matching a brand guide has the colour
         * written down. They are encoded to `#rrggbb` by App\Support\BrandColor
         * on the way to the database — one colour, one column.
         *
         * `required_unless` rather than `required`: clearing the setting is the
         * same request with `reset`, and asking for a colour to be sent along
         * with the instruction to forget it would be theatre.
         */
        return [
            'reset' => ['sometimes', 'boolean'],
            'red' => ['required_unless:reset,true', 'integer', 'between:0,255'],
            'green' => ['required_unless:reset,true', 'integer', 'between:0,255'],
            'blue' => ['required_unless:reset,true', 'integer', 'between:0,255'],
        ];
    }

    /**
     * Whether this request clears the company's colour.
     */
    public function clearsTheme(): bool
    {
        return $this->boolean('reset');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'red.between' => __('Each colour value must be between 0 and 255.'),
            'green.between' => __('Each colour value must be between 0 and 255.'),
            'blue.between' => __('Each colour value must be between 0 and 255.'),
        ];
    }
}
