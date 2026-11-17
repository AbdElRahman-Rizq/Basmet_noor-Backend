<?php

namespace App\Policies;

use App\Models\Role;
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

    /**
     * CO_ADMIN creates SPECIALIST accounts through `specialists.manage`, not
     * `users.*` (DATABASE_DESIGN §5.2 I-3). The role check is explicit so a
     * grant on any other role never widens access.
     */
    public function createSpecialist(User $actor): bool
    {
        return $this->isActiveCoAdmin($actor)
            && $this->allows($actor, 'specialists.manage');
    }

    /**
     * CO_ADMIN may edit SPECIALIST accounts only (I-3, I-8).
     *
     * The explicit role check also stops role promotion through this endpoint:
     * `role_id` is never accepted by the request, and the target must already
     * be a SPECIALIST. SUPER_ADMIN bypasses Gate::before, so the controller
     * repeats the target-role check for that path.
     */
    public function updateSpecialist(User $actor, User $target): bool
    {
        return $this->isActiveCoAdmin($actor)
            && $this->allows($actor, 'specialists.manage')
            && ! $target->trashed()
            && $target->role?->name === Role::SPECIALIST;
    }

    /**
     * Deactivation matrix (D-055): SUPER_ADMIN may deactivate any user except
     * themselves; an active CO_ADMIN with `specialists.manage` may deactivate
     * SPECIALIST accounts only. No other role is authorized, and an individual
     * `users.deactivate` grant never widens this scope.
     */
    public function deactivateUser(User $actor, User $target): bool
    {
        return $this->canManageAccountStatus($actor, $target);
    }

    /**
     * Reactivation reuses the same ability as deactivation (D-055). There is
     * deliberately no `users.activate` permission.
     */
    public function reactivateUser(User $actor, User $target): bool
    {
        return $this->canManageAccountStatus($actor, $target);
    }

    private function canManageAccountStatus(User $actor, User $target): bool
    {
        if ($actor->trashed() || ! $actor->is_active) {
            return false;
        }

        // Self-modification is forbidden for every role (I-2/I-9), including
        // SUPER_ADMIN, whose Gate::before bypass skips this policy entirely.
        if ($actor->is($target)) {
            return false;
        }

        if ($actor->isSuperAdmin()) {
            return true;
        }

        return $this->isActiveCoAdmin($actor)
            && $this->allows($actor, 'specialists.manage')
            && ! $target->trashed()
            && $target->role?->name === Role::SPECIALIST;
    }

    private function isActiveCoAdmin(User $actor): bool
    {
        return ! $actor->trashed()
            && $actor->is_active
            && $actor->role?->name === Role::CO_ADMIN;
    }
}
