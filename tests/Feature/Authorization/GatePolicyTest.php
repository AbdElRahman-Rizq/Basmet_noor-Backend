<?php

use App\Authorization\PermissionResolver;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\Support\PermissionViewPolicy;

uses(RefreshDatabase::class);

function d03Permission(string $name): Permission
{
    return Permission::create([
        'name' => $name,
        'module' => 'test',
    ]);
}

function d03Token(User $user): string
{
    return $user->createToken('d03-token')->plainTextToken;
}

function d03PermissionRows(): array
{
    return [
        'permissions' => Permission::count(),
        'role_permissions' => DB::table('role_permissions')->count(),
        'user_permissions' => DB::table('user_permissions')->count(),
    ];
}

beforeEach(function () {
    Gate::define('d03.http.view', function (User $user): bool {
        return app(PermissionResolver::class)->allows($user, 'children.view_all');
    });

    Route::middleware(['auth:api', 'can:d03.http.view'])->get('/api/v1/__d03/protected', function (Request $request) {
        return ApiResponse::success(['user_id' => $request->user()->id]);
    });

    Gate::policy(Permission::class, PermissionViewPolicy::class);
});

it('authorizes SUPER_ADMIN through the global bypass without touching permission rows', function () {
    d03Permission('children.view_all');

    $superAdmin = fixtureUser(fixtureRole(Role::SUPER_ADMIN, 100));
    $before = d03PermissionRows();

    Gate::define('d03.always-denied', fn (): bool => false);

    expect($superAdmin->can('d03.always-denied'))->toBeTrue()
        ->and(d03PermissionRows())->toBe($before);
});

it('authorizes a regular user with the permission through the base policy', function () {
    $permission = d03Permission('children.view_all');

    $role = fixtureRole('SPECIALIST', 40);
    $role->permissions()->attach($permission);

    $user = fixtureUser($role);

    expect($user->can('view', $permission))->toBeTrue();
});

it('denies a regular user without the permission', function () {
    $permission = d03Permission('children.view_all');

    $user = fixtureUser(fixtureRole('SPECIALIST', 40));

    expect($user->can('view', $permission))->toBeFalse();
});

it('lets an explicit deny override a role permission through the policy', function () {
    $permission = d03Permission('children.view_all');

    $role = fixtureRole('SPECIALIST', 40);
    $role->permissions()->attach($permission);

    $user = fixtureUser($role);

    DB::table('user_permissions')->insert([
        'user_id' => $user->id,
        'permission_id' => $permission->id,
        'is_granted' => false,
        'granted_by_user_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($user->can('view', $permission))->toBeFalse();
});

it('returns 200 for a regular user who holds the permission', function () {
    $permission = d03Permission('children.view_all');

    $role = fixtureRole('SPECIALIST', 40);
    $role->permissions()->attach($permission);

    $user = fixtureUser($role);

    $this->withToken(d03Token($user))
        ->getJson('/api/v1/__d03/protected')
        ->assertOk()
        ->assertExactJson([
            'success' => true,
            'message' => 'Operation completed successfully',
            'data' => ['user_id' => $user->id],
            'meta' => null,
        ]);
});

it('returns 403 with the existing envelope when a regular user lacks the permission', function () {
    d03Permission('children.view_all');

    $user = fixtureUser(fixtureRole('SPECIALIST', 40));

    $this->withToken(d03Token($user))
        ->getJson('/api/v1/__d03/protected')
        ->assertForbidden()
        ->assertExactJson([
            'success' => false,
            'message' => 'This action is unauthorized.',
            'error' => [
                'code' => 'FORBIDDEN',
                'details' => [],
            ],
        ]);
});

it('keeps unauthenticated requests at 401 with the existing envelope', function () {
    $this->getJson('/api/v1/__d03/protected')
        ->assertUnauthorized()
        ->assertExactJson([
            'success' => false,
            'message' => 'Authentication required.',
            'error' => [
                'code' => 'UNAUTHENTICATED',
                'details' => [],
            ],
        ]);
});

it('does not authorize an inactive user even with a valid token', function () {
    $permission = d03Permission('children.view_all');

    $role = fixtureRole('SPECIALIST', 40);
    $role->permissions()->attach($permission);

    $user = fixtureUser($role, ['is_active' => false]);

    $this->withToken(d03Token($user))
        ->getJson('/api/v1/__d03/protected')
        ->assertForbidden();
});

it('lets SUPER_ADMIN through a protected route through the bypass', function () {
    d03Permission('children.view_all');

    $superAdmin = fixtureUser(fixtureRole(Role::SUPER_ADMIN, 100));

    $this->withToken(d03Token($superAdmin))
        ->getJson('/api/v1/__d03/protected')
        ->assertOk()
        ->assertJsonPath('data.user_id', $superAdmin->id);
});
