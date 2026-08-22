<?php

namespace App\Http\Requests\Expenses;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
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
            /*
             * Salary is closed to new expenses: wages are recorded in Payroll
             * and counted from `salary_payments`, so an expense under it would
             * be the same money twice.
             *
             * Asymmetric on purpose — an expense already recorded under Salary
             * can still be edited and saved, or its category would silently
             * change the first time somebody fixed a typo in the description.
             * Enforced here rather than by leaving it off the form, because a
             * hidden option is still a postable value.
             */
            'category' => [
                'required',
                Rule::enum(ExpenseCategory::class)->only($this->allowedCategories()),
            ],
            'description' => ['required', 'string', 'max:255'],
            'spent_on' => ['required', 'date_format:Y-m-d'],

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
     * The categories this particular request may set.
     *
     * @return array<int, ExpenseCategory>
     */
    private function allowedCategories(): array
    {
        $categories = array_values(array_filter(
            ExpenseCategory::cases(),
            fn (ExpenseCategory $category) => $category->selectable(),
        ));

        $expense = $this->route('expense');

        if ($expense instanceof Expense && ! $expense->category->selectable()) {
            $categories[] = $expense->category;
        }

        return $categories;
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
