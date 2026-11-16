<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    /**
     * Default role permissions from DATABASE_DESIGN.md §5.4.
     * Pending-dependent permissions (consents.record, cases.assign_specialist,
     * admins.manage, permissions.manage) are intentionally NOT granted by default.
     */
    public const DEFAULTS = [
        'CO_ADMIN' => [
            'children.view_all', 'children.view_own', 'children.create', 'children.update',
            'parents.view', 'parents.manage',
            'cases.view_all', 'cases.create', 'cases.update',
            'assessments.view_all', 'assessments.create', 'assessments.update',
            'assessments.manage_questions', 'assessments.manage_scales', 'assessments.manage_reinforcers',
            'consents.view',
            'plans.view', 'plans.create', 'plans.update', 'plans.assign_specialist',
            'goals.create', 'goals.update', 'goals.record_progress',
            'reports.view_all',
            'homework.view',
            'appointments.view', 'appointments.create', 'appointments.update',
            'notifications.view',
            'specialists.view', 'specialists.manage',
        ],
        'SPECIALIST' => [
            'cases.view_assigned',
            'assessments.view_assigned',
            'plans.view',
            'goals.record_progress',
            'reports.view_assigned', 'reports.create', 'reports.download',
            'homework.view', 'homework.create', 'homework.update',
            'appointments.view',
            'notifications.view',
        ],
        'PARENT' => [
            'children.view_own',
            'assessments.view_own',
            'reports.download',
            'homework.view', 'homework.complete', 'homework.comment',
            'notifications.view',
        ],
        'RECEPTION' => [],
    ];

    public function run(): void
    {
        $roles = Role::pluck('id', 'name');
        $permissions = Permission::pluck('id', 'name');

        $rows = [];

        foreach ($permissions as $name => $permissionId) {
            $rows[] = [$roles['SUPER_ADMIN'], $permissionId];
        }

        foreach (self::DEFAULTS as $roleName => $permissionNames) {
            foreach ($permissionNames as $permissionName) {
                $rows[] = [$roles[$roleName], $permissions[$permissionName]];
            }
        }

        $rows = collect($rows)
            ->map(fn (array $row) => ['role_id' => $row[0], 'permission_id' => $row[1]])
            ->unique(fn (array $row) => $row['role_id'].'|'.$row['permission_id'])
            ->values()
            ->all();

        DB::table('role_permissions')->insertOrIgnore($rows);
    }
}
