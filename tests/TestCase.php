<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $connectionName = config('database.default');
        $connectionConfig = config("database.connections.{$connectionName}");

        if (
            ! is_array($connectionConfig)
            || ($connectionConfig['driver'] ?? null) !== 'sqlite'
            || ($connectionConfig['database'] ?? null) !== ':memory:'
        ) {
            throw new RuntimeException('Tests may only use SQLite :memory:.');
        }

        $connection = DB::connection();

        if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
            throw new RuntimeException('Tests may only use SQLite :memory:.');
        }
    }
}
