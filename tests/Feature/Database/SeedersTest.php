<?php

use App\Authorization\PermissionResolver;
use App\Models\Branch;
use App\Models\ConsentType;
use App\Models\FunctionalScale;
use App\Models\KnowledgeSource;
use App\Models\Permission;
use App\Models\ReinforcerCatalog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('seeds every approved reference set', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Role::count())->toBe(5)
        ->and(Permission::count())->toBe(55)
        ->and(Branch::count())->toBe(2)
        ->and(KnowledgeSource::count())->toBe(4)
        ->and(ConsentType::count())->toBe(3)
        ->and(FunctionalScale::count())->toBe(10)
        ->and(ReinforcerCatalog::count())->toBe(8);

    expect(ConsentType::where('is_required', true)->where('is_active', true)->count())->toBe(3);

    foreach (['material', 'social', 'activity', 'food'] as $category) {
        expect(ReinforcerCatalog::where('category', $category)->count())->toBe(2);
    }

    expect(Branch::pluck('name')->all())->toBe([
        'Main Branch (Placeholder)',
        'Second Branch (Placeholder)',
    ]);
});

it('is idempotent when the database seeder runs twice', function () {
    $this->seed(DatabaseSeeder::class);

    $counts = [
        'roles' => Role::count(),
        'permissions' => Permission::count(),
        'role_permissions' => DB::table('role_permissions')->count(),
        'branches' => Branch::count(),
        'knowledge_sources' => KnowledgeSource::count(),
        'consent_types' => ConsentType::count(),
        'functional_scales' => FunctionalScale::count(),
        'reinforcer_catalog' => ReinforcerCatalog::count(),
        'users' => User::count(),
    ];

    $this->seed(DatabaseSeeder::class);

    expect([
        'roles' => Role::count(),
        'permissions' => Permission::count(),
        'role_permissions' => DB::table('role_permissions')->count(),
        'branches' => Branch::count(),
        'knowledge_sources' => KnowledgeSource::count(),
        'consent_types' => ConsentType::count(),
        'functional_scales' => FunctionalScale::count(),
        'reinforcer_catalog' => ReinforcerCatalog::count(),
        'users' => User::count(),
    ])->toBe($counts);
});

it('grants every permission to SUPER_ADMIN and keeps pending permissions ungranted', function () {
    $this->seed(DatabaseSeeder::class);

    $superAdmin = Role::where('name', 'SUPER_ADMIN')->firstOrFail();
    $granted = DB::table('role_permissions')->where('role_id', $superAdmin->id)->pluck('permission_id');

    expect($granted)->toHaveCount(Permission::count());

    foreach (['CO_ADMIN', 'RECEPTION', 'SPECIALIST', 'PARENT'] as $roleName) {
        $role = Role::where('name', $roleName)->firstOrFail();
        $names = Permission::whereIn('id', DB::table('role_permissions')->where('role_id', $role->id)->pluck('permission_id'))
            ->pluck('name');

        expect($names)->not->toContain('consents.record')
            ->and($names)->not->toContain('cases.assign_specialist')
            ->and($names)->not->toContain('admins.manage')
            ->and($names)->not->toContain('permissions.manage');
    }
});

it('leaves RECEPTION with no default permissions at all', function () {
    $this->seed(DatabaseSeeder::class);

    $reception = Role::where('name', 'RECEPTION')->firstOrFail();

    expect(DB::table('role_permissions')->where('role_id', $reception->id)->count())->toBe(0)
        ->and(app(PermissionResolver::class)->permissionsFor(fixtureUser($reception)))->toBe([]);
});

it('creates the initial SUPER_ADMIN from environment configuration only', function () {
    $this->seed(DatabaseSeeder::class);

    $user = User::where('email', config('basma.super_admin.email'))->firstOrFail();

    expect($user->role->name)->toBe('SUPER_ADMIN')
        ->and($user->is_active)->toBeTrue()
        ->and($user->password)->not->toBe(config('basma.super_admin.password'));

    $hash = $user->password;

    $this->seed(SuperAdminSeeder::class);

    expect(User::where('email', $user->email)->count())->toBe(1)
        ->and($user->fresh()->password)->toBe($hash);
});

it('skips the SUPER_ADMIN account when credentials are not configured', function () {
    $this->seed(DatabaseSeeder::class);

    $email = config('basma.super_admin.email');

    config([
        'basma.super_admin.email' => null,
        'basma.super_admin.password' => null,
    ]);

    User::where('email', $email)->forceDelete();

    $this->seed(SuperAdminSeeder::class);

    expect(User::where('email', $email)->exists())->toBeFalse();
});
