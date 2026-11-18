<?php

use App\Authorization\UpdateUserPermissionOverridesAction;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| D1.5 — security invariants I-1…I-11 gap tests
|--------------------------------------------------------------------------
|
| Most invariants are already covered by the D1.1-D1.4 suites. This file only
| fills the documented gaps:
|   - I-8/I-10/I-11: a full staff-endpoint denial sweep for PARENT and RECEPTION.
|   - I-1: an individual `permissions.manage` grant never unlocks overrides.
|   - I-4/I-6/I-9: the write boundary repeats the target and delegation rules.
|   - Coarse account scope: soft-deleted actors and targets stay unreachable.
|
*/

function d15Token(User $user, string $name = 'd15-token'): string
{
    return $user->createToken($name)->plainTextToken;
}

function d15Permission(string $name): Permission
{
    return Permission::firstOrCreate(
        ['name' => $name],
        ['module' => explode('.', $name)[0]],
    );
}

function d15RolePermission(Role $role, string $permissionName): void
{
    $role->permissions()->syncWithoutDetaching([d15Permission($permissionName)->id]);
}

function d15SuperAdmin(array $attributes = []): User
{
    return fixtureUser(fixtureRole(Role::SUPER_ADMIN, 100), array_merge([
        'name' => 'D15 Super Admin',
        'email' => 'd15.super@example.test',
        'mobile' => '+966500000300',
    ], $attributes));
}

function d15CoAdmin(array $attributes = []): User
{
    return fixtureUser(fixtureRole(Role::CO_ADMIN, 80), array_merge([
        'name' => 'D15 Co Admin',
        'email' => 'd15.coadmin@example.test',
        'mobile' => '+966500000301',
    ], $attributes));
}

function d15Target(string $roleName, int $level, array $attributes = []): User
{
    return fixtureUser(fixtureRole($roleName, $level), array_merge([
        'name' => "D15 {$roleName} Target",
        'email' => mb_strtolower($roleName).'.target@example.test',
        'mobile' => '+966500000302',
    ], $attributes));
}

it('denies parent and reception accounts every staff management endpoint', function (string $roleName, int $level) {
    $role = fixtureRole($roleName, $level);

    // Even a maximally over-granted non-staff actor must not cross the
    // explicit role gates (I-8, I-10, I-11).
    foreach ([
        'admins.manage',
        'permissions.manage',
        'specialists.manage',
        'users.create',
        'users.update',
        'users.deactivate',
    ] as $permissionName) {
        d15RolePermission($role, $permissionName);
    }

    $actor = fixtureUser($role, [
        'name' => "D15 {$roleName} Actor",
        'email' => mb_strtolower($roleName).'.actor@example.test',
        'mobile' => '+966500000303',
    ]);
    $token = d15Token($actor);

    $specialist = d15Target(Role::SPECIALIST, 40);
    $coAdmin = d15CoAdmin();

    $requests = [
        ['POST', '/api/v1/admins', []],
        ['POST', '/api/v1/specialists', []],
        ['PATCH', "/api/v1/specialists/{$specialist->id}", ['name' => 'Hijacked']],
        ['POST', "/api/v1/users/{$specialist->id}/deactivate", []],
        ['POST', "/api/v1/users/{$specialist->id}/reactivate", []],
        ['PUT', "/api/v1/users/{$coAdmin->id}/permissions", ['grants' => ['audit_logs.view']]],
    ];

    foreach ($requests as [$method, $url, $payload]) {
        $this->withToken($token)
            ->json($method, $url, $payload)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        Auth::forgetGuards();
    }

    expect($specialist->fresh()->is_active)->toBeTrue()
        ->and($coAdmin->fresh()->is_active)->toBeTrue()
        ->and(DB::table('audit_logs')->count())->toBe(0)
        ->and(DB::table('user_permissions')->where('user_id', $coAdmin->id)->count())->toBe(0);
})->with([
    'PARENT' => ['PARENT', 20],
    'RECEPTION' => ['RECEPTION', 60],
]);

it('keeps permission overrides locked even when permissions.manage is granted individually', function () {
    $actor = d15CoAdmin([
        'name' => 'D15 Elevated Co Admin',
        'email' => 'd15.elevated@example.test',
        'mobile' => '+966500000304',
    ]);

    $permission = d15Permission('permissions.manage');

    DB::table('user_permissions')->insert([
        'user_id' => $actor->id,
        'permission_id' => $permission->id,
        'is_granted' => true,
        'granted_by_user_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $target = d15CoAdmin([
        'name' => 'D15 Target Co Admin',
        'email' => 'd15.target.coadmin@example.test',
        'mobile' => '+966500000305',
    ]);

    $this->withToken(d15Token($actor))
        ->putJson("/api/v1/users/{$target->id}/permissions", ['grants' => ['audit_logs.view']])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    expect(DB::table('user_permissions')->where('user_id', $target->id)->count())->toBe(0)
        ->and(DB::table('audit_logs')->count())->toBe(0);
});

it('refuses to delegate a permission the actor does not hold', function () {
    $actor = d15CoAdmin();
    $target = d15CoAdmin([
        'name' => 'D15 Delegation Target',
        'email' => 'd15.delegation.target@example.test',
        'mobile' => '+966500000306',
    ]);

    d15Permission('reports.view_all');

    $request = Request::create("/api/v1/users/{$target->id}/permissions", 'PUT');

    expect(fn () => app(UpdateUserPermissionOverridesAction::class)->execute(
        $actor,
        $target,
        ['reports.view_all'],
        [],
        $request,
    ))->toThrow(AuthorizationException::class);

    expect(DB::table('user_permissions')->where('user_id', $target->id)->count())->toBe(0)
        ->and(DB::table('audit_logs')->count())->toBe(0);
});

it('refuses to write overrides for a super admin or for the actor themselves', function (string $case) {
    $actor = d15SuperAdmin();
    $target = $case === 'own account'
        ? $actor
        : d15SuperAdmin([
            'name' => 'D15 Other Super Admin',
            'email' => 'd15.other.super@example.test',
            'mobile' => '+966500000307',
        ]);

    d15Permission('audit_logs.view');

    $request = Request::create("/api/v1/users/{$target->id}/permissions", 'PUT');

    expect(fn () => app(UpdateUserPermissionOverridesAction::class)->execute(
        $actor,
        $target,
        ['audit_logs.view'],
        [],
        $request,
    ))->toThrow(AuthorizationException::class);

    expect(DB::table('user_permissions')->count())->toBe(0)
        ->and(DB::table('audit_logs')->count())->toBe(0);
})->with(['own account', 'other super admin']);

it('ignores role and status fields on the permission endpoint', function () {
    $actor = d15SuperAdmin();
    $target = d15CoAdmin();
    $targetRoleId = $target->role_id;
    $superAdminRole = fixtureRole(Role::SUPER_ADMIN, 100);

    $this->withToken(d15Token($actor))
        ->putJson("/api/v1/users/{$target->id}/permissions", [
            'grants' => [],
            'denies' => [],
            'role_id' => $superAdminRole->id,
            'is_active' => false,
            'permissions' => ['permissions.manage'],
            'password' => 'NewPassword123',
        ])
        ->assertOk();

    $target->refresh();

    expect($target->role_id)->toBe($targetRoleId)
        ->and($target->is_active)->toBeTrue()
        ->and($target->permissions()->count())->toBe(0)
        ->and(DB::table('audit_logs')->count())->toBe(0);
});

it('rejects a token whose user was soft deleted', function () {
    $user = fixtureUser(fixtureRole(Role::SPECIALIST, 40));
    $token = d15Token($user);

    $user->delete();
    Auth::forgetGuards();

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
});

it('does not expose a soft deleted co admin as a permission target', function () {
    $actor = d15SuperAdmin();
    $target = d15CoAdmin();

    $target->delete();

    $this->withToken(d15Token($actor))
        ->putJson("/api/v1/users/{$target->id}/permissions", ['grants' => ['audit_logs.view']])
        ->assertNotFound();

    expect(DB::table('user_permissions')->count())->toBe(0)
        ->and(DB::table('audit_logs')->count())->toBe(0);
});
