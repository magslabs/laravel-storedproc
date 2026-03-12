<?php

namespace MagsLabs\LaravelStoredProc\Tests;

use MagsLabs\LaravelStoredProc\Providers\PaginationServiceProvider;
use MagsLabs\LaravelStoredProc\Providers\StoredProcedureServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            StoredProcedureServiceProvider::class,
            PaginationServiceProvider::class,
        ];
    }
}
