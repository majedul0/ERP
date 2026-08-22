<?php

namespace App\Http\Requests\Employees;

use App\Enums\SalaryType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveEmployeeRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            'employee_code' => [
                'required',
                'string',
                'max:64',
                // Unique within the company only — two companies may both
                // number their first hire "EMP-001". `ignore` keeps an edit
                // that leaves the code alone from colliding with itself.
                Rule::unique('employees', 'employee_code')
                    ->where('team_id', $teamId)
                    ->whereNull('deleted_at')
                    ->ignore($this->route('employee')),
            ],

            'name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'nid' => ['nullable', 'string', 'max:64'],
            'designation' => ['nullable', 'string', 'max:120'],

            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')
                    ->where('team_id', $teamId)
                    ->whereNull('deleted_at'),
            ],

            'address' => ['nullable', 'string', 'max:255'],
            'thana' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'division' => ['nullable', 'string', 'max:120'],

            'salary_type' => ['required', Rule::enum(SalaryType::class)],

            'joined_on' => ['required', 'date_format:Y-m-d'],

            // Somebody cannot leave before they arrived, and a leaving date is
            // what stops payroll counting them — worth catching here rather
            // than as a strange payslip three weeks later.
            'left_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:joined_on'],

            'photo' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.(int) config('company.storage.employee_photos.max_kilobytes'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_code.unique' => __('Another employee already has that staff number.'),
            'left_on.after_or_equal' => __('The leaving date cannot be before the joining date.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'employee_code' => 'staff number',
            'nid' => 'NID',
            'department_id' => 'department',
            'salary_type' => 'salary type',
            'joined_on' => 'joining date',
            'left_on' => 'leaving date',
        ];
    }
}
