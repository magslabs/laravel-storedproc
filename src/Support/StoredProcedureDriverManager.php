<?php

namespace MagsLabs\LaravelStoredProc\Support;

use Illuminate\Database\Connection;
use MagsLabs\LaravelStoredProc\Contracts\StoredProcedureDriver;
use MagsLabs\LaravelStoredProc\Contracts\StoredProcedureIntrospector;
use MagsLabs\LaravelStoredProc\Drivers\MySqlDriver;
use MagsLabs\LaravelStoredProc\Drivers\SqlServerDriver;
use MagsLabs\LaravelStoredProc\Exceptions\UnsupportedDriverException;
use MagsLabs\LaravelStoredProc\Introspectors\MySqlIntrospector;
use MagsLabs\LaravelStoredProc\Introspectors\SqlServerIntrospector;

class StoredProcedureDriverManager
{
    public function driverFor(Connection $connection): StoredProcedureDriver
    {
        return match ($connection->getDriverName()) {
            'mysql', 'mariadb' => new MySqlDriver,
            'sqlsrv' => new SqlServerDriver,
            default => throw new UnsupportedDriverException(
                "Stored procedure driver [{$connection->getDriverName()}] is not supported."
            ),
        };
    }

    public function introspectorFor(Connection $connection): StoredProcedureIntrospector
    {
        return match ($connection->getDriverName()) {
            'mysql', 'mariadb' => new MySqlIntrospector($connection),
            'sqlsrv' => new SqlServerIntrospector($connection),
            default => throw new UnsupportedDriverException(
                "Stored procedure introspection for [{$connection->getDriverName()}] is not supported."
            ),
        };
    }
}
