<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsUserStatus;
use App\Models\User;
use App\Support\ApiResponse;
use App\Users\ChangeUserStatusAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReactivateUserController
{
    use FormatsUserStatus;

    public function __invoke(
        Request $request,
        User $user,
        ChangeUserStatusAction $changeStatus,
    ): JsonResponse {
        // Gate::before lets SUPER_ADMIN through every policy, so the
        // self-modification ban (I-2/I-9) is enforced explicitly here.
        if ($request->user()->is($user)) {
            throw new AuthorizationException;
        }

        $user = $changeStatus->execute($request->user(), $user, true, $request);

        return ApiResponse::success(
            $this->userStatusPayload($user),
            'User reactivated successfully',
        );
    }
}
