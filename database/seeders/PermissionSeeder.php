<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Permission catalogue from DATABASE_DESIGN.md §5.3.
     * `assessments.view_own` is required by the PARENT defaults in §5.4.
     */
    public const PERMISSIONS = [
        'users' => ['view', 'create', 'update', 'deactivate'],
        'admins' => ['manage'],
        'permissions' => ['manage'],
        'specialists' => ['view', 'manage'],
        'parents' => ['view', 'manage'],
        'branches' => ['view', 'manage'],
        'lookups' => ['view', 'manage'],
        'children' => ['view_all', 'view_own', 'create', 'update'],
        'cases' => ['view_all', 'view_assigned', 'create', 'update', 'assign_specialist'],
        'assessments' => ['view_all', 'view_assigned', 'view_own', 'create', 'update', 'manage_questions', 'manage_scales', 'manage_reinforcers'],
        'consents' => ['view', 'record'],
        'plans' => ['view', 'create', 'update', 'assign_specialist'],
        'goals' => ['create', 'update', 'record_progress'],
        'reports' => ['view_all', 'view_assigned', 'create', 'hide_from_parent', 'download'],
        'homework' => ['view', 'create', 'update', 'complete', 'comment'],
        'appointments' => ['view', 'create', 'update'],
        'notifications' => ['view'],
        'audit' => ['view'],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $module => $actions) {
            foreach ($actions as $action) {
                Permission::updateOrCreate(
                    ['name' => "{$module}.{$action}"],
                    ['module' => $module, 'description' => null],
                );
            }
        }
    }
}
