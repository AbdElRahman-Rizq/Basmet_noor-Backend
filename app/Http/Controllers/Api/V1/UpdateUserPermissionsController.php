<?php

namespace App\Http\Controllers\Api\V1;

use App\Authorization\PermissionResolver;
use App\Authorization\UpdateUserPermissionOverridesAction;
use App\Http\Requests\Api\V1\UpdateUserPermissionsRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

final class UpdateUserPermissionsController
{
    public function __invoke(
        UpdateUserPermissionsRequest $request,
        User $user,
        UpdateUserPermissionOverridesAction $updateOverrides,
        PermissionResolver $permissions,
    ): JsonResponse {
        // Defence in depth: FormRequest::authorize() already enforces this
        // because SUPER_ADMIN bypasses the policy through Gate::before.
        if ($request->user()->is($user) || $user->role?->name !== Role::CO_ADMIN) {
            throw new AuthorizationException;
        }

        $validated = $request->validated();

        $overrides = $updateOverrides->execute(
            $request->user(),
            $user,
            $validated['grants'] ?? [],
            $validated['denies'] ?? [],
            $request,
        );

        $user->refresh()->loadMissing('role');

        return ApiResponse::success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'is_active' => $user->is_active,
            'role' => $user->role === null ? null : [
                'id' => $user->role->id,
                'name' => $user->role->name,
            ],
            'overrides' => $overrides,
            'effective_permissions' => $permissions->permissionsFor($user),
        ], 'Permission overrides updated successfully');
    }
}
