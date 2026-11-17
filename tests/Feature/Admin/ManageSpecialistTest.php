<?php

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Specialist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function d12Token(User $user, string $name = 'd12-token'): string
{
    return $user->createToken($name)->plainTextToken;
}

function d12GrantRolePermission(Role $role, string $permissionName, string $module = 'specialists'): void
{
    $permission = Permission::firstOrCreate(
        ['name' => $permissionName],
        ['module' => $module],
    );

    $role->permissions()->syncWithoutDetaching([$permission->id]);
}

function d12CoAdmin(bool $withPermission = true): User
{
    $role = fixtureRole(Role::CO_ADMIN, 80);

    if ($withPermission) {
        d12GrantRolePermission($role, 'specialists.manage');
    }

    return fixtureUser($role, ['name' => 'Co Admin']);
}

function d12Specialist(array $attributes = []): User
{
    return fixtureUser(fixtureRole(Role::SPECIALIST, 40), array_merge([
        'name' => 'Existing Specialist',
        'email' => 'existing.specialist@example.test',
        'mobile' => '+966500000020',
    ], $attributes));
}

function d12Payload(array $overrides = []): array
{
    return array_merge([
        'name' => 'New Specialist',
        'email' => 'specialist@example.test',
        'mobile' => '+966500000021',
        'password' => 'StrongPass123',
    ], $overrides);
}

it('lets a co admin create a specialist account and domain profile', function () {
    $actor = d12CoAdmin();
    fixtureRole(Role::SPECIALIST, 40);

    $response = $this->withToken(d12Token($actor))
        ->postJson('/api/v1/specialists', d12Payload());

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'SPECIALIST created successfully')
        ->assertJsonPath('data.name', 'New Specialist')
        ->assertJsonPath('data.email', 'specialist@example.test')
        ->assertJsonPath('data.mobile', '+966500000021')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.role.name', Role::SPECIALIST)
        ->assertJsonPath('meta', null);

    $created = User::query()->where('email', 'specialist@example.test')->firstOrFail();
    $role = Role::query()->where('name', Role::SPECIALIST)->firstOrFail();

    expect($created->role_id)->toBe($role->id)
        ->and($created->created_by_user_id)->toBe($actor->id)
        ->and($created->is_active)->toBeTrue()
        ->and(Hash::check('StrongPass123', $created->password))->toBeTrue()
        ->and($created->password)->not->toBe('StrongPass123')
        ->and(Specialist::query()->where('user_id', $created->id)->exists())->toBeTrue();

    expect($response->json('data'))
        ->not->toHaveKeys(['password', 'remember_token', 'role_id', 'created_by_user_id', 'deleted_at']);

    expect($response->getContent())->not->toContain('StrongPass123');
});

it('writes the specialist creation to the audit log without sensitive data', function () {
    $actor = d12CoAdmin();
    fixtureRole(Role::SPECIALIST, 40);

    $this->withToken(d12Token($actor))
        ->postJson('/api/v1/specialists', d12Payload())
        ->assertCreated();

    $created = User::query()->where('email', 'specialist@example.test')->firstOrFail();
    $log = AuditLog::query()->where('record_id', $created->id)->firstOrFail();

    expect($log->user_id)->toBe($actor->id)
        ->and($log->actor_role)->toBe(Role::CO_ADMIN)
        ->and($log->action)->toBe('created')
        ->and($log->entity_name)->toBe('users')
        ->and($log->new_values)->toMatchArray([
            'name' => 'New Specialist',
            'email' => 'specialist@example.test',
            'mobile' => '+966500000021',
            'role' => Role::SPECIALIST,
            'is_active' => true,
        ])
        ->and($log->new_values)->not->toHaveKey('password');

    $raw = json_encode(DB::table('audit_logs')->where('id', $log->id)->first());

    expect($raw)->not->toContain('StrongPass123')
        ->and($raw)->not->toContain($created->password);
});

it('lets a co admin edit an existing specialist', function () {
    $actor = d12CoAdmin();
    $target = d12Specialist();

    $response = $this->withToken(d12Token($actor))
        ->patchJson("/api/v1/specialists/{$target->id}", [
            'name' => 'Edited Specialist',
            'email' => 'edited.specialist@example.test',
            'mobile' => '+966500000022',
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'SPECIALIST updated successfully')
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.name', 'Edited Specialist')
        ->assertJsonPath('data.email', 'edited.specialist@example.test')
        ->assertJsonPath('data.mobile', '+966500000022')
        ->assertJsonPath('data.role.name', Role::SPECIALIST);

    $target->refresh();

    expect($target->name)->toBe('Edited Specialist')
        ->and($target->email)->toBe('edited.specialist@example.test')
        ->and($target->mobile)->toBe('+966500000022')
        ->and($target->role?->name)->toBe(Role::SPECIALIST);

    expect($response->getContent())->not->toContain('password');
});

it('audits specialist edits with old and new values only', function () {
    $actor = d12CoAdmin();
    $target = d12Specialist();

    $this->withToken(d12Token($actor))
        ->patchJson("/api/v1/specialists/{$target->id}", [
            'name' => 'Edited Specialist',
            'mobile' => '+966500000022',
        ])
        ->assertOk();

    $log = AuditLog::query()->where('record_id', $target->id)->firstOrFail();

    expect($log->user_id)->toBe($actor->id)
        ->and($log->actor_role)->toBe(Role::CO_ADMIN)
        ->and($log->action)->toBe('updated')
        ->and($log->entity_name)->toBe('users')
        ->and($log->old_values)->toEqual([
            'name' => 'Existing Specialist',
            'mobile' => '+966500000020',
        ])
        ->and($log->new_values)->toEqual([
            'name' => 'Edited Specialist',
            'mobile' => '+966500000022',
        ]);

    expect($log->old_values)->not->toHaveKey('password')
        ->and($log->new_values)->not->toHaveKey('password');
});

it('rejects role and server controlled fields when creating a specialist', function (array $payload, string $field) {
    $actor = d12CoAdmin();

    $this->withToken(d12Token($actor))
        ->postJson('/api/v1/specialists', d12Payload($payload))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['details' => [$field]]]);

    expect(User::query()->where('email', 'specialist@example.test')->exists())->toBeFalse()
        ->and(DB::table('audit_logs')->count())->toBe(0);
})->with([
    'role id' => [['role_id' => '00000000-0000-0000-0000-000000000000'], 'role_id'],
    'role name field' => [['role' => Role::SUPER_ADMIN], 'role'],
    'role name alias' => [['role_name' => Role::CO_ADMIN], 'role_name'],
    'active status' => [['is_active' => false], 'is_active'],
    'creator id' => [['created_by_user_id' => '00000000-0000-0000-0000-000000000000'], 'created_by_user_id'],
    'permission overrides' => [['permissions' => ['permissions.manage']], 'permissions'],
    'permission ids' => [['permission_ids' => ['00000000-0000-0000-0000-000000000000']], 'permission_ids'],
]);

it('does not let a co admin create a co admin or super admin', function () {
    $actor = d12CoAdmin();

    // The CO_ADMIN creation endpoint remains SUPER_ADMIN-only (D1.1).
    $this->withToken(d12Token($actor))
        ->postJson('/api/v1/admins', d12Payload(['email' => 'escalation@example.test']))
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    expect(User::query()->where('email', 'escalation@example.test')->exists())->toBeFalse()
        ->and(DB::table('audit_logs')->count())->toBe(0);
});

it('does not let a co admin edit a co admin or super admin', function () {
    $actor = d12CoAdmin();

    $coAdminTarget = fixtureUser(fixtureRole(Role::CO_ADMIN, 80), [
        'name' => 'Co Admin Target',
        'email' => 'coadmin.target@example.test',
        'mobile' => '+966500000030',
    ]);

    $superAdminTarget = fixtureUser(fixtureRole(Role::SUPER_ADMIN, 100), [
        'name' => 'Super Admin Target',
        'email' => 'super.target@example.test',
        'mobile' => '+966500000031',
    ]);

    $this->withToken(d12Token($actor))
        ->patchJson("/api/v1/specialists/{$coAdminTarget->id}", ['name' => 'Hijacked'])
        ->assertForbidden();

    $this->withToken(d12Token($actor))
        ->patchJson("/api/v1/specialists/{$superAdminTarget->id}", ['name' => 'Hijacked'])
        ->assertForbidden();

    expect($coAdminTarget->fresh()->name)->toBe('Co Admin Target')
        ->and($superAdminTarget->fresh()->name)->toBe('Super Admin Target')
        ->and(DB::table('audit_logs')->count())->toBe(0);
});

it('does not let a co admin promote a specialist through an update payload', function () {
    $actor = d12CoAdmin();
    $target = d12Specialist();
    $coAdminRole = fixtureRole(Role::CO_ADMIN, 80);

    $this->withToken(d12Token($actor))
        ->patchJson("/api/v1/specialists/{$target->id}", [
            'name' => 'Promotion Attempt',
            'role_id' => $coAdminRole->id,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['details' => ['role_id']]]);

    $target->refresh();

    expect($target->name)->toBe('Existing Specialist')
        ->and($target->role_id)->toBe(fixtureRole(Role::SPECIALIST, 40)->id)
        ->and(DB::table('audit_logs')->count())->toBe(0);
});

it('does not let a specialist manage users even with specialists.manage granted', function () {
    $role = fixtureRole(Role::SPECIALIST, 40);
    d12GrantRolePermission($role, 'specialists.manage');

    $actor = fixtureUser($role, [
        'name' => 'Elevated Specialist',
        'email' => 'elevated.specialist@example.test',
        'mobile' => '+966500000032',
    ]);

    $target = d12Specialist();

    $this->withToken(d12Token($actor))
        ->postJson('/api/v1/specialists', d12Payload())
        ->assertForbidden();

    $this->withToken(d12Token($actor))
        ->patchJson("/api/v1/specialists/{$target->id}", ['name' => 'Hijacked'])
        ->assertForbidden();

    expect(User::query()->where('email', 'specialist@example.test')->exists())->toBeFalse()
        ->and($target->fresh()->name)->toBe('Existing Specialist')
        ->and(DB::table('audit_logs')->count())->toBe(0);
});

it('requires specialists.manage for a co admin to manage specialists', function () {
    $actor = d12CoAdmin(withPermission: false);
    $target = d12Specialist();

    $this->withToken(d12Token($actor))
        ->postJson('/api/v1/specialists', d12Payload())
        ->assertForbidden();

    $this->withToken(d12Token($actor))
        ->patchJson("/api/v1/specialists/{$target->id}", ['name' => 'Hijacked'])
        ->assertForbidden();

    expect(User::query()->where('email', 'specialist@example.test')->exists())->toBeFalse()
        ->and(DB::table('audit_logs')->count())->toBe(0);
});

it('rejects an inactive co admin', function () {
    $actor = d12CoAdmin();
    $actor->update(['is_active' => false]);
    $target = d12Specialist();

    $this->withToken(d12Token($actor))
        ->postJson('/api/v1/specialists', d12Payload())
        ->assertForbidden();

    $this->withToken(d12Token($actor))
        ->patchJson("/api/v1/specialists/{$target->id}", ['name' => 'Hijacked'])
        ->assertForbidden();

    expect(User::query()->where('email', 'specialist@example.test')->exists())->toBeFalse()
        ->and(DB::table('audit_logs')->count())->toBe(0);
});

it('rejects an inactive super admin', function () {
    $actor = fixtureUser(fixtureRole(Role::SUPER_ADMIN, 100), [
        'name' => 'Inactive Super Admin',
        'is_active' => false,
    ]);

    $this->withToken(d12Token($actor))
        ->postJson('/api/v1/specialists', d12Payload())
        ->assertForbidden();

    expect(User::query()->where('email', 'specialist@example.test')->exists())->toBeFalse()
        ->and(DB::table('audit_logs')->count())->toBe(0);
});

it('keeps the specialist endpoint scoped even for a super admin', function () {
    $superAdmin = fixtureUser(fixtureRole(Role::SUPER_ADMIN, 100), [
        'name' => 'Super Admin',
        'email' => 'super.admin@example.test',
        'mobile' => '+966500000033',
    ]);

    $coAdminTarget = fixtureUser(fixtureRole(Role::CO_ADMIN, 80), [
        'name' => 'Co Admin Target',
        'email' => 'coadmin.scope@example.test',
        'mobile' => '+966500000034',
    ]);

    $this->withToken(d12Token($superAdmin))
        ->patchJson("/api/v1/specialists/{$coAdminTarget->id}", ['name' => 'Hijacked'])
        ->assertForbidden();

    expect($coAdminTarget->fresh()->name)->toBe('Co Admin Target')
        ->and(DB::table('audit_logs')->count())->toBe(0);
});

it('rejects unauthenticated requests with the standard envelope', function () {
    $target = d12Specialist();

    $this->postJson('/api/v1/specialists', d12Payload())
        ->assertUnauthorized()
        ->assertExactJson([
            'success' => false,
            'message' => 'Authentication required.',
            'error' => [
                'code' => 'UNAUTHENTICATED',
                'details' => [],
            ],
        ]);

    $this->patchJson("/api/v1/specialists/{$target->id}", ['name' => 'Hijacked'])
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

it('validates specialist creation input', function (array $payload, string $field) {
    $actor = d12CoAdmin();

    $this->withToken(d12Token($actor))
        ->postJson('/api/v1/specialists', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['details' => [$field]]]);
})->with([
    'missing name' => [d12Payload(['name' => null]), 'name'],
    'invalid email' => [d12Payload(['email' => 'not-an-email']), 'email'],
    'missing both identifiers' => [d12Payload(['email' => null, 'mobile' => null]), 'email'],
    'short password' => [d12Payload(['password' => 'short']), 'password'],
]);

it('validates specialist update input', function (array $payload, string $field) {
    $actor = d12CoAdmin();
    $target = d12Specialist();

    $this->withToken(d12Token($actor))
        ->patchJson("/api/v1/specialists/{$target->id}", $payload)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['details' => [$field]]]);
})->with([
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'empty name' => [['name' => ''], 'name'],
    'clearing both identifiers' => [['email' => null, 'mobile' => null], 'email'],
    'role id' => [['role_id' => '00000000-0000-0000-0000-000000000000'], 'role_id'],
    'active status' => [['is_active' => false], 'is_active'],
    'password change' => [['password' => 'AnotherPass123'], 'password'],
]);

it('rejects duplicate identifiers on update', function () {
    $actor = d12CoAdmin();
    $target = d12Specialist();

    fixtureUser(fixtureRole(Role::SPECIALIST, 40), [
        'name' => 'Other Specialist',
        'email' => 'taken@example.test',
        'mobile' => '+966500000035',
    ]);

    $this->withToken(d12Token($actor))
        ->patchJson("/api/v1/specialists/{$target->id}", ['email' => 'taken@example.test'])
        ->assertUnprocessable()
        ->assertJsonStructure(['error' => ['details' => ['email']]]);

    $this->withToken(d12Token($actor))
        ->patchJson("/api/v1/specialists/{$target->id}", ['mobile' => '+966500000035'])
        ->assertUnprocessable()
        ->assertJsonStructure(['error' => ['details' => ['mobile']]]);

    expect($target->fresh()->email)->toBe('existing.specialist@example.test')
        ->and($target->fresh()->mobile)->toBe('+966500000020')
        ->and(DB::table('audit_logs')->count())->toBe(0);
});

it('allows clearing one identifier when the other remains', function () {
    $actor = d12CoAdmin();
    $target = d12Specialist();
    $token = d12Token($actor);

    $this->withToken($token)
        ->patchJson("/api/v1/specialists/{$target->id}", ['email' => null])
        ->assertOk()
        ->assertJsonPath('data.email', null)
        ->assertJsonPath('data.mobile', '+966500000020');

    $this->withToken($token)
        ->patchJson("/api/v1/specialists/{$target->id}", [
            'email' => 'restored@example.test',
            'mobile' => null,
        ])
        ->assertOk()
        ->assertJsonPath('data.email', 'restored@example.test')
        ->assertJsonPath('data.mobile', null);

    $target->refresh();

    expect($target->email)->toBe('restored@example.test')
        ->and($target->mobile)->toBeNull();
});

it('does not write an audit row when an update changes nothing', function () {
    $actor = d12CoAdmin();
    $target = d12Specialist();

    $this->withToken(d12Token($actor))
        ->patchJson("/api/v1/specialists/{$target->id}", [
            'name' => 'Existing Specialist',
            'email' => 'existing.specialist@example.test',
            'mobile' => '+966500000020',
        ])
        ->assertOk();

    expect(DB::table('audit_logs')->count())->toBe(0);
});
