<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

it('returns JSON 404 for unknown API routes even without an Accept header', function () {
    $this->get('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'NOT_FOUND');
});

it('returns 405 with a stable error code for a wrong method', function () {
    $this->postJson('/api/v1/health')
        ->assertStatus(405)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'METHOD_NOT_ALLOWED');
});

it('formats validation errors in the standard envelope', function () {
    Route::middleware('api')->get('/api/v1/_test/validation', function () {
        throw ValidationException::withMessages([
            'email' => ['The email field is required.'],
        ]);
    });

    $this->getJson('/api/v1/_test/validation')
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Validation failed')
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonPath('error.details.email.0', 'The email field is required.');
});

it('returns 401 for unauthenticated API requests', function () {
    Route::middleware('api')->get('/api/v1/_test/unauthenticated', function () {
        throw new AuthenticationException;
    });

    $this->getJson('/api/v1/_test/unauthenticated')
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
});

it('returns 403 for forbidden API requests', function () {
    Route::middleware('api')->get('/api/v1/_test/forbidden', function () {
        abort(403);
    });

    $this->getJson('/api/v1/_test/forbidden')
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

it('never leaks internal details on server errors in production', function () {
    Route::middleware('api')->get('/api/v1/_test/boom', function () {
        throw new RuntimeException('SECRET database credential leaked');
    });

    $this->app['env'] = 'production';
    config(['app.debug' => false]);

    $response = $this->getJson('/api/v1/_test/boom');

    $response->assertStatus(500)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'An unexpected error occurred.')
        ->assertJsonPath('error.code', 'SERVER_ERROR');

    expect($response->getContent())
        ->not->toContain('SECRET')
        ->not->toContain('RuntimeException')
        ->not->toContain('Stack trace')
        ->not->toContain(base_path());
});
