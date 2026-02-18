<?php

namespace MagsLabs\LaravelStoredProc\Tests;

use MagsLabs\LaravelStoredProc\Providers\PaginationServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            PaginationServiceProvider::class,
        ];
    }
}
