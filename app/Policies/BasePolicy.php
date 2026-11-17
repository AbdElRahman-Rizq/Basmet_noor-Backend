<?php

namespace App\Policies;

use App\Authorization\PermissionResolver;
use App\Models\User;

/**
 * Shared base for policy classes.
 *
 * Policies express domain rules; permission checks stay centralized in the
 * resolver so controllers never compare role names directly (D-030).
 * SUPER_ADMIN bypass is handled once by the global Gate::before callback.
 */
abstract class BasePolicy
{
    public function __construct(protected PermissionResolver $permissions) {}

    protected function allows(User $user, string $permission): bool
    {
        return $this->permissions->allows($user, $permission);
    }
}
