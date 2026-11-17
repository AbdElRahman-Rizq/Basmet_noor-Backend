<?php

namespace App\Http\Controllers\Api\V1;

use App\Authorization\PermissionResolver;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MeController
{
    public function __invoke(Request $request, PermissionResolver $permissions): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('role');

        // Explicit allow-list: never serialize the model directly.
        return ApiResponse::success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'is_active' => $user->is_active,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'role' => $user->role === null ? null : [
                'id' => $user->role->id,
                'name' => $user->role->name,
            ],
            'permissions' => $permissions->permissionsFor($user),
        ], 'Current user loaded');
    }
}
