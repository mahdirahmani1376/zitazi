<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();


        if (config('database.connections.mysql.database') !== 'zitazi_test') {
            throw new \RuntimeException('Tests must run against the zitazi_test database, not the live zitazi database.');
        }
    }
}
