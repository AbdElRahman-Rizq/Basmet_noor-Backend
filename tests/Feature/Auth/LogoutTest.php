<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

function d04LogoutToken(User $user, string $name): string
{
    return $user->createToken($name)->plainTextToken;
}

it('revokes the current token on logout', function () {
    $user = fixtureUser(fixtureRole('SPECIALIST', 40));
    $token = $user->createToken('current-token');

    $this->withToken($token->plainTextToken)
        ->postJson('/api/v1/logout')
        ->assertOk()
        ->assertExactJson([
            'success' => true,
            'message' => 'Logged out successfully',
            'data' => [],
            'meta' => null,
        ]);

    expect($user->tokens()->whereKey($token->accessToken->id)->exists())->toBeFalse();
});

it('prevents a revoked token from being reused', function () {
    $user = fixtureUser(fixtureRole('SPECIALIST', 40));
    $token = d04LogoutToken($user, 'current-token');

    $this->withToken($token)->getJson('/api/v1/me')->assertOk();
    $this->withToken($token)->postJson('/api/v1/logout')->assertOk();

    // A new HTTP request would build a fresh guard; reset it in-process so the
    // test observes token revocation instead of a cached authenticated user.
    Auth::forgetGuards();

    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
});

it('keeps other tokens belonging to the same user valid', function () {
    $user = fixtureUser(fixtureRole('SPECIALIST', 40));
    $current = d04LogoutToken($user, 'current-token');
    $other = d04LogoutToken($user, 'other-token');

    $this->withToken($current)->postJson('/api/v1/logout')->assertOk();

    Auth::forgetGuards();

    $this->withToken($other)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);

    expect($user->tokens()->count())->toBe(1)
        ->and($user->tokens()->first()->name)->toBe('other-token');
});

it('rejects an unauthenticated logout with the standard envelope', function () {
    $this->postJson('/api/v1/logout')
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
