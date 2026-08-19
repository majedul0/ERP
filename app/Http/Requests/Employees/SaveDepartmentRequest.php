<?php

namespace App\Http\Requests\Employees;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDepartmentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                /*
                 * Unique among live departments only. The table carries no
                 * unique index on purpose: departments soft-delete, and a
                 * constraint would silently refuse to re-create the name of one
                 * that was removed last year.
                 */
                Rule::unique('departments', 'name')
                    ->where('team_id', $teamId)
                    ->whereNull('deleted_at')
                    ->ignore($this->route('department')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => __('That department already exists.'),
        ];
    }
}
