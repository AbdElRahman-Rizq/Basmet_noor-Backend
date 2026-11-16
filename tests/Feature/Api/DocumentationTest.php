<?php

it('serves the API documentation page', function () {
    $this->get('/docs/api')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSee('Basmat Nour API', false);
});

it('serves a loadable OpenAPI document', function () {
    $response = $this->get('/docs/api.json')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/json');

    $spec = $response->json();

    expect($spec)->toBeArray()
        ->and($spec['openapi'] ?? null)->toStartWith('3.1')
        ->and($spec['info']['title'] ?? null)->toBe('Basmat Nour API')
        ->and($spec['info']['version'] ?? null)->toBe('1.0.0')
        ->and($spec['servers'] ?? [])->not->toBeEmpty();
});

it('documents the health endpoint with the correct path and method', function () {
    $spec = $this->get('/docs/api.json')->json();

    expect($spec['paths'])->toHaveKey('/api/v1/health');

    $operation = $spec['paths']['/api/v1/health'];

    expect($operation)->toHaveKey('get')
        ->and($operation)->not->toHaveKey('post')
        ->and($operation['get']['summary'] ?? null)->toBe('Service health check')
        ->and($operation['get']['responses'] ?? [])->toHaveKey('200');
});

it('documents the real success envelope as an example', function () {
    $spec = $this->get('/docs/api.json')->json();

    $response200 = $spec['paths']['/api/v1/health']['get']['responses']['200'];
    $example = $response200['content']['application/json']['example'];

    expect($example)->toBe([
        'success' => true,
        'message' => 'Service is healthy',
        'data' => [
            'status' => 'ok',
            'application' => 'basma-noor',
            'version' => '1.0.0',
        ],
        'meta' => null,
    ]);

    // The documented example must match what the endpoint really returns.
    $this->getJson('/api/v1/health')->assertOk()->assertJson($example);
});

it('documents the standard error envelope', function () {
    $spec = $this->get('/docs/api.json')->json();

    $schema = $spec['components']['schemas']['ErrorEnvelope'] ?? null;

    expect($schema)->not->toBeNull()
        ->and($schema['properties'])->toHaveKeys(['success', 'message', 'error'])
        ->and($schema['properties']['error']['properties'])->toHaveKeys(['code', 'details']);

    $responses = $spec['paths']['/api/v1/health']['get']['responses'];

    expect($responses)->toHaveKey('405')
        ->and($responses['405']['content']['application/json']['schema']['$ref'])
        ->toBe('#/components/schemas/ErrorEnvelope')
        ->and($responses['default']['content']['application/json']['schema']['$ref'])
        ->toBe('#/components/schemas/ErrorEnvelope');
});

it('never exposes environment secrets in the specification', function () {
    $contents = $this->get('/docs/api.json')->getContent();

    foreach ([
        config('database.connections.pgsql.password'),
        env('APP_KEY'),
    ] as $secret) {
        if (is_string($secret) && $secret !== '') {
            expect($contents)->not->toContain($secret);
        }
    }

    expect($contents)->not->toContain('DB_PASSWORD');
});

it('keeps documentation unavailable outside local and testing environments', function () {
    $this->app['env'] = 'production';

    $this->get('/docs/api')->assertForbidden();
    $this->get('/docs/api.json')->assertForbidden();
});
