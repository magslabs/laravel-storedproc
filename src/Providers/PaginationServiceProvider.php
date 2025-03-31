<?php

namespace MagsLabs\LaravelStoredProcedures\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PaginationServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if (!Collection::hasMacro('paginate')) {
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

    public function register()
    {
        //
    }
}
