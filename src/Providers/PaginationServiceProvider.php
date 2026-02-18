<?php

namespace MagsLabs\LaravelStoredProc\Providers;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use MagsLabs\LaravelStoredProc\StoredProcedure;

class PaginationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StoredProcedure::class, function () {
            return new StoredProcedure;
        });
    }

    public function boot(): void
    {
        if (! Collection::hasMacro('paginate')) {
            Collection::macro('paginate', function ($perPage = 15, $page = null, $pageName = 'page') {
                $page = $page ?: LengthAwarePaginator::resolveCurrentPage($pageName);
                $items = $this->forPage($page, $perPage);

                return new LengthAwarePaginator(
                    $items,
                    $this->count(),
                    $perPage,
                    $page,
                    [
                        'path' => request()->url(),
                        'query' => request()->query(),
                    ]
                );
            });
        }
    }
}
