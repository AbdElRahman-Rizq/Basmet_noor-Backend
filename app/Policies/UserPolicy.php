<?php

namespace App\Policies;

use App\Models\User;

final class UserPolicy extends BasePolicy
{
    /**
     * Only an active SUPER_ADMIN may create a CO_ADMIN (D1.1).
     *
     * This is deliberately role-based, not permission-based: CO_ADMIN must
     * still be blocked even if it is individually granted `users.create`.
     */
    public function createCoAdmin(User $actor): bool
    {
        return ! $actor->trashed()
            && $actor->is_active
            && $actor->isSuperAdmin();
    }
}
