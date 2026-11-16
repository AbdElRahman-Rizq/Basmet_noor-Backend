<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            BranchSeeder::class,
            KnowledgeSourceSeeder::class,
            ConsentTypeSeeder::class,
            FunctionalScaleSeeder::class,
            ReinforcerCatalogSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}
