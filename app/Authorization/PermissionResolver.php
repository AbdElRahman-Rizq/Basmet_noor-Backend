<?php

namespace App\Authorization;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the effective permissions of a user.
 *
 * effective = (role_permissions ∪ user grants) − user denies   (D-030)
 *
 * Explicit denies always win, inactive/soft-deleted users resolve to nothing,
 * and SUPER_ADMIN resolves to the full catalogue without extra database rows.
 */
final class PermissionResolver
{
    /**
     * @return list<string>
     */
    public function permissionsFor(User $user): array
    {
        if ($user->trashed() || ! $user->is_active || $user->role_id === null) {
            return [];
        }

        $role = $user->role;

        // A missing role relation means the user has no valid authorization scope.
        if ($role === null) {
            return [];
        }

        if ($role->name === Role::SUPER_ADMIN) {
            return Permission::query()->orderBy('name')->pluck('name')->all();
        }

        $rows = DB::table('permissions as p')
            ->leftJoin('role_permissions as rp', function ($join) use ($role): void {
                $join->on('rp.permission_id', '=', 'p.id')
                    ->where('rp.role_id', '=', $role->id);
            })
            ->leftJoin('user_permissions as up', function ($join) use ($user): void {
                $join->on('up.permission_id', '=', 'p.id')
                    ->where('up.user_id', '=', $user->id);
            })
            ->whereNotNull('rp.permission_id')
            ->orWhereNotNull('up.permission_id')
            ->orderBy('p.name')
            ->get(['p.name', 'rp.permission_id as role_permission_id', 'up.is_granted']);

        $permissions = [];

        foreach ($rows as $row) {
            $hasOverride = $row->is_granted !== null;

            // A denied override removes the permission even when the role grants it.
            if ($hasOverride && ! $this->isGranted($row->is_granted)) {
                continue;
            }

            if ($row->role_permission_id !== null || $hasOverride) {
                $permissions[] = $row->name;
            }
        }

        return $permissions;
    }

    public function allows(User $user, string $permission): bool
    {
        if ($user->trashed() || ! $user->is_active) {
            return false;
        }

        // Full override, independent of role_permissions/user_permissions rows.
        if ($user->isSuperAdmin()) {
            return true;
        }

        return in_array($permission, $this->permissionsFor($user), true);
    }

    /**
     * PostgreSQL returns booleans as native PHP booleans, but accepting the
     * driver-level string forms keeps the resolver safe if PDO fetch options
     * ever change.
     */
    private function isGranted(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 't';
    }
}
