<?php

namespace App\Repositories\Concerns;

/**
 * Resolves the database connection used for read queries.
 *
 * Production: defaults to 'mysql::read' (Laravel's read/write-split syntax
 * for the mysql connection's read pool).
 *
 * Tests / CI: phpunit.xml sets DB_READ_CONNECTION=sqlite, and TestCase
 * also sets config('database.read_connection') = 'sqlite', so all
 * repositories transparently use the in-memory sqlite database instead
 * of attempting a real MySQL connection.
 */
trait UsesReadConnection
{
    protected static function readConnection(): string
    {
        return config('database.read_connection', 'mysql::read');
    }
}
