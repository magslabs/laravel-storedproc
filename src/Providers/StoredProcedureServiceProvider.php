<?php

namespace MagsLabs\LaravelStoredProc\Providers;

use Illuminate\Support\ServiceProvider;
use MagsLabs\LaravelStoredProc\StoredProcedure;

class StoredProcedureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StoredProcedure::class, function () {
            return new StoredProcedure;
        });
    }
}
