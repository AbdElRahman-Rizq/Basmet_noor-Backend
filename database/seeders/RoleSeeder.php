<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'SUPER_ADMIN', 'level' => 100, 'description' => 'System owner with full override.'],
            ['name' => 'CO_ADMIN', 'level' => 80, 'description' => 'Administrative user with explicit permissions.'],
            ['name' => 'RECEPTION', 'level' => 60, 'description' => 'Reception — inactive until P-02 is approved.'],
            ['name' => 'SPECIALIST', 'level' => 40, 'description' => 'Specialist assigned to cases.'],
            ['name' => 'PARENT', 'level' => 20, 'description' => 'Parent/guardian account.'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']],
                $role + ['is_system_role' => true],
            );
        }
    }
}
