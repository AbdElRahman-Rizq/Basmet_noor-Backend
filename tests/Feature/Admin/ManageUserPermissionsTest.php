<?php

use App\Authorization\PermissionResolver;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function d14Token(User $user, string $name = 'd14-token'): string
{
    return $user->createToken($name)->plainTextToken;
}

function d14Permission(string $name): Permission
{
    return Permission::firstOrCreate(
        ['name' => $name],
        ['module' => explode('.', $name)[0]],
    );
}

function d14RolePermission(Role $role, string $permissionName): void
{
    $role->permissions()->syncWithoutDetaching([d14Permission($permissionName)->id]);
}

function d14SuperAdmin(array $attributes = []): User
{
    return fixtureUser(fixtureRole(Role::SUPER_ADMIN, 100), array_merge([
        'name' => 'D14 Super Admin',
        'email' => 'd14.super@example.test',
        'mobile' => '+966500000200',
    ], $attributes));
}

function d14CoAdmin(array $attributes = []): User
{
    return fixtureUser(fixtureRole(Role::CO_ADMIN, 80), array_merge([
        'name' => 'D14 Co Admin',
        'email' => 'd14.coadmin@example.test',
        'mobile' => '+966500000201',
    ], $attributes));
}

function d14Url(User $target): string
{
    return "/api/v1/users/{$target->id}/permissions";
}

it('lets a super admin grant an individual permission to a co admin', function () {
    $actor = d14SuperAdmin();
    $target = d14CoAdmin();
    d14Permission('audit_logs.view');

    $rolePermissionsBefore = DB::table('role_permissions')->orderBy('role_id')->get();

    $response = $this->withToken(d14Token($actor))
        ->putJson(d14Url($target), ['grants' => ['audit_logs.view']]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Permission overrides updated successfully')
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.role.name', Role::CO_ADMIN)
        ->assertJsonPath('data.overrides.grants', ['audit_logs.view'])
        ->assertJsonPath('data.overrides.denies', [])
        ->assertJsonPath('meta', null);

    expect($response->json('data.effective_permissions'))->toContain('audit_logs.view');

    $row = DB::table('user_permissions')
        ->where('user_id', $target->id)
        ->sole();

    expect((bool) $row->is_granted)->toBeTrue()
        ->and($row->granted_by_user_id)->toBe($actor->id);

    $log = AuditLog::query()->where('record_id', $target->id)->sole();

    expect($log->user_id)->toBe($actor->id)
        ->and($log->actor_role)->toBe(Role::SUPER_ADMIN)
        ->and($log->action)->toBe('permission_granted')
        ->and($log->entity_name)->toBe('user_permissions')
        ->and($log->old_values)->toEqual(['permission' => 'audit_logs.view', 'is_granted' => null])
        ->and($log->new_values)->toEqual(['permission' => 'audit_logs.view', 'is_granted' => true]);

    // The role defaults table is never touched by an individual override.
    expect(DB::table('role_permissions')->orderBy('role_id')->get()->toArray())
        ->toEqual($rolePermissionsBefore->toArray());
});

it('lets a super admin deny a permission that the role grants', function () {
    $role = fixtureRole(Role::CO_ADMIN, 80);
    d14RolePermission($role, 'reports.view_all');
    d14Permission('audit_logs.view');

    $actor = d14SuperAdmin();
    $target = fixtureUser($role, [
        'name' => 'D14 Co Admin Denied',
        'email' => 'd14.denied@example.test',
        'mobile' => '+966500000202',
    ]);

    $resolver = app(PermissionResolver::class);

    expect($resolver->allows($target, 'reports.view_all'))->toBeTrue();

    $response = $this->withToken(d14Token($actor))
        ->putJson(d14Url($target), ['denies' => ['reports.view_all']]);

    $response->assertOk()
        ->assertJsonPath('data.overrides.grants', [])
        ->assertJsonPath('data.overrides.denies', ['reports.view_all']);

    expect($resolver->permissionsFor($target->refresh()))->not->toContain('reports.view_all');

    $row = DB::table('user_permissions')->where('user_id', $target->id)->sole();

    expect((bool) $row->is_granted)->toBeFalse();

    $log = AuditLog::query()->where('record_id', $target->id)->sole();

    expect($log->action)->toBe('permission_denied')
        ->and($log->old_values)->toEqual(['permission' => 'reports.view_all', 'is_granted' => null])
        ->and($log->new_values)->toEqual(['permission' => 'reports.view_all', 'is_granted' => false]);
});

it('lets a super admin remove an override so the role default applies again', function () {
    $role = fixtureRole(Role::CO_ADMIN, 80);
    d14RolePermission($role, 'reports.view_all');
    d14Permission('audit_logs.view');

    $actor = d14SuperAdmin();
    $token = d14Token($actor);
    $target = fixtureUser($role, [
        'name' => 'D14 Co Admin Restored',
        'email' => 'd14.restored@example.test',
        'mobile' => '+966500000203',
    ]);

    $this->withToken($token)
        ->putJson(d14Url($target), ['grants' => ['audit_logs.view'], 'denies' => ['reports.view_all']])
        ->assertOk();

    $this->withToken($token)
        ->putJson(d14Url($target), ['grants' => [], 'denies' => []])
        ->assertOk()
        ->assertJsonPath('data.overrides.grants', [])
        ->assertJsonPath('data.overrides.denies', []);

    expect(DB::table('user_permissions')->where('user_id', $target->id)->count())->toBe(0);

    $resolver = app(PermissionResolver::class);

    expect($resolver->permissionsFor($target->refresh()))->toBe(['reports.view_all']);

    $logs = AuditLog::query()
        ->where('record_id', $target->id)
        ->where('action', 'permission_override_removed')
        ->get()
        ->keyBy(fn (AuditLog $log): string => $log->new_values['permission']);

    expect(AuditLog::query()->where('record_id', $target->id)->count())->toBe(4)
        ->and($logs)->toHaveCount(2)
        ->and($logs['audit_logs.view']->action)->toBe('permission_override_removed')
        ->and($logs['audit_logs.view']->old_values)->toEqual(['permission' => 'audit_logs.view', 'is_granted' => true])
        ->and($logs['audit_logs.view']->new_values)->toEqual(['permission' => 'audit_logs.view', 'is_granted' => null])
        ->and($logs['reports.view_all']->action)->toBe('permission_override_removed')
        ->and($logs['reports.view_all']->old_values)->toEqual(['permission' => 'reports.view_all', 'is_granted' => false])
        ->and($logs['reports.view_all']->new_values)->toEqual(['permission' => 'reports.view_all', 'is_granted' => null]);
});

it('keeps the documented resolution algorithm for grants and denies', function () {
    $role = fixtureRole(Role::CO_ADMIN, 80);
    d14RolePermission($role, 'reports.view_all');
    d14RolePermission($role, 'homework.view');
    d14Permission('audit_logs.view');

    $actor = d14SuperAdmin();
    $target = fixtureUser($role, [
        'name' => 'D14 Co Admin Algorithm',
        'email' => 'd14.algorithm@example.test',
        'mobile' => '+966500000204',
    ]);

    $this->withToken(d14Token($actor))
        ->putJson(d14Url($target), [
            'grants' => ['audit_logs.view'],
            'denies' => ['reports.view_all'],
        ])
        ->assertOk();

    $resolver = app(PermissionResolver::class);

    expect($resolver->permissionsFor($target->refresh()))
        ->toBe(['audit_logs.view', 'homework.view'])
        ->and($resolver->allows($target, 'reports.view_all'))->toBeFalse()
        ->and($resolver->allows($target, 'audit_logs.view'))->toBeTrue()
        ->and($resolver->allows($target, 'homework.view'))->toBeTrue();
});

it('forbids a co admin from managing permissions even with administrative grants', function () {
    $role = fixtureRole(Role::CO_ADMIN, 80);
    d14RolePermission($role, 'permissions.manage');
    d14RolePermission($role, 'admins.manage');
    d14RolePermission($role, 'users.update');
    d14Permission('audit_logs.view');

    $actor = fixtureUser($role, [
        'name' => 'D14 Elevated Co Admin',
        'email' => 'd14.elevated@example.test',
        'mobile' => '+966500000205',
    ]);
    $target = d14CoAdmin();

    $this->withToken(d14Token($actor))
        ->putJson(d14Url($target), ['grants' => ['audit_logs.view']])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    expect(DB::table('user_permissions')->count())->toBe(0)
        ->and(DB::table('audit_logs')->count())->toBe(0);
});

it('forbids other roles from managing permissions', function (string $roleName, int $level) {
    $role = fixtureRole($roleName, $level);
    d14RolePermission($role, 'permissions.manage');
    d14Permission('audit_logs.view');

    $actor = fixtureUser($role, [
        'name' => "D14 {$roleName} Actor",
        'email' => mb_strtolower($roleName).'.actor@example.test',
        'mobile' => '+966500000206',
    ]);
    $target = d14CoAdmin();

    $this->withToken(d14Token($actor))
        ->putJson(d14Url($target), ['grants' => ['audit_logs.view']])
        ->assertForbidden();

    expect(DB::table('user_permissions')->count())->toBe(0)
        ->and(DB::table('audit_logs')->count())->toBe(0);
})->with([
    'SPECIALIST' => [Role::SPECIALIST, 40],
    'PARENT' => ['PARENT', 20],
    'RECEPTION' => ['RECEPTION', 60],
]);

it('forbids users from modifying their own permissions', function () {
    d14Permission('audit_logs.view');

    $superAdmin = d14SuperAdmin();
    $superToken = d14Token($superAdmin);

    $this->withToken($superToken)
        ->putJson(d14Url($superAdmin), ['grants' => ['audit_logs.view']])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    $coAdminRole = fixtureRole(Role::CO_ADMIN, 80);
    d14RolePermission($coAdminRole, 'permissions.manage');

    $coAdmin = fixtureUser($coAdminRole, [
        'name' => 'D14 Self Co Admin',
        'email' => 'd14.self@example.test',
        'mobile' => '+966500000207',
    ]);

    Auth::forgetGuards();

    $this->withToken(d14Token($coAdmin))
        ->putJson(d14Url($coAdmin), ['grants' => ['audit_logs.view']])
        ->assertForbidden();

    expect(DB::table('user_permissions')->count())->toBe(0)
        ->and(DB::table('audit_logs')->count())->toBe(0);
});

it('rejects grants of non delegable permissions', function (string $permissionName) {
    d14Permission($permissionName);

    $actor = d14SuperAdmin();
    $target = d14CoAdmin();

    $this->withToken(d14Token($actor))
        ->putJson(d14Url($target), ['grants' => [$permissionName]])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['details' => ['grants']]]);

    expect(DB::table('user_permissions')->count())->toBe(0)
        ->and(DB::table('audit_logs')->count())->toBe(0);
})->with([
    'permissions.manage' => ['permissions.manage'],
    'admins.manage' => ['admins.manage'],
]);

it('rejects unknown permissions and overlapping grants and denies', function (array $payload, string $field) {
    d14Permission('audit_logs.view');

    $actor = d14SuperAdmin();
    $target = d14CoAdmin();

    $this->withToken(d14Token($actor))
        ->putJson(d14Url($target), $payload)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['details' => [$field]]]);

    expect(DB::table('user_permissions')->count())->toBe(0)
        ->and(DB::table('audit_logs')->count())->toBe(0);
})->with([
    'unknown grant' => [['grants' => ['does.not.exist']], 'grants.0'],
    'unknown deny' => [['denies' => ['does.not.exist']], 'denies.0'],
    'overlap' => [['grants' => ['audit_logs.view'], 'denies' => ['audit_logs.view']], 'denies'],
]);

it('forbids managing permissions of non co admin targets', function (string $roleName, int $level) {
    d14Permission('audit_logs.view');

    $actor = d14SuperAdmin();
    $target = fixtureUser(fixtureRole($roleName, $level), [
        'name' => "D14 {$roleName} Target",
        'email' => mb_strtolower($roleName).'.target@example.test',
        'mobile' => '+966500000208',
    ]);

    $this->withToken(d14Token($actor))
        ->putJson(d14Url($target), ['grants' => ['audit_logs.view']])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    expect(DB::table('user_permissions')->count())->toBe(0)
        ->and(DB::table('audit_logs')->count())->toBe(0);
})->with([
    'SUPER_ADMIN' => [Role::SUPER_ADMIN, 100],
    'SPECIALIST' => [Role::SPECIALIST, 40],
    'PARENT' => ['PARENT', 20],
    'RECEPTION' => ['RECEPTION', 60],
]);

it('audits every actual override change with actor, target and old and new values', function () {
    $role = fixtureRole(Role::CO_ADMIN, 80);
    d14RolePermission($role, 'reports.view_all');
    d14Permission('audit_logs.view');
    d14Permission('users.deactivate');

    $actor = d14SuperAdmin();
    $target = fixtureUser($role, [
        'name' => 'D14 Co Admin Audited',
        'email' => 'd14.audited@example.test',
        'mobile' => '+966500000209',
    ]);

    $this->withToken(d14Token($actor))
        ->putJson(d14Url($target), [
            'grants' => ['audit_logs.view', 'users.deactivate'],
            'denies' => ['reports.view_all'],
        ])
        ->assertOk();

    $logs = AuditLog::query()->where('record_id', $target->id)->get()
        ->keyBy(fn (AuditLog $log): string => $log->new_values['permission']);

    expect($logs)->toHaveCount(3)
        ->and($logs['audit_logs.view']->action)->toBe('permission_granted')
        ->and($logs['audit_logs.view']->new_values)->toEqual(['permission' => 'audit_logs.view', 'is_granted' => true])
        ->and($logs['users.deactivate']->action)->toBe('permission_granted')
        ->and($logs['users.deactivate']->new_values)->toEqual(['permission' => 'users.deactivate', 'is_granted' => true])
        ->and($logs['reports.view_all']->action)->toBe('permission_denied')
        ->and($logs['reports.view_all']->old_values)->toEqual(['permission' => 'reports.view_all', 'is_granted' => null])
        ->and($logs['reports.view_all']->new_values)->toEqual(['permission' => 'reports.view_all', 'is_granted' => false]);

    foreach ($logs as $log) {
        expect($log->user_id)->toBe($actor->id)
            ->and($log->actor_role)->toBe(Role::SUPER_ADMIN)
            ->and($log->entity_name)->toBe('user_permissions')
            ->and($log->record_id)->toBe($target->id);
    }
});

it('does not write audit rows or change state for a no-op update', function () {
    d14Permission('audit_logs.view');

    $actor = d14SuperAdmin();
    $token = d14Token($actor);
    $target = d14CoAdmin();

    $this->withToken($token)
        ->putJson(d14Url($target), ['grants' => ['audit_logs.view']])
        ->assertOk();

    $this->withToken($token)
        ->putJson(d14Url($target), ['grants' => ['audit_logs.view']])
        ->assertOk()
        ->assertJsonPath('data.overrides.grants', ['audit_logs.view']);

    expect(AuditLog::query()->where('record_id', $target->id)->count())->toBe(1)
        ->and(DB::table('user_permissions')->where('user_id', $target->id)->count())->toBe(1);
});

it('leaves other co admin overrides untouched', function () {
    d14Permission('audit_logs.view');
    d14Permission('users.deactivate');

    $actor = d14SuperAdmin();
    $token = d14Token($actor);

    $target = d14CoAdmin();
    $other = d14CoAdmin([
        'name' => 'D14 Other Co Admin',
        'email' => 'd14.other@example.test',
        'mobile' => '+966500000210',
    ]);

    $this->withToken($token)
        ->putJson(d14Url($other), ['grants' => ['users.deactivate']])
        ->assertOk();

    $this->withToken($token)
        ->putJson(d14Url($target), ['grants' => ['audit_logs.view']])
        ->assertOk();

    expect(DB::table('user_permissions')->where('user_id', $other->id)->count())->toBe(1)
        ->and(DB::table('user_permissions')->where('user_id', $target->id)->count())->toBe(1);

    $otherRow = DB::table('user_permissions')->where('user_id', $other->id)->sole();
    $targetRow = DB::table('user_permissions')->where('user_id', $target->id)->sole();

    expect($otherRow->permission_id)->toBe(d14Permission('users.deactivate')->id)
        ->and($targetRow->permission_id)->toBe(d14Permission('audit_logs.view')->id);
});

it('returns 401 for unauthenticated and inactive actors', function () {
    d14Permission('audit_logs.view');

    $target = d14CoAdmin();

    $envelope = [
        'success' => false,
        'message' => 'Authentication required.',
        'error' => [
            'code' => 'UNAUTHENTICATED',
            'details' => [],
        ],
    ];

    $this->putJson(d14Url($target), ['grants' => ['audit_logs.view']])
        ->assertUnauthorized()
        ->assertExactJson($envelope);

    $inactive = d14SuperAdmin(['is_active' => false]);

    $this->withToken(d14Token($inactive))
        ->putJson(d14Url($target), ['grants' => ['audit_logs.view']])
        ->assertUnauthorized()
        ->assertExactJson($envelope);

    expect(DB::table('user_permissions')->count())->toBe(0)
        ->and(DB::table('audit_logs')->count())->toBe(0);
});
