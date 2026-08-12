<?php

namespace App\Http\Requests\Teams;

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamMemberRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(array_column(TeamRole::assignable(), 'value'))],

            /*
             * Omit `permissions` to follow the role's defaults; send a list —
             * including an empty one — to tailor this member specifically.
             *
             * The two are genuinely different: absent means "keep inheriting",
             * empty means "this person may do nothing". See the migration that
             * added the column.
             */
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => [Rule::enum(TeamPermission::class)],
        ];
    }
}
