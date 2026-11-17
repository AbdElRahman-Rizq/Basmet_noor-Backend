<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function d04Permission(string $name): Permission
{
    return Permission::create([
        'name' => $name,
        'module' => 'test',
    ]);
}

function d04Token(User $user, string $name = 'd04-token'): string
{
    return $user->createToken($name)->plainTextToken;
}

it('returns the authenticated user safe profile, role, and effective permissions', function () {
    $view = d04Permission('children.view_all');
    $create = d04Permission('children.create');

    $role = fixtureRole('SPECIALIST', 40);
    $role->permissions()->attach([$view->id, $create->id]);

    $user = fixtureUser($role, [
        'name' => 'Test Specialist',
        'email' => 'specialist@example.test',
        'mobile' => '+966500000001',
    ]);

    $response = $this->withToken(d04Token($user))->getJson('/api/v1/me');

    $response->assertOk()->assertExactJson([
        'success' => true,
        'message' => 'Current user loaded',
        'data' => [
            'id' => $user->id,
            'name' => 'Test Specialist',
            'email' => 'specialist@example.test',
            'mobile' => '+966500000001',
            'is_active' => true,
            'last_login_at' => null,
            'role' => [
                'id' => $role->id,
                'name' => 'SPECIALIST',
            ],
            'permissions' => ['children.create', 'children.view_all'],
        ],
        'meta' => null,
    ]);

    expect($response->json('data'))
        ->not->toHaveKeys(['password', 'remember_token', 'role_id', 'deleted_at', 'created_by_user_id']);
});

it('reflects grants and denies in the permissions payload', function () {
    $view = d04Permission('children.view_all');
    $create = d04Permission('children.create');
    $reports = d04Permission('reports.view_all');

    $role = fixtureRole('SPECIALIST', 40);
    $role->permissions()->attach([$view->id, $create->id]);

    $user = fixtureUser($role);

    DB::table('user_permissions')->insert([
        [
            'user_id' => $user->id,
            'permission_id' => $reports->id,
            'is_granted' => true,
            'granted_by_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'user_id' => $user->id,
            'permission_id' => $create->id,
            'is_granted' => false,
            'granted_by_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->withToken(d04Token($user))
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.permissions', ['children.view_all', 'reports.view_all']);
});

it('returns every catalogue permission for SUPER_ADMIN', function () {
    d04Permission('children.view_all');
    d04Permission('reports.view_all');

    $role = fixtureRole(Role::SUPER_ADMIN, 100);
    $user = fixtureUser($role);

    $this->withToken(d04Token($user))
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.role.name', Role::SUPER_ADMIN)
        ->assertJsonPath('data.permissions', ['children.view_all', 'reports.view_all']);

    expect(DB::table('role_permissions')->where('role_id', $role->id)->count())->toBe(0);
});

it('rejects an unauthenticated current-user request with the standard envelope', function () {
    $this->getJson('/api/v1/me')
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
