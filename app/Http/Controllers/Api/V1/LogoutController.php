<?php

namespace App\Http\Controllers\Api\V1;

use App\Audit\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

final class LogoutController
{
    public function __invoke(Request $request, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        // The token revocation and its audit row commit together: if the audit
        // write fails, the token is not silently revoked. No token value, token
        // id or request body is stored (D1.6).
        DB::transaction(function () use ($user, $token, $audit, $request): void {
            $revoked = false;

            // Revoke only the bearer token used for this request. A stateful
            // (cookie) session token is transient and has nothing to delete.
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
                $revoked = true;
            }

            $audit->log(
                actor: $user,
                action: 'logout',
                entityName: 'users',
                recordId: $user->id,
                newValues: ['token_revoked' => $revoked],
                request: $request,
            );
        });

        return ApiResponse::success(null, 'Logged out successfully');
    }
}
