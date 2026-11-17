<?php

namespace Tests\Support;

use App\Models\User;
use App\Policies\BasePolicy;

/**
 * Minimal policy used to prove the reusable BasePolicy → PermissionResolver path.
 */
class PermissionViewPolicy extends BasePolicy
{
    public function view(User $user): bool
    {
        return $this->allows($user, 'children.view_all');
    }
}
