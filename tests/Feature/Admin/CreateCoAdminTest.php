<?php

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function d11Token(User $user, string $name = 'd11-token'): string
{
    return $user->createToken($name)->plainTextToken;
}

function d11CoAdminRole(): Role
{
    return fixtureRole(Role::CO_ADMIN, 80);
}

function d11SuperAdmin(): User
{
    return fixtureUser(fixtureRole(Role::SUPER_ADMIN, 100), ['name' => 'Super Admin']);
}

function d11Payload(array $overrides = []): array
{
    return array_merge([
        'name' => 'New Co Admin',
        'email' => 'coadmin@example.test',
        'mobile' => '+966500000010',
        'password' => 'StrongPass123',
    ], $overrides);
}

it('lets a super admin create a co admin', function () {
    d11CoAdminRole();
    $actor = d11SuperAdmin();

    $response = $this->withToken(d11Token($actor))
        ->postJson('/api/v1/admins', d11Payload());

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'CO_ADMIN created successfully')
        ->assertJsonPath('data.name', 'New Co Admin')
        ->assertJsonPath('data.email', 'coadmin@example.test')
        ->assertJsonPath('data.mobile', '+966500000010')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.role.name', Role::CO_ADMIN)
        ->assertJsonPath('meta', null);

    $created = User::query()->where('email', 'coadmin@example.test')->firstOrFail();
    $role = Role::query()->where('name', Role::CO_ADMIN)->firstOrFail();

    expect($created->role_id)->toBe($role->id)
        ->and($created->created_by_user_id)->toBe($actor->id)
        ->and($created->is_active)->toBeTrue()
        ->and(Hash::check('StrongPass123', $created->password))->toBeTrue()
        ->and($created->password)->not->toBe('StrongPass123');

    expect($response->json('data'))
        ->not->toHaveKeys(['password', 'remember_token', 'role_id', 'created_by_user_id', 'deleted_at']);

    expect($response->getContent())->not->toContain('StrongPass123');
});

it('writes the creation to the audit log without sensitive data', function () {
    d11CoAdminRole();
    $actor = d11SuperAdmin();

    $this->withToken(d11Token($actor))
        ->postJson('/api/v1/admins', d11Payload())
        ->assertCreated();

    $created = User::query()->where('email', 'coadmin@example.test')->firstOrFail();

    $log = AuditLog::query()->where('record_id', $created->id)->firstOrFail();

    expect($log->user_id)->toBe($actor->id)
        ->and($log->actor_role)->toBe(Role::SUPER_ADMIN)
        ->and($log->action)->toBe('created')
        ->and($log->entity_name)->toBe('users')
        ->and($log->record_id)->toBe($created->id)
        ->and($log->new_values)->toMatchArray([
            'name' => 'New Co Admin',
            'email' => 'coadmin@example.test',
            'mobile' => '+966500000010',
            'role' => Role::CO_ADMIN,
            'is_active' => true,
        ]);

    expect($log->new_values)->not->toHaveKey('password')
        ->and($log->old_values)->toBeNull();

    $raw = json_encode(DB::table('audit_logs')->where('id', $log->id)->first());

    expect($raw)->not->toContain('StrongPass123')
        ->and($raw)->not->toContain($created->password);
});

it('forbids a co admin from creating another co admin even with users.create granted', function () {
    d11CoAdminRole();
    $actor = fixtureUser(fixtureRole(Role::CO_ADMIN, 80));

    $permission = Permission::create(['name' => 'users.create', 'module' => 'users']);

    DB::table('user_permissions')->insert([
        'user_id' => $actor->id,
        'permission_id' => $permission->id,
        'is_granted' => true,
        'granted_by_user_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->withToken(d11Token($actor))
        ->postJson('/api/v1/admins', d11Payload())
        ->assertForbidden()
        ->assertExactJson([
            'success' => false,
            'message' => 'This action is unauthorized.',
            'error' => [
                'code' => 'FORBIDDEN',
                'details' => [],
            ],
        ]);

    expect(User::query()->where('email', 'coadmin@example.test')->exists())->toBeFalse()
        ->and(DB::table('audit_logs')->count())->toBe(0);
});

it('forbids a specialist from creating a co admin', function () {
    d11CoAdminRole();

    $this->withToken(d11Token(fixtureUser(fixtureRole('SPECIALIST', 40))))
        ->postJson('/api/v1/admins', d11Payload())
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    expect(User::query()->where('email', 'coadmin@example.test')->exists())->toBeFalse();
});

it('forbids a parent from creating a co admin', function () {
    d11CoAdminRole();

    $this->withToken(d11Token(fixtureUser(fixtureRole('PARENT', 20))))
        ->postJson('/api/v1/admins', d11Payload())
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');

    expect(User::query()->where('email', 'coadmin@example.test')->exists())->toBeFalse();
});

it('forbids an inactive super admin from creating a co admin', function () {
    d11CoAdminRole();
    $actor = fixtureUser(fixtureRole(Role::SUPER_ADMIN, 100), ['is_active' => false]);

    $this->withToken(d11Token($actor))
        ->postJson('/api/v1/admins', d11Payload())
        ->assertForbidden();

    expect(User::query()->where('email', 'coadmin@example.test')->exists())->toBeFalse();
});

it('rejects an unauthenticated request with the standard envelope', function () {
    $this->postJson('/api/v1/admins', d11Payload())
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

it('validates required and malformed input', function (array $payload, string $field) {
    d11CoAdminRole();
    $actor = d11SuperAdmin();

    $this->withToken(d11Token($actor))
        ->postJson('/api/v1/admins', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['details' => [$field]]]);
})->with([
    'missing name' => [d11Payload(['name' => null]), 'name'],
    'missing password' => [d11Payload(['password' => null]), 'password'],
    'short password' => [d11Payload(['password' => 'short']), 'password'],
    'invalid email' => [d11Payload(['email' => 'not-an-email']), 'email'],
    'missing both identifiers' => [d11Payload(['email' => null, 'mobile' => null]), 'email'],
    'name too long' => [d11Payload(['name' => str_repeat('a', 151)]), 'name'],
    'email too long' => [d11Payload(['email' => str_repeat('a', 185).'@example.test']), 'email'],
]);

it('rejects client supplied role and server controlled fields', function (array $payload, string $field) {
    d11CoAdminRole();
    $actor = d11SuperAdmin();

    $this->withToken(d11Token($actor))
        ->postJson('/api/v1/admins', d11Payload($payload))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['details' => [$field]]]);
})->with([
    'role id' => [['role_id' => '00000000-0000-0000-0000-000000000000'], 'role_id'],
    'is active' => [['is_active' => false], 'is_active'],
    'created by' => [['created_by_user_id' => '00000000-0000-0000-0000-000000000000'], 'created_by_user_id'],
]);

it('ignores an untrusted role field and always assigns co admin', function () {
    d11CoAdminRole();
    $actor = d11SuperAdmin();

    $this->withToken(d11Token($actor))
        ->postJson('/api/v1/admins', d11Payload([
            'role' => Role::SUPER_ADMIN,
            'role_name' => Role::SUPER_ADMIN,
        ]))
        ->assertCreated()
        ->assertJsonPath('data.role.name', Role::CO_ADMIN);

    $created = User::query()->where('email', 'coadmin@example.test')->firstOrFail();

    expect($created->role?->name)->toBe(Role::CO_ADMIN)
        ->and($created->isSuperAdmin())->toBeFalse();
});

it('normalizes email and accepts a mobile-only user', function () {
    d11CoAdminRole();
    $actor = d11SuperAdmin();

    $this->withToken(d11Token($actor))
        ->postJson('/api/v1/admins', [
            'name' => 'Mobile Only',
            'email' => '  Mixed.Case@Example.TEST ',
            'password' => 'StrongPass123',
        ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'mixed.case@example.test');

    $this->withToken(d11Token($actor))
        ->postJson('/api/v1/admins', [
            'name' => 'No Email',
            'mobile' => '+966500000011',
            'password' => 'StrongPass123',
        ])
        ->assertCreated()
        ->assertJsonPath('data.email', null)
        ->assertJsonPath('data.mobile', '+966500000011');

    expect(User::query()->where('email', 'mixed.case@example.test')->exists())->toBeTrue()
        ->and(User::query()->where('mobile', '+966500000011')->exists())->toBeTrue();
});

it('rejects duplicate email and mobile identifiers', function () {
    d11CoAdminRole();
    $actor = d11SuperAdmin();

    $this->withToken(d11Token($actor))
        ->postJson('/api/v1/admins', d11Payload())
        ->assertCreated();

    $this->withToken(d11Token($actor))
        ->postJson('/api/v1/admins', d11Payload([
            'mobile' => '+966500000099',
        ]))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['details' => ['email']]]);

    $this->withToken(d11Token($actor))
        ->postJson('/api/v1/admins', d11Payload([
            'email' => 'other@example.test',
        ]))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['details' => ['mobile']]]);

    expect(User::query()->where('role_id', d11CoAdminRole()->id)->count())->toBe(1);
});
