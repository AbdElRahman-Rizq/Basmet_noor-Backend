<?php

use Illuminate\Support\Facades\DB;

it('runs against the dedicated test database, never the development one', function () {
    expect(config('database.connections.pgsql.database'))->toBe('basma_noor_test');

    $current = DB::selectOne('select current_database() as db')->db;

    expect($current)->toBe('basma_noor_test');
});
