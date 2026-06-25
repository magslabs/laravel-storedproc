<?php

namespace MagsLabs\LaravelStoredProc\Providers;

use Illuminate\Support\ServiceProvider;
use MagsLabs\LaravelStoredProc\StoredProcedure;
use MagsLabs\LaravelStoredProc\Support\StoredProcedureDriverManager;

class StoredProcedureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/storedproc.php', 'storedproc');

        $this->app->singleton(StoredProcedureDriverManager::class);

        $this->app->bind(StoredProcedure::class, function ($app) {
            return new StoredProcedure(
                driver_manager: $app->make(StoredProcedureDriverManager::class),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/storedproc.php' => config_path('storedproc.php'),
            ], 'storedproc-config');
        }
    }
}
