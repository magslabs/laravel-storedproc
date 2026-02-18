<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use MagsLabs\LaravelStoredProc\StoredProcedure;

beforeEach(function () {
    DB::shouldReceive('getConfig')
        ->with('driver')
        ->andReturn('mysql');
});

it('sets procedure name via stored_procedure and returns chain', function () {
    $sp = new StoredProcedure();
    $instance = $sp->stored_procedure('my_procedure');

    expect($instance)->toBeInstanceOf(StoredProcedure::class);
});

it('chains stored_procedure with params and values', function () {
    $sp = new StoredProcedure();
    $result = $sp->stored_procedure('get_user')
        ->stored_procedure_params([':id'])
        ->stored_procedure_values([1]);

    expect($result)->toBeInstanceOf(StoredProcedure::class);
});

it('instantiates with mysql driver', function () {
    $sp = new StoredProcedure();

    expect($sp)->toBeInstanceOf(StoredProcedure::class);
});

it('registers paginate macro on collection', function () {
    expect(Collection::hasMacro('paginate'))->toBeTrue();

    $collection = collect([1, 2, 3, 4, 5]);
    $paginator = $collection->paginate(2);

    expect($paginator->total())->toBe(5);
    expect($paginator->count())->toBe(2);
});
