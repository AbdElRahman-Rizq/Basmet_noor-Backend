<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('basma.super_admin.email');
        $password = config('basma.super_admin.password');

        if (blank($email) || blank($password)) {
            $this->command?->warn(
                'SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD are not configured; skipping initial SUPER_ADMIN seeding.'
            );

            return;
        }

        $roleId = Role::where('name', 'SUPER_ADMIN')->value('id');

        if ($roleId === null) {
            $this->command?->warn('SUPER_ADMIN role is missing; run RoleSeeder first. Seeding skipped.');

            return;
        }

        $existing = User::withTrashed()->where('email', $email)->first();

        if ($existing !== null) {
            // Never rewrite the password on re-seed; keep the account idempotent.
            $existing->forceFill([
                'role_id' => $roleId,
                'name' => $existing->name ?: config('basma.super_admin.name'),
                'mobile' => $existing->mobile ?: config('basma.super_admin.mobile'),
                'is_active' => true,
            ])->save();

            if ($existing->trashed()) {
                $existing->restore();
            }

            return;
        }

        User::create([
            'role_id' => $roleId,
            'name' => config('basma.super_admin.name'),
            'email' => $email,
            'mobile' => config('basma.super_admin.mobile'),
            'password' => $password,
            'is_active' => true,
        ]);
    }
}
