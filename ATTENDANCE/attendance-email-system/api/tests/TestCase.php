<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Application;

abstract class TestCase extends BaseTestCase
{
    /**
     * Override database config on the Application instance so tests always
     * use SQLite :memory:, regardless of Docker OS env vars (DB_DATABASE=attendance_db)
     * that would otherwise bleed through dotenv before PHPUnit can override them.
     */
    protected function getEnvironmentSetUp(Application $app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('database.read_connection', 'sqlite');
    }
}
