<?php

use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| D0.1 — Sanctum token infrastructure
|--------------------------------------------------------------------------
|
| These routes exist only inside the test process. No business endpoint is
| registered in the application; the goal is to prove the bearer-token guard,
| revocation and the existing error envelope.
|
*/

beforeEach(function () {
    Route::middleware('auth:api')->get('/api/v1/__d01/protected', function (Request $request) {
        return ApiResponse::success(['user_id' => $request->user()->id]);
    });

    Route::middleware('auth:api')->post('/api/v1/__d01/revoke', function (Request $request) {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Token revoked');
    });

    Route::middleware(['auth:api', 'can:__d01.denied'])->get('/api/v1/__d01/denied', function () {
        return ApiResponse::success();
    });
});

it('creates a personal access token for an existing user', function () {
    $user = User::factory()->create();

    $token = $user->createToken('d01-token');

    expect($token->plainTextToken)->toBeString()->not->toBeEmpty();

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_type' => User::class,
        'tokenable_id' => $user->id,
        'name' => 'd01-token',
    ]);

    expect($user->tokens()->count())->toBe(1);
});

it('authenticates a valid token against a protected route', function () {
    $user = User::factory()->create();
    $token = $user->createToken('d01-token')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/__d01/protected');

    $response->assertOk()->assertExactJson([
        'success' => true,
        'message' => 'Operation completed successfully',
        'data' => ['user_id' => $user->id],
        'meta' => null,
    ]);

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'name' => 'd01-token',
    ]);
});

it('rejects an invalid token with the existing 401 envelope', function () {
    $response = $this->withToken('not-a-real-token')->getJson('/api/v1/__d01/protected');

    $response->assertUnauthorized()->assertExactJson([
        'success' => false,
        'message' => 'Authentication required.',
        'error' => [
            'code' => 'UNAUTHENTICATED',
            'details' => [],
        ],
    ]);
});

it('rejects an unauthenticated request with the existing 401 envelope', function () {
    $response = $this->getJson('/api/v1/__d01/protected');

    $response->assertUnauthorized()->assertExactJson([
        'success' => false,
        'message' => 'Authentication required.',
        'error' => [
            'code' => 'UNAUTHENTICATED',
            'details' => [],
        ],
    ]);
});

it('prevents a revoked token from being reused', function () {
    $user = User::factory()->create();
    $token = $user->createToken('d01-token')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/__d01/protected')->assertOk();

    $this->withToken($token)->postJson('/api/v1/__d01/revoke')->assertOk();

    expect($user->tokens()->count())->toBe(0);

    // A new HTTP request would build a fresh guard; reset it in-process so the
    // test observes the same behaviour instead of a cached authenticated user.
    Auth::forgetGuards();

    $this->withToken($token)->getJson('/api/v1/__d01/protected')->assertUnauthorized();
});

it('does not let token authentication bypass an authorization boundary', function () {
    Gate::define('__d01.denied', fn () => false);

    $user = User::factory()->create();
    $token = $user->createToken('d01-token')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/__d01/denied')
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

it('keeps the existing API response format unchanged', function () {
    $this->getJson('/api/v1/health')->assertOk()->assertExactJson([
        'success' => true,
        'message' => 'Service is healthy',
        'data' => [
            'status' => 'ok',
            'application' => 'basma-noor',
            'version' => '1.0.0',
        ],
        'meta' => null,
    ]);
});
