<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // DatabaseMigrations runs db:wipe, which disconnects the MongoDB connection and leaves it
        // without a client, so transactions could not start a session. Purge it to reconnect lazily.
        DB::purge();
    }
}
