<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateSpecialistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only profile fields are editable. Role, status, creator, permissions,
     * and password changes are separate security operations and are rejected
     * rather than silently ignored.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $target = $this->target();
        $targetId = $target?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'email' => [
                'sometimes', 'nullable', 'string', 'email', 'max:190',
                Rule::unique('users', 'email')->ignore($targetId),
            ],
            'mobile' => [
                'sometimes', 'nullable', 'string', 'max:20',
                Rule::unique('users', 'mobile')->ignore($targetId),
            ],

            'role' => ['prohibited'],
            'role_name' => ['prohibited'],
            'role_id' => ['prohibited'],
            'is_active' => ['prohibited'],
            'created_by_user_id' => ['prohibited'],
            'permissions' => ['prohibited'],
            'permission_ids' => ['prohibited'],
            'password' => ['prohibited'],
        ];
    }

    /**
     * `users.email` and `users.mobile` are both nullable in the schema, but at
     * least one identifier must remain after applying the update.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $target = $this->target();

                $email = $this->has('email') ? $this->input('email') : $target?->email;
                $mobile = $this->has('mobile') ? $this->input('mobile') : $target?->mobile;

                if ($email === null && $mobile === null) {
                    $validator->errors()->add('email', 'At least one of email or mobile is required.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    private function target(): ?User
    {
        $target = $this->route('specialist');

        return $target instanceof User ? $target : null;
    }
}
