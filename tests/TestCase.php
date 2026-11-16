<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Hard guard: the test suite must never write to the development database.
        // `$_SERVER` values coming from the container can win over phpunit.xml,
        // so the isolated test database is enforced here as well.
        config(['database.connections.pgsql.database' => 'basma_noor_test']);
        DB::purge('pgsql');
    }
}
