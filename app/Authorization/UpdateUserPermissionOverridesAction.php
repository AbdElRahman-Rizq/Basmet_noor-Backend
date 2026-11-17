<?php

namespace App\Authorization;

use App\Audit\AuditLogger;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Replaces the individual permission overrides of a CO_ADMIN account.
 *
 * The effective permission algorithm itself stays untouched in
 * PermissionResolver: role defaults ∪ grants − denies. This action only writes
 * `user_permissions` rows (D-005, D-030) and never touches `role_permissions`.
 */
final class UpdateUserPermissionOverridesAction
{
    /**
     * D-030 #1 / I-1: these permissions stay exclusive to SUPER_ADMIN and can
     * never be delegated through an individual override.
     */
    private const NON_DELEGABLE = ['admins.manage', 'permissions.manage'];

    public function __construct(
        private readonly PermissionResolver $permissions,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<string>  $grants
     * @param  list<string>  $denies
     * @return array{grants: list<string>, denies: list<string>}
     */
    public function execute(
        User $actor,
        User $target,
        array $grants,
        array $denies,
        Request $request,
    ): array {
        $grants = array_values(array_unique($grants));
        $denies = array_values(array_unique($denies));

        if (array_intersect($grants, $denies) !== []) {
            throw ValidationException::withMessages([
                'denies' => 'A permission cannot be both granted and denied.',
            ]);
        }

        $blocked = array_intersect($grants, self::NON_DELEGABLE);

        if ($blocked !== []) {
            throw ValidationException::withMessages([
                'grants' => 'The '.implode(', ', $blocked).' permission cannot be delegated.',
            ]);
        }

        // I-4: an actor can never delegate a permission they do not hold.
        foreach ($grants as $permissionName) {
            if (! $this->permissions->allows($actor, $permissionName)) {
                throw new AuthorizationException;
            }
        }

        return DB::transaction(function () use ($actor, $target, $grants, $denies, $request): array {
            $target = User::query()->lockForUpdate()->findOrFail($target->getKey());

            $catalogue = Permission::query()->pluck('name', 'id')->all();
            $permissionIds = Permission::query()
                ->whereIn('name', array_merge($grants, $denies))
                ->pluck('id', 'name');

            if ($permissionIds->count() !== count(array_merge($grants, $denies))) {
                throw ValidationException::withMessages([
                    'grants' => 'One or more permissions do not exist.',
                ]);
            }

            $desired = [];

            foreach ($grants as $permissionName) {
                $desired[$permissionIds[$permissionName]] = true;
            }

            foreach ($denies as $permissionName) {
                $desired[$permissionIds[$permissionName]] = false;
            }

            $existing = DB::table('user_permissions')
                ->where('user_id', $target->id)
                ->get()
                ->keyBy('permission_id');

            $now = now();

            // Clear overrides that are no longer present: the role default
            // applies again.
            foreach ($existing as $permissionId => $row) {
                if (array_key_exists($permissionId, $desired)) {
                    continue;
                }

                DB::table('user_permissions')
                    ->where('user_id', $target->id)
                    ->where('permission_id', $permissionId)
                    ->delete();

                $this->logChange(
                    $actor,
                    $target,
                    $catalogue[$permissionId],
                    (bool) $row->is_granted,
                    null,
                    $request,
                );
            }

            foreach ($desired as $permissionId => $isGranted) {
                $row = $existing->get($permissionId);
                $previous = $row === null ? null : (bool) $row->is_granted;

                if ($previous === $isGranted) {
                    continue;
                }

                if ($row === null) {
                    DB::table('user_permissions')->insert([
                        'user_id' => $target->id,
                        'permission_id' => $permissionId,
                        'is_granted' => $isGranted,
                        'granted_by_user_id' => $actor->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    DB::table('user_permissions')
                        ->where('user_id', $target->id)
                        ->where('permission_id', $permissionId)
                        ->update([
                            'is_granted' => $isGranted,
                            'granted_by_user_id' => $actor->id,
                            'updated_at' => $now,
                        ]);
                }

                $this->logChange(
                    $actor,
                    $target,
                    $catalogue[$permissionId],
                    $previous,
                    $isGranted,
                    $request,
                );
            }

            return $this->currentOverrides($target);
        });
    }

    private function logChange(
        User $actor,
        User $target,
        string $permissionName,
        ?bool $previous,
        ?bool $current,
        Request $request,
    ): void {
        $action = match ($current) {
            true => 'permission_granted',
            false => 'permission_denied',
            null => 'permission_override_removed',
        };

        $this->audit->log(
            actor: $actor,
            action: $action,
            entityName: 'user_permissions',
            recordId: $target->id,
            oldValues: ['permission' => $permissionName, 'is_granted' => $previous],
            newValues: ['permission' => $permissionName, 'is_granted' => $current],
            request: $request,
        );
    }

    /**
     * @return array{grants: list<string>, denies: list<string>}
     */
    private function currentOverrides(User $target): array
    {
        $rows = DB::table('user_permissions as up')
            ->join('permissions as p', 'p.id', '=', 'up.permission_id')
            ->where('up.user_id', $target->id)
            ->orderBy('p.name')
            ->get(['p.name', 'up.is_granted']);

        $grants = [];
        $denies = [];

        foreach ($rows as $row) {
            if ((bool) $row->is_granted) {
                $grants[] = $row->name;
            } else {
                $denies[] = $row->name;
            }
        }

        return ['grants' => $grants, 'denies' => $denies];
    }
}
