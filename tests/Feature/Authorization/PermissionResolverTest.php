<?php

use App\Authorization\PermissionResolver;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function d02Permission(string $name): Permission
{
    return Permission::create([
        'name' => $name,
        'module' => 'test',
    ]);
}

function d02Override(User $user, Permission $permission, bool $isGranted): void
{
    DB::table('user_permissions')->updateOrInsert(
        [
            'user_id' => $user->id,
            'permission_id' => $permission->id,
        ],
        [
            'is_granted' => $isGranted,
            'granted_by_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );
}

function d02Resolve(User $user): array
{
    return app(PermissionResolver::class)->permissionsFor($user);
}

it('resolves only the role permissions when no user overrides exist', function () {
    $view = d02Permission('children.view_all');
    $create = d02Permission('children.create');
    d02Permission('reports.view_all');

    $role = fixtureRole('SPECIALIST', 40);
    $role->permissions()->attach([$view->id, $create->id]);

    $user = fixtureUser($role);

    expect(d02Resolve($user))->toBe(['children.create', 'children.view_all']);
});

it('adds an explicitly granted permission to the role set', function () {
    $rolePermission = d02Permission('children.view_all');
    $granted = d02Permission('reports.view_all');

    $role = fixtureRole('SPECIALIST', 40);
    $role->permissions()->attach($rolePermission);

    $user = fixtureUser($role);
    d02Override($user, $granted, true);

    expect(d02Resolve($user))->toBe(['children.view_all', 'reports.view_all'])
        ->and(app(PermissionResolver::class)->allows($user, 'reports.view_all'))->toBeTrue();
});

it('lets a user deny override a role permission', function () {
    $view = d02Permission('children.view_all');
    $create = d02Permission('children.create');

    $role = fixtureRole('SPECIALIST', 40);
    $role->permissions()->attach([$view->id, $create->id]);

    $user = fixtureUser($role);
    d02Override($user, $view, false);

    expect(d02Resolve($user))->toBe(['children.create'])
        ->and(app(PermissionResolver::class)->allows($user, 'children.view_all'))->toBeFalse();
});

it('lets a user deny override an explicit grant', function () {
    $permission = d02Permission('reports.view_all');
    $user = fixtureUser(fixtureRole('SPECIALIST', 40));

    d02Override($user, $permission, true);
    expect(d02Resolve($user))->toBe(['reports.view_all']);

    d02Override($user, $permission, false);

    expect(d02Resolve($user))->toBe([])
        ->and(app(PermissionResolver::class)->allows($user, 'reports.view_all'))->toBeFalse();
});

it('resolves every permission for SUPER_ADMIN without touching permission rows', function () {
    d02Permission('children.view_all');
    d02Permission('reports.view_all');

    $role = fixtureRole(Role::SUPER_ADMIN, 100);
    $user = fixtureUser($role);

    expect(d02Resolve($user))->toBe(['children.view_all', 'reports.view_all'])
        ->and(app(PermissionResolver::class)->allows($user, 'children.view_all'))->toBeTrue()
        ->and(DB::table('role_permissions')->where('role_id', $role->id)->count())->toBe(0)
        ->and(DB::table('user_permissions')->where('user_id', $user->id)->count())->toBe(0);
});

it('denies users without a valid role', function () {
    $permission = d02Permission('children.view_all');

    $role = fixtureRole('SPECIALIST', 40);
    $role->permissions()->attach($permission);
    $user = fixtureUser($role);

    $user->setRelation('role', null);
    expect(d02Resolve($user))->toBe([])
        ->and(app(PermissionResolver::class)->allows($user, 'children.view_all'))->toBeFalse();

    $unassigned = User::factory()->make(['role_id' => null]);
    expect(d02Resolve($unassigned))->toBe([]);
});

it('denies an unknown permission even when the user has other permissions', function () {
    $permission = d02Permission('children.view_all');

    $role = fixtureRole('SPECIALIST', 40);
    $role->permissions()->attach($permission);
    $user = fixtureUser($role);

    $resolver = app(PermissionResolver::class);

    expect($resolver->allows($user, 'does.not.exist'))->toBeFalse()
        ->and($resolver->allows($user, 'children.create'))->toBeFalse()
        ->and(d02Resolve($user))->toBe(['children.view_all']);
});

it('denies inactive, deleted, and inactive SUPER_ADMIN users', function () {
    $permission = d02Permission('children.view_all');

    $role = fixtureRole('SPECIALIST', 40);
    $role->permissions()->attach($permission);

    $inactive = fixtureUser($role, ['is_active' => false]);
    expect(d02Resolve($inactive))->toBe([])
        ->and(app(PermissionResolver::class)->allows($inactive, 'children.view_all'))->toBeFalse();

    $deleted = fixtureUser($role);
    $deleted->delete();
    expect(d02Resolve($deleted))->toBe([])
        ->and(app(PermissionResolver::class)->allows($deleted, 'children.view_all'))->toBeFalse();

    $inactiveSuperAdmin = fixtureUser(
        fixtureRole(Role::SUPER_ADMIN, 100),
        ['is_active' => false],
    );

    expect(d02Resolve($inactiveSuperAdmin))->toBe([])
        ->and(app(PermissionResolver::class)->allows($inactiveSuperAdmin, 'children.view_all'))->toBeFalse();
});

it('resolves an empty set for a role without permissions', function () {
    $user = fixtureUser(fixtureRole('SPECIALIST', 40));

    expect(d02Resolve($user))->toBe([])
        ->and(app(PermissionResolver::class)->allows($user, 'children.view_all'))->toBeFalse();
});
