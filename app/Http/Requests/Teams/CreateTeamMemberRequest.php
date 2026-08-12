<?php

namespace App\Http\Requests\Teams;

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateTeamMemberRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            /*
             * Unique across the whole platform, not just this company: an
             * address identifies one person signing in, and a second account
             * on the same address could never be told apart at the login form.
             * Somebody who already has an account is added by invitation.
             */
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],

            'password' => ['required', 'string', Password::default()],
            'role' => ['required', Rule::in(array_column(TeamRole::assignable(), 'value'))],

            // Omit to follow the role's defaults; send a list to tailor them.
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => [Rule::enum(TeamPermission::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => __('Somebody already uses that email address. Invite them instead of creating a second account.'),
        ];
    }
}
