<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Hard guard: the test suite must never write to the development database.
        // The values are forced before the application boots so the `pgsql`
        // connection is built against the isolated test database from the start.
        // Purging the connection after `parent::setUp()` would destroy the
        // transaction that `RefreshDatabase` opens for every test.
        foreach (['DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => 'basma_noor_test', 'DB_URL' => ''] as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }

        parent::setUp();

        config(['database.connections.pgsql.database' => 'basma_noor_test']);
    }
}
