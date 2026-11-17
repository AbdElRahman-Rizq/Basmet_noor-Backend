<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\User;

/**
 * Shared allow-listed payload for account status responses.
 */
trait FormatsUserStatus
{
    /**
     * @return array<string, mixed>
     */
    private function userStatusPayload(User $user): array
    {
        $user->loadMissing('role');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'is_active' => $user->is_active,
            'role' => $user->role === null ? null : [
                'id' => $user->role->id,
                'name' => $user->role->name,
            ],
        ];
    }
}
