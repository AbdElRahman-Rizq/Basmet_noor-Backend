<?php

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function d13Token(User $user, string $name = 'd13-token'): string
{
    return $user->createToken($name)->plainTextToken;
}

function d13RolePermission(Role $role, string $permissionName): void
{
    $permission = Permission::firstOrCreate(
        ['name' => $permissionName],
        ['module' => explode('.', $permissionName)[0]],
    );

    $role->permissions()->syncWithoutDetaching([$permission->id]);
}

function d13SuperAdmin(array $attributes = []): User
{
    return fixtureUser(fixtureRole(Role::SUPER_ADMIN, 100), array_merge([
        'name' => 'D13 Super Admin',
        'email' => 'd13.super@example.test',
        'mobile' => '+966500000100',
    ], $attributes));
}

function d13CoAdmin(bool $withSpecialistsManage = true, array $extraPermissions = []): User
{
    $role = fixtureRole(Role::CO_ADMIN, 80);

    if ($withSpecialistsManage) {
        d13RolePermission($role, 'specialists.manage');
    }

    foreach ($extraPermissions as $permissionName) {
        d13RolePermission($role, $permissionName);
    }

    return fixtureUser($role, [
        'name' => 'D13 Co Admin',
        'email' => 'd13.coadmin@example.test',
        'mobile' => '+966500000101',
    ]);
}

function d13Target(string $roleName, int $level, array $attributes = []): User
{
    return fixtureUser(fixtureRole($roleName, $level), array_merge([
        'name' => "D13 {$roleName} Target",
        'email' => mb_strtolower($roleName).'.target@example.test',
        'mobile' => '+966500000102',
    ], $attributes));
}

it('lets a super admin deactivate every applicable target role', function (string $roleName, int $level) {
    $actor = d13SuperAdmin();
    $target = d13Target($roleName, $level);

    $targetToken = d13Token($target, 'target-token');
    d13Token($target, 'target-second-token');
    $actorToken = d13Token($actor, 'actor-token');

    $targetPassword = $target->password;
    $targetRoleId = $target->role_id;

    $response = $this->withToken($actorToken)
        ->postJson("/api/v1/users/{$target->id}/deactivate");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'User deactivated successfully')
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.role.name', $roleName)
        ->assertJsonPath('meta', null);

    $target->refresh();

    expect($target->is_active)->toBeFalse()
        ->and($target->role_id)->toBe($targetRoleId)
        ->and($target->password)->toBe($targetPassword)
        ->and($target->tokens()->count())->toBe(0);

    $log = AuditLog::query()->where('record_id', $target->id)->sole();

    expect($log->user_id)->toBe($actor->id)
        ->and($log->actor_role)->toBe(Role::SUPER_ADMIN)
        ->and($log->action)->toBe('deactivated')
        ->and($log->entity_name)->toBe('users')
        ->and($log->old_values)->toEqual(['is_active' => true])
        ->and($log->new_values)->toEqual(['is_active' => false]);

    // Only the target's tokens are revoked; the actor keeps working.
    $this->withToken($actorToken)->getJson('/api/v1/me')->assertOk();

    Auth::forgetGuards();

    $this->withToken($targetToken)->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
})->with([
    'CO_ADMIN' => [Role::CO_ADMIN, 80],
    'SUPER_ADMIN' => [Role::SUPER_ADMIN, 100],
    'SPECIALIST' => [Role::SPECIALIST, 40],
    'PARENT' => ['PARENT', 20],
    'RECEPTION' => ['RECEPTION', 60],
]);

it('lets a super admin reactivate every applicable target role without issuing tokens', function (string $roleName, int $level) {
    $actor = d13SuperAdmin();
    $target = d13Target($roleName, $level, ['is_active' => false]);

    $response = $this->withToken(d13Token($actor))
        ->postJson("/api/v1/users/{$target->id}/reactivate");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'User reactivated successfully')
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.role.name', $roleName);

    $target->refresh();

    expect($target->is_active)->toBeTrue()
        ->and($target->tokens()->count())->toBe(0);

    $log = AuditLog::query()->where('record_id', $target->id)->sole();

    expect($log->user_id)->toBe($actor->id)
        ->and($log->action)->toBe('reactivated')
        ->and($log->entity_name)->toBe('users')
        ->and($log->old_values)->toEqual(['is_active' => false])
        ->and($log->new_values)->toEqual(['is_active' => true]);
})->with([
    'CO_ADMIN' => [Role::CO_ADMIN, 80],
    'SUPER_ADMIN' => [Role::SUPER_ADMIN, 100],
    'SPECIALIST' => [Role::SPECIALIST, 40],
    'PARENT' => ['PARENT', 20],
    'RECEPTION' => ['RECEPTION', 60],
]);

it('forbids a super admin from deactivating or reactivating themselves', function () {
    $actor = d13SuperAdmin();
    $token = d13Token($actor);

    $this->withToken($token)
        ->postJson("/api/v1/users/{$actor->id}/deactivate")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    $this->withToken($token)
        ->postJson("/api/v1/users/{$actor->id}/reactivate")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    expect($actor->fresh()->is_active)->toBeTrue()
        ->and($actor->tokens()->count())->toBe(1)
        ->and(DB::table('audit_logs')->count())->toBe(0);

    $this->withToken($token)->getJson('/api/v1/me')->assertOk();
});

it('lets a co admin deactivate and reactivate a specialist', function () {
    $actor = d13CoAdmin();
    $target = d13Target(Role::SPECIALIST, 40);

    $oldToken = d13Token($target);
    $actorToken = d13Token($actor);

    $this->withToken($actorToken)
        ->postJson("/api/v1/users/{$target->id}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect($target->fresh()->is_active)->toBeFalse()
        ->and($target->tokens()->count())->toBe(0);

    $this->withToken($actorToken)
        ->postJson("/api/v1/users/{$target->id}/reactivate")
        ->assertOk()
        ->assertJsonPath('data.is_active', true);

    expect($target->fresh()->is_active)->toBeTrue()
        ->and($target->tokens()->count())->toBe(0);

    expect(AuditLog::query()->where('record_id', $target->id)->orderBy('created_at')->pluck('action')->all())
        ->toBe(['deactivated', 'reactivated']);

    // Revoked tokens stay revoked after reactivation.
    Auth::forgetGuards();

    $this->withToken($oldToken)->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');

    // A newly issued token works again.
    $this->withToken(d13Token($target, 'fresh-token'))
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $target->id);
});

it('forbids a co admin from managing any prohibited target role', function (string $roleName, int $level) {
    $actor = d13CoAdmin();
    $target = d13Target($roleName, $level);
    $targetToken = d13Token($target);

    $this->withToken(d13Token($actor))
        ->postJson("/api/v1/users/{$target->id}/deactivate")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    $inactiveTarget = d13Target($roleName, $level, [
        'is_active' => false,
        'email' => 'inactive.'.mb_strtolower($roleName).'.target@example.test',
        'mobile' => '+966500000105',
    ]);
    $inactiveToken = d13Token($inactiveTarget);

    $this->withToken(d13Token($actor))
        ->postJson("/api/v1/users/{$inactiveTarget->id}/reactivate")
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    expect($target->fresh()->is_active)->toBeTrue()
        ->and($target->tokens()->count())->toBe(1)
        ->and($inactiveTarget->fresh()->is_active)->toBeFalse()
        ->and($inactiveTarget->tokens()->count())->toBe(1)
        ->and(DB::table('audit_logs')->count())->toBe(0);

    Auth::forgetGuards();

    $this->withToken($targetToken)->getJson('/api/v1/me')->assertOk();

    Auth::forgetGuards();

    $this->withToken($inactiveToken)->getJson('/api/v1/me')->assertUnauthorized();
})->with([
    'CO_ADMIN' => [Role::CO_ADMIN, 80],
    'SUPER_ADMIN' => [Role::SUPER_ADMIN, 100],
    'PARENT' => ['PARENT', 20],
    'RECEPTION' => ['RECEPTION', 60],
]);

it('forbids a co admin without specialists.manage', function () {
    $actor = d13CoAdmin(withSpecialistsManage: false);
    $target = d13Target(Role::SPECIALIST, 40);
    $inactiveTarget = d13Target(Role::SPECIALIST, 40, [
        'is_active' => false,
        'email' => 'inactive.specialist.target@example.test',
        'mobile' => '+966500000106',
    ]);

    $this->withToken(d13Token($actor))
        ->postJson("/api/v1/users/{$target->id}/deactivate")
        ->assertForbidden();

    $this->withToken(d13Token($actor))
        ->postJson("/api/v1/users/{$inactiveTarget->id}/reactivate")
        ->assertForbidden();

    expect($target->fresh()->is_active)->toBeTrue()
        ->and($inactiveTarget->fresh()->is_active)->toBeFalse()
        ->and(DB::table('audit_logs')->count())->toBe(0);
});

it('does not let an individual users.deactivate grant substitute for specialists.manage', function () {
    $actor = d13CoAdmin(withSpecialistsManage: false, extraPermissions: ['users.deactivate']);
    $specialist = d13Target(Role::SPECIALIST, 40);

    $this->withToken(d13Token($actor))
        ->postJson("/api/v1/users/{$specialist->id}/deactivate")
        ->assertForbidden();

    $this->withToken(d13Token($actor))
        ->postJson("/api/v1/users/{$specialist->id}/reactivate")
        ->assertForbidden();

    expect($specialist->fresh()->is_active)->toBeTrue()
        ->and(DB::table('audit_logs')->count())->toBe(0);
});

it('does not let an individual users.deactivate grant widen the co admin scope', function () {
    $actor = d13CoAdmin(extraPermissions: ['users.deactivate']);
    $specialist = d13Target(Role::SPECIALIST, 40);
    $coAdmin = d13Target(Role::CO_ADMIN, 80, [
        'email' => 'other.coadmin.target@example.test',
        'mobile' => '+966500000108',
    ]);

    $this->withToken(d13Token($actor))
        ->postJson("/api/v1/users/{$coAdmin->id}/deactivate")
        ->assertForbidden();

    // The same actor can still manage a SPECIALIST, proving the scope is the
    // role matrix and not the users.deactivate grant.
    $this->withToken(d13Token($actor))
        ->postJson("/api/v1/users/{$specialist->id}/deactivate")
        ->assertOk();

    expect($specialist->fresh()->is_active)->toBeFalse()
        ->and($coAdmin->fresh()->is_active)->toBeTrue();

    expect(AuditLog::query()->where('record_id', $coAdmin->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('record_id', $specialist->id)->count())->toBe(1);
});

it('denies every other role even with account permissions granted', function (string $roleName, int $level, bool $grantSpecialistsManage) {
    $role = fixtureRole($roleName, $level);

    if ($grantSpecialistsManage) {
        d13RolePermission($role, 'specialists.manage');
    }

    d13RolePermission($role, 'users.deactivate');

    $actor = fixtureUser($role, [
        'name' => "D13 {$roleName} Actor",
        'email' => mb_strtolower($roleName).'.actor@example.test',
        'mobile' => '+966500000103',
    ]);

    $target = d13Target(Role::SPECIALIST, 40);

    $this->withToken(d13Token($actor))
        ->postJson("/api/v1/users/{$target->id}/deactivate")
        ->assertForbidden();

    $this->withToken(d13Token($actor))
        ->postJson("/api/v1/users/{$target->id}/reactivate")
        ->assertForbidden();

    expect($target->fresh()->is_active)->toBeTrue()
        ->and(DB::table('audit_logs')->count())->toBe(0);
})->with([
    'SPECIALIST with specialists.manage' => [Role::SPECIALIST, 40, true],
    'SPECIALIST without specialists.manage' => [Role::SPECIALIST, 40, false],
    'PARENT' => ['PARENT', 20, false],
    'RECEPTION' => ['RECEPTION', 60, false],
]);

it('rejects unauthenticated status requests with the standard envelope', function () {
    $target = d13Target(Role::SPECIALIST, 40);

    foreach (['deactivate', 'reactivate'] as $action) {
        $this->postJson("/api/v1/users/{$target->id}/{$action}")
            ->assertUnauthorized()
            ->assertExactJson([
                'success' => false,
                'message' => 'Authentication required.',
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'details' => [],
                ],
            ]);
    }

    expect($target->fresh()->is_active)->toBeTrue()
        ->and(DB::table('audit_logs')->count())->toBe(0);
});

it('rejects inactive accounts on every protected api route', function (string $method, string $path, bool $needsTarget) {
    $actor = d13SuperAdmin(['is_active' => false]);
    $token = d13Token($actor);
    $target = d13Target(Role::SPECIALIST, 40);

    $url = str_replace('{target}', $target->id, '/api/v1'.$path);

    $this->withToken($token)
        ->json($method, $url, $needsTarget ? ['name' => 'Attempt'] : [])
        ->assertUnauthorized()
        ->assertExactJson([
            'success' => false,
            'message' => 'Authentication required.',
            'error' => [
                'code' => 'UNAUTHENTICATED',
                'details' => [],
            ],
        ]);

    expect($target->fresh()->is_active)->toBeTrue()
        ->and(DB::table('audit_logs')->count())->toBe(0);
})->with([
    'me' => ['GET', '/me', false],
    'logout' => ['POST', '/logout', false],
    'admins' => ['POST', '/admins', false],
    'specialists create' => ['POST', '/specialists', false],
    'specialists update' => ['PATCH', '/specialists/{target}', false],
    'deactivate' => ['POST', '/users/{target}/deactivate', false],
    'reactivate' => ['POST', '/users/{target}/reactivate', false],
]);

it('revokes every existing token when deactivating', function () {
    $actor = d13SuperAdmin();
    $target = d13Target(Role::SPECIALIST, 40);

    $tokens = [
        d13Token($target, 'first'),
        d13Token($target, 'second'),
        d13Token($target, 'third'),
    ];

    expect($target->tokens()->count())->toBe(3);

    $this->withToken(d13Token($actor))
        ->postJson("/api/v1/users/{$target->id}/deactivate")
        ->assertOk();

    expect($target->tokens()->count())->toBe(0);

    foreach ($tokens as $token) {
        Auth::forgetGuards();

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }
});

it('does not restore tokens after reactivation', function () {
    $actor = d13SuperAdmin();
    $target = d13Target(Role::SPECIALIST, 40);
    $oldToken = d13Token($target);

    $actorToken = d13Token($actor);

    $this->withToken($actorToken)
        ->postJson("/api/v1/users/{$target->id}/deactivate")
        ->assertOk();

    $this->withToken($actorToken)
        ->postJson("/api/v1/users/{$target->id}/reactivate")
        ->assertOk();

    expect($target->tokens()->count())->toBe(0);

    Auth::forgetGuards();

    $this->withToken($oldToken)->getJson('/api/v1/me')->assertUnauthorized();

    $newToken = d13Token($target, 'post-reactivation');
    $this->withToken($newToken)->getJson('/api/v1/me')->assertOk();
});

it('returns 200 for no-op status requests without duplicate audit rows or token changes', function () {
    $actor = d13SuperAdmin();
    $token = d13Token($actor);

    // No-op reactivation of an already-active account.
    $active = d13Target(Role::SPECIALIST, 40);

    $this->withToken($token)
        ->postJson("/api/v1/users/{$active->id}/reactivate")
        ->assertOk()
        ->assertJsonPath('data.is_active', true);

    expect(DB::table('audit_logs')->count())->toBe(0);

    // No-op deactivation of an already-inactive account keeps its tokens.
    $inactive = d13Target(Role::SPECIALIST, 40, [
        'is_active' => false,
        'email' => 'inactive.noop@example.test',
        'mobile' => '+966500000107',
    ]);
    d13Token($inactive);

    $this->withToken($token)
        ->postJson("/api/v1/users/{$inactive->id}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect($inactive->fresh()->is_active)->toBeFalse()
        ->and($inactive->tokens()->count())->toBe(1)
        ->and(DB::table('audit_logs')->count())->toBe(0);
});

it('writes exactly one audit row per actual transition', function () {
    $actor = d13SuperAdmin();
    $target = d13Target(Role::SPECIALIST, 40, ['is_active' => false]);
    $token = d13Token($actor);

    $this->withToken($token)
        ->postJson("/api/v1/users/{$target->id}/reactivate")
        ->assertOk();

    $this->withToken($token)
        ->postJson("/api/v1/users/{$target->id}/reactivate")
        ->assertOk();

    $this->withToken($token)
        ->postJson("/api/v1/users/{$target->id}/deactivate")
        ->assertOk();

    $this->withToken($token)
        ->postJson("/api/v1/users/{$target->id}/deactivate")
        ->assertOk();

    $logs = AuditLog::query()->where('record_id', $target->id)->orderBy('created_at')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->action)->toBe('reactivated')
        ->and($logs[0]->old_values)->toEqual(['is_active' => false])
        ->and($logs[0]->new_values)->toEqual(['is_active' => true])
        ->and($logs[1]->action)->toBe('deactivated')
        ->and($logs[1]->old_values)->toEqual(['is_active' => true])
        ->and($logs[1]->new_values)->toEqual(['is_active' => false]);
});

it('ignores role, permission, password and active-status fields in the request body', function () {
    $actor = d13SuperAdmin();
    $target = d13Target(Role::SPECIALIST, 40);
    $password = $target->password;
    $roleId = $target->role_id;

    $this->withToken(d13Token($actor))
        ->postJson("/api/v1/users/{$target->id}/deactivate", [
            'is_active' => true,
            'role_id' => fixtureRole(Role::SUPER_ADMIN, 100)->id,
            'permissions' => ['permissions.manage'],
            'password' => 'NewPassword123',
        ])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $target->refresh();

    expect($target->is_active)->toBeFalse()
        ->and($target->role_id)->toBe($roleId)
        ->and($target->password)->toBe($password)
        ->and($target->permissions()->count())->toBe(0);
});
