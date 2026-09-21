<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in the controller via
     * Gate::authorize(), so every incoming request reaches validation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                // withoutTrashed(): a trashed user's email is reusable by
                // someone else (see StoreUserRequest) - without it, this
                // ignore() would still block a different user's email
                // change onto an address a soft-deleted row still holds.
                Rule::unique('users', 'email')->ignore($this->route('user'))->withoutTrashed(),
            ],
            'password' => ['nullable', 'confirmed', Password::default()],
            'is_active' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }
}
