<?php

use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use MagsLabs\LaravelStoredProc\Contracts\StoredProcedureIntrospector;
use MagsLabs\LaravelStoredProc\Data\ProcedureParameter;
use MagsLabs\LaravelStoredProc\Drivers\MySqlDriver;
use MagsLabs\LaravelStoredProc\Drivers\SqlServerDriver;
use MagsLabs\LaravelStoredProc\Exceptions\InvalidCallOrderException;
use MagsLabs\LaravelStoredProc\Exceptions\ParameterMismatchException;
use MagsLabs\LaravelStoredProc\Exceptions\StoredProcedureNotFoundException;
use MagsLabs\LaravelStoredProc\StoredProcedure;
use MagsLabs\LaravelStoredProc\Support\StoredProcedureDriverManager;
use MagsLabs\LaravelStoredProc\Validation\StoredProcedureValidator;

beforeEach(function () {
    //
});

it('sets procedure name via stored_procedure and returns chain', function () {
    $sp = new StoredProcedure;
    $instance = $sp->stored_procedure('my_procedure');

    expect($instance)->toBeInstanceOf(StoredProcedure::class);
});

it('chains stored_procedure with params and values', function () {
    $sp = new StoredProcedure;
    $result = $sp->stored_procedure('get_user')
        ->stored_procedure_params([':id'])
        ->stored_procedure_values([1]);

    expect($result)->toBeInstanceOf(StoredProcedure::class);
});

it('supports shorter method aliases', function () {
    $sp = new StoredProcedure;
    $result = $sp->procedure('get_user')
        ->params([':id'])
        ->values([1]);

    expect($result)->toBeInstanceOf(StoredProcedure::class);
});

it('registers paginate macro on collection', function () {
    expect(Collection::hasMacro('paginate'))->toBeTrue();

    $collection = collect([1, 2, 3, 4, 5]);
    $paginator = $collection->paginate(2);

    expect($paginator->total())->toBe(5);
    expect($paginator->count())->toBe(2);
});

it('throws invalid call order exceptions that extend exception', function () {
    $sp = new StoredProcedure;

    expect(fn () => $sp->stored_procedure_params([':id']))
        ->toThrow(InvalidCallOrderException::class)
        ->toThrow(Exception::class);
});

it('builds mysql and sql server calls via drivers', function () {
    $mysql = new MySqlDriver;
    $sqlsrv = new SqlServerDriver;

    expect($mysql->buildCall('get_users', ':id'))->toBe('CALL get_users (:id);');
    expect($sqlsrv->buildCall('dbo.get_users', '@id'))->toBe('EXEC dbo.get_users @id');
    expect($sqlsrv->buildCall('dbo.get_users', null))->toBe('EXEC dbo.get_users');
});

it('executes sql server procedures with qualified dbo name when unqualified', function () {
    $introspector = Mockery::mock(StoredProcedureIntrospector::class);
    $introspector->shouldReceive('exists')->once()->with('get_users', 'dbo')->andReturn(true);

    $manager = Mockery::mock(StoredProcedureDriverManager::class);
    $manager->shouldReceive('introspectorFor')->once()->andReturn($introspector);
    $manager->shouldReceive('driverFor')->once()->andReturn(new SqlServerDriver);

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('sqlsrv');
    $connection->shouldReceive('select')
        ->once()
        ->with('EXEC dbo.get_users')
        ->andReturn([['id' => 1]]);

    DB::shouldReceive('connection')->andReturn($connection);

    $sp = new StoredProcedure($manager);

    $result = $sp->procedure('get_users')->run()->result();

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result->first())->toBe(['id' => 1]);
});

it('throws stored procedure not found for missing sql server procedure with defaults', function () {
    $introspector = Mockery::mock(StoredProcedureIntrospector::class);
    $introspector->shouldReceive('exists')->once()->with('missing_proc', 'dbo')->andReturn(false);

    $manager = Mockery::mock(StoredProcedureDriverManager::class);
    $manager->shouldReceive('introspectorFor')->once()->andReturn($introspector);

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('sqlsrv');

    DB::shouldReceive('connection')->andReturn($connection);

    $sp = new StoredProcedure($manager);

    expect(fn () => $sp->procedure('missing_proc')->run())
        ->toThrow(StoredProcedureNotFoundException::class, 'Stored procedure [dbo.missing_proc] was not found');
});

it('assertExists throws when procedure is missing', function () {
    $introspector = Mockery::mock(StoredProcedureIntrospector::class);
    $introspector->shouldReceive('exists')->once()->with('missing_proc', 'test_db')->andReturn(false);

    $manager = Mockery::mock(StoredProcedureDriverManager::class);
    $manager->shouldReceive('introspectorFor')->once()->andReturn($introspector);

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getDatabaseName')->andReturn('test_db');

    DB::shouldReceive('connection')->andReturn($connection);

    $sp = new StoredProcedure($manager);

    expect(fn () => $sp->stored_procedure('missing_proc')->assertExists())
        ->toThrow(StoredProcedureNotFoundException::class);
});

it('validate runs before execute when chained', function () {
    $introspector = Mockery::mock(StoredProcedureIntrospector::class);
    $introspector->shouldReceive('exists')->once()->andReturn(true);
    $introspector->shouldReceive('parameters')->once()->andReturn([
        new ProcedureParameter('id', 1, 'IN', 'int'),
    ]);

    $manager = Mockery::mock(StoredProcedureDriverManager::class);
    $manager->shouldReceive('introspectorFor')->once()->andReturn($introspector);
    $manager->shouldReceive('driverFor')->once()->andReturn(new MySqlDriver);

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getDatabaseName')->andReturn('test_db');
    $connection->shouldReceive('beginTransaction')->never();
    $connection->shouldReceive('select')->once()->andReturn([['id' => 1]]);

    DB::shouldReceive('connection')->andReturn($connection);

    $validator = new StoredProcedureValidator;
    $sp = new StoredProcedure($manager, $validator);

    $result = $sp->procedure('get_user')
        ->params([':id'])
        ->values([1])
        ->validate()
        ->run()
        ->result();

    expect($result)->toBeInstanceOf(Collection::class);
});

it('fails validation when input value count mismatches', function () {
    $introspector = Mockery::mock(StoredProcedureIntrospector::class);
    $introspector->shouldReceive('exists')->once()->andReturn(true);
    $introspector->shouldReceive('parameters')->once()->andReturn([
        new ProcedureParameter('id', 1, 'IN', 'int'),
        new ProcedureParameter('role', 2, 'IN', 'varchar'),
    ]);

    $manager = Mockery::mock(StoredProcedureDriverManager::class);
    $manager->shouldReceive('introspectorFor')->once()->andReturn($introspector);

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getDatabaseName')->andReturn('test_db');

    DB::shouldReceive('connection')->andReturn($connection);

    $sp = new StoredProcedure($manager, new StoredProcedureValidator);

    expect(fn () => $sp->procedure('get_user')
        ->params([':id', ':role'])
        ->values([1])
        ->validate()
        ->run())
        ->toThrow(ParameterMismatchException::class);
});

it('automatically checks procedure exists on every execute', function () {
    $introspector = Mockery::mock(StoredProcedureIntrospector::class);
    $introspector->shouldReceive('exists')->once()->with('get_user', 'test_db')->andReturn(true);

    $manager = Mockery::mock(StoredProcedureDriverManager::class);
    $manager->shouldReceive('introspectorFor')->once()->andReturn($introspector);
    $manager->shouldReceive('driverFor')->once()->andReturn(new MySqlDriver);

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getDatabaseName')->andReturn('test_db');
    $connection->shouldReceive('select')->once()->andReturn([]);

    DB::shouldReceive('connection')->andReturn($connection);

    $sp = new StoredProcedure($manager);

    $sp->procedure('get_user')->run();

    expect(true)->toBeTrue();
});

it('throws when procedure is missing on execute without validate', function () {
    $introspector = Mockery::mock(StoredProcedureIntrospector::class);
    $introspector->shouldReceive('exists')->once()->andReturn(false);

    $manager = Mockery::mock(StoredProcedureDriverManager::class);
    $manager->shouldReceive('introspectorFor')->once()->andReturn($introspector);

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getDatabaseName')->andReturn('test_db');

    DB::shouldReceive('connection')->andReturn($connection);

    $sp = new StoredProcedure($manager);

    expect(fn () => $sp->procedure('missing_proc')->run())
        ->toThrow(StoredProcedureNotFoundException::class);
});

it('uses merged package config defaults without publishing', function () {
    expect(config('storedproc.check_exists_before_execute'))->toBeTrue();
    expect(config('storedproc.validate_before_execute'))->toBeFalse();
    expect(config('storedproc.default_schema'))->toBe('dbo');
});

it('skips existence check only when config explicitly disables it', function () {
    config(['storedproc.check_exists_before_execute' => false]);

    $manager = Mockery::mock(StoredProcedureDriverManager::class);
    $manager->shouldReceive('introspectorFor')->never();
    $manager->shouldReceive('driverFor')->once()->andReturn(new MySqlDriver);

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getDatabaseName')->andReturn('test_db');
    $connection->shouldReceive('select')->once()->andReturn([]);

    DB::shouldReceive('connection')->andReturn($connection);

    $sp = new StoredProcedure($manager);

    $sp->procedure('any_proc')->run();

    config(['storedproc.check_exists_before_execute' => true]);

    expect(true)->toBeTrue();
});
