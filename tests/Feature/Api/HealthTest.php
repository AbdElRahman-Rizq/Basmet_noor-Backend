<?php

it('returns the approved health contract', function () {
    $response = $this->getJson('/api/v1/health');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
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

it('serves the health endpoint without touching the database', function () {
    // The endpoint must stay available even if PostgreSQL is down.
    // It is a pure response; any DB access here would fail the query log assertion.
    \Illuminate\Support\Facades\DB::listen(function () {
        throw new RuntimeException('The health endpoint must not query the database.');
    });

    $this->getJson('/api/v1/health')->assertOk();
});
