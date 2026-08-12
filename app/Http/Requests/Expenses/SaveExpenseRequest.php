<?php

namespace App\Http\Requests\Expenses;

use App\Enums\ExpenseCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveExpenseRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            'category' => ['required', Rule::enum(ExpenseCategory::class)],
            'description' => ['required', 'string', 'max:255'],
            'spent_on' => ['required', 'date'],

            // Whole amounts, and at least 1 — a zero expense is a no-op.
            'amount' => ['required', 'integer', 'min:1', 'max:999999999'],
            'bank_id' => [
                'nullable',
                'integer',
                Rule::exists('banks', 'id')
                    ->where('team_id', $teamId)
                    ->whereNull('deleted_at'),
            ],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.integer' => __('The amount must be a whole number, with no decimals.'),
            'amount.min' => __('Enter the amount spent.'),
        ];
    }
}
