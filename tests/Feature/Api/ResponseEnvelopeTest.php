<?php

use App\Support\ApiResponse;

it('builds the standard success envelope', function () {
    $payload = ApiResponse::success(['id' => 10], 'Created')->getData(true);

    expect($payload)->toBe([
        'success' => true,
        'message' => 'Created',
        'data' => ['id' => 10],
        'meta' => null,
    ]);
});

it('supports pagination meta and an empty data object', function () {
    $payload = ApiResponse::success(null, 'Loaded', [
        'current_page' => 1,
        'per_page' => 15,
        'total' => 0,
    ])->getData(true);

    expect($payload['data'])->toBe([])
        ->and($payload['meta'])->toBe([
            'current_page' => 1,
            'per_page' => 15,
            'total' => 0,
        ]);
});

it('builds the standard error envelope', function () {
    $payload = ApiResponse::error('SOME_CODE', 'Something failed', 409)->getData(true);

    expect($payload)->toBe([
        'success' => false,
        'message' => 'Something failed',
        'error' => [
            'code' => 'SOME_CODE',
            'details' => [],
        ],
    ]);
});
