<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class CreateCoAdminRequest extends FormRequest
{
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
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'string', 'email', 'max:190', 'unique:users,email', 'required_without:mobile'],
            'mobile' => ['nullable', 'string', 'max:20', 'unique:users,mobile', 'required_without:email'],
            'password' => ['required', 'string', 'max:255', Password::min(8)],

            // Server-controlled fields: never accepted from the client.
            'role_id' => ['prohibited'],
            'is_active' => ['prohibited'],
            'created_by_user_id' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }
}
