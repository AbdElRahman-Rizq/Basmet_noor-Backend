<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateUserPermissionsRequest extends FormRequest
{
    /**
     * SUPER_ADMIN bypasses policies through Gate::before, so the target-scope
     * rule (CO_ADMIN only) and the self-modification ban (I-2/I-9) are
     * enforced here before validation runs.
     */
    public function authorize(): bool
    {
        $actor = $this->user();
        $target = $this->route('user');

        return $actor instanceof User
            && $target instanceof User
            && ! $actor->is($target)
            && $target->role?->name === Role::CO_ADMIN;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'grants' => ['sometimes', 'array'],
            'grants.*' => ['string', 'distinct', 'exists:permissions,name'],
            'denies' => ['sometimes', 'array'],
            'denies.*' => ['string', 'distinct', 'exists:permissions,name'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $grants = $this->input('grants', []);
                $denies = $this->input('denies', []);

                if (! is_array($grants) || ! is_array($denies)) {
                    return;
                }

                if (array_intersect($grants, $denies) !== []) {
                    $validator->errors()->add(
                        'denies',
                        'A permission cannot be both granted and denied.',
                    );
                }
            },
        ];
    }
}
