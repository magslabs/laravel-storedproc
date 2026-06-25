<?php

namespace MagsLabs\LaravelStoredProc;

use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MagsLabs\LaravelStoredProc\Exceptions\InvalidCallOrderException;
use MagsLabs\LaravelStoredProc\Exceptions\StoredProcedureNotFoundException;
use MagsLabs\LaravelStoredProc\Support\StoredProcedureDriverManager;
use MagsLabs\LaravelStoredProc\Validation\StoredProcedureValidator;
use Throwable;

/**
 * Class StoredProcedure
 *
 * A fluent interface for executing stored procedures in Laravel.
 *
 * ### Example Usage:
 * ```php
 * $result = StoredProcedure::stored_procedure('my_procedure')
 *    ->stored_procedure_params([':id']) //optional if parameters exist
 *    ->stored_procedure_values([1]) //optional if parameters exist
 *    ->with_transaction() // optional to enable transactions
 *    ->execute()
 *    ->stored_procedure_result();
 * ```
 *
 * Shorter aliases (same behavior, optional): `procedure()`, `connection()`, `params()`,
 * `values()`, `outputs()`, `run()`, `result()`, `outputResults()`.
 *
 * ### Validation (v2)
 * Every `execute()` / `run()` automatically verifies the procedure exists on the database.
 * Full parameter validation is opt-in via `->validate()` or `STORED_PROC_VALIDATE=true`.
 * Disable automatic existence checks with `STORED_PROC_CHECK_EXISTS=false`.
 *
 * @method static self stored_procedure(string $procedure) Set the stored procedure name (Required, must be called first).
 * @method static self stored_procedure_connection(string $connection) Set a specific database connection (Optional).
 * @method static self stored_procedure_params(array|Request $params) Set procedure parameters including OUTPUT params with OUTPUT keyword (Optional, required if the procedure has parameters).
 * @method static self stored_procedure_values(array $values) Set procedure values for input parameters only (Optional, required if input parameters exist).
 * @method static self stored_procedure_output_params(array $output_params) Define SQL types for OUTPUT/OUT parameters (Optional, SQL Server/MySQL).
 * @method static self with_transaction(bool $value = true) Optionally wrap the procedure in a Laravel-managed transaction.
 * @method static self validate() Validate procedure existence and parameters before execute (Optional).
 * @method static self assertExists() Fail fast if the procedure is missing (Optional).
 * @method static self execute() Execute the stored procedure (Required, must be called last).
 * @method static mixed stored_procedure_result() Retrieve the stored procedure result as Collection or object with result/output properties (Required).
 * @method static mixed stored_procedure_output_results() Retrieve only OUTPUT/OUT parameter results (Optional, SQL Server/MySQL).
 * @method static self reset() Manually reset the instance state (Optional).
 * @method static self procedure(string $procedure) Alias of stored_procedure().
 * @method static self connection(string $connection) Alias of stored_procedure_connection().
 * @method static self params(array|Request $params) Alias of stored_procedure_params().
 * @method static self values(array $values) Alias of stored_procedure_values().
 * @method static self outputs(array $output_params) Alias of stored_procedure_output_params().
 * @method static self run() Alias of execute().
 * @method static mixed result() Alias of stored_procedure_result().
 * @method static mixed outputResults() Alias of stored_procedure_output_results().
 */
class StoredProcedure
{
    protected string $db;

    protected ?string $procedure_name = null;

    protected ?string $params = null;

    protected array $values = [];

    protected ?string $connection = null;

    protected ?string $schema = null;

    protected ?array $result = null;

    /** @var array<string, string> */
    protected array $output_params = [];

    /** @var array<int, array<string, mixed>> */
    protected array $output_results = [];

    protected bool $use_transaction = false;

    protected bool $should_validate = false;

    private bool $is_sp_name_initialized = false;

    private bool $is_sp_params_initialized = false;

    private bool $is_sp_values_initialized = false;

    private bool $is_execute_called = false;

    public function __construct(
        protected ?StoredProcedureDriverManager $driver_manager = null,
        protected ?StoredProcedureValidator $validator = null,
    ) {
        $this->db = DB::class;
        $this->driver_manager = $driver_manager ?? new StoredProcedureDriverManager;
        $this->validator = $validator ?? new StoredProcedureValidator;
    }

    protected function logger(): \Psr\Log\LoggerInterface
    {
        try {
            return Log::channel('magslabs_laravel_stored_proc');
        } catch (Throwable $e) {
            return Log::build([
                'driver' => 'errorlog',
                'level' => 'debug',
            ]);
        }
    }

    public function stored_procedure(string $procedure = ''): self
    {
        $this->procedure_name = $procedure;
        $this->is_sp_name_initialized = true;

        $this->logger()->debug('Stored procedure set', ['procedure' => $procedure]);

        return $this;
    }

    public function procedure(string $procedure = ''): self
    {
        return $this->stored_procedure($procedure);
    }

    public function stored_procedure_connection(string $connection = ''): self
    {
        $this->connection = $connection;

        $this->logger()->debug('Database connection set', ['connection' => $connection]);

        return $this;
    }

    public function connection(string $connection = ''): self
    {
        return $this->stored_procedure_connection($connection);
    }

    public function stored_procedure_schema(?string $schema = null): self
    {
        $this->schema = $schema;

        return $this;
    }

    public function stored_procedure_params(array|Request $params = []): self
    {
        if (! $this->is_sp_name_initialized) {
            $this->logger()->error('stored_procedure_params() called before stored_procedure()');
            throw new InvalidCallOrderException('You must call stored_procedure() before stored_procedure_params().');
        }

        if ($params instanceof Request) {
            unset($params['_token']);

            $params_keys_array = [];
            foreach (array_keys($params->toArray()) as $key) {
                $params_keys_array[] = ':'.$key;
            }

            $this->params = empty($params_keys_array) ? null : implode(', ', $params_keys_array);
        } else {
            $this->params = empty($params) ? null : implode(', ', $params);
        }

        $this->is_sp_params_initialized = true;

        $this->logger()->debug('Stored procedure parameters set', ['params' => $this->params]);

        return $this;
    }

    public function params(array|Request $params = []): self
    {
        return $this->stored_procedure_params($params);
    }

    public function stored_procedure_values(array $values = []): self
    {
        if (! $this->is_sp_params_initialized) {
            $this->logger()->error('stored_procedure_values() called before stored_procedure_params()');
            throw new InvalidCallOrderException('You must call stored_procedure_params() before stored_procedure_values().');
        }

        if (empty($this->params)) {
            $this->logger()->error('Values were set but no parameters exist');
            throw new InvalidCallOrderException('Cannot call stored_procedure_values() if there are no parameters.');
        }

        $this->values = $values;
        $this->is_sp_values_initialized = true;

        $this->logger()->debug('Stored procedure values set', ['values' => $values]);

        return $this;
    }

    public function values(array $values = []): self
    {
        return $this->stored_procedure_values($values);
    }

    public function stored_procedure_output_params(array $output_params = []): self
    {
        $default_type = $this->resolveDefaultOutputType();

        $normalized = [];
        foreach ($output_params as $key => $value) {
            if (is_int($key)) {
                $normalized[$value] = $default_type;
            } else {
                $normalized[$key] = strtoupper($value);
            }
        }

        $this->output_params = $normalized;

        $this->logger()->debug('Output parameters set', ['output_params' => $this->output_params]);

        return $this;
    }

    public function outputs(array $output_params = []): self
    {
        return $this->stored_procedure_output_params($output_params);
    }

    public function with_transaction(bool $use_transaction = true): self
    {
        $this->use_transaction = $use_transaction;

        $this->logger()->debug('Transaction enabled', ['use_transaction' => $use_transaction]);

        return $this;
    }

    /**
     * Opt in to database validation on the next execute() call.
     */
    public function validate(): self
    {
        $this->should_validate = true;

        return $this;
    }

    /**
     * Immediately verify the procedure exists on the active connection.
     *
     * @throws StoredProcedureNotFoundException
     */
    public function assertExists(): self
    {
        $this->ensureProcedureNamed();

        $introspector = $this->driver_manager->introspectorFor($this->resolveConnection());

        if (! $introspector->exists($this->qualifiedProcedureName())) {
            $qualified = $this->qualifiedProcedureName();

            throw new StoredProcedureNotFoundException(
                "Stored procedure [{$qualified}] was not found on the database."
            );
        }

        return $this;
    }

    /**
     * Check whether the procedure exists without throwing.
     */
    public function exists(): bool
    {
        if (! $this->is_sp_name_initialized) {
            return false;
        }

        try {
            $introspector = $this->driver_manager->introspectorFor($this->resolveConnection());

            return $introspector->exists($this->qualifiedProcedureName());
        } catch (Throwable $throwable) {
            $this->logger()->warning('Stored procedure existence check failed', [
                'procedure' => $this->qualifiedProcedureName(),
                'error' => $throwable->getMessage(),
                'exception' => get_class($throwable),
            ]);

            return false;
        }
    }

    public function execute(): self
    {
        return $this->run();
    }

    public function run(): self
    {
        if (! $this->is_sp_name_initialized) {
            throw new InvalidCallOrderException('You must call stored_procedure() before execute().');
        }

        if ($this->is_sp_params_initialized && ! $this->is_sp_values_initialized) {
            throw new InvalidCallOrderException('You must call stored_procedure_values() after stored_procedure_params().');
        }

        $connection = $this->resolveConnection();

        if ($this->shouldCheckExists()) {
            $this->runExistenceCheck($connection);
        }

        if ($this->should_validate || $this->configBool('validate_before_execute', false)) {
            $this->runValidation($connection);
        }

        $driver = $this->driver_manager->driverFor($connection);
        $procedure = $this->qualifiedProcedureName();
        $sp_call = $driver->buildCall($procedure, $this->params);

        $this->logger()->info('Executing stored procedure', [
            'query' => $sp_call,
            'procedure' => $procedure,
            'connection' => $this->connection ?? 'default',
            'driver' => $driver->name(),
            'use_transaction' => $this->use_transaction,
        ]);
        $this->logger()->debug('Stored procedure bindings (debug only)', [
            'values' => $this->values,
            'output_params' => $this->output_params,
        ]);

        try {
            if ($this->use_transaction) {
                $connection->beginTransaction();
            }

            $execution = $driver->execute(
                $connection,
                $procedure,
                $this->params,
                $this->values,
                $this->output_params,
            );

            $this->result = $execution->result;
            $this->output_results = $execution->output_results;

            if ($this->use_transaction) {
                $connection->commit();
            }

            $this->logger()->info('Stored procedure executed successfully');
        } catch (Throwable $throwable) {
            if ($this->use_transaction) {
                $connection->rollBack();
            }

            $this->logger()->error('Stored procedure execution failed', [
                'error' => $throwable->getMessage(),
                'exception' => get_class($throwable),
                'query' => $sp_call,
            ]);
            $this->logger()->debug('Failed call bindings (debug only)', ['values' => $this->values]);

            throw $this->wrapExecutionException($throwable, $procedure);
        }

        $this->is_execute_called = true;
        $this->should_validate = false;

        return $this;
    }

    public function stored_procedure_result(): mixed
    {
        return $this->result();
    }

    public function result(): mixed
    {
        if (! $this->is_execute_called) {
            $this->logger()->error('Attempted to retrieve stored procedure result before execution');
            throw new InvalidCallOrderException('You must call execute() before stored_procedure_result().');
        }

        $this->logger()->debug('Returning stored procedure result (smart mode)', [
            'records_found' => count($this->result ?? []),
            'output_results' => $this->output_results,
        ]);

        $result = $this->result ?? [];
        $outputs = $this->output_results;

        $this->autoReset();

        if (empty($outputs)) {
            return collect($result)->count() > 0 ? Collection::make($result) : Collection::make([]);
        }

        return (object) [
            'result' => collect($result)->count() > 0 ? Collection::make($result) : Collection::make([]),
            'output' => $this->normalizeOutput($outputs),
        ];
    }

    private function normalizeOutput(array $outputs): mixed
    {
        if (empty($outputs)) {
            return null;
        }

        $row = $outputs[0] ?? [];

        if (count($row) === 1) {
            return reset($row);
        }

        return (object) $row;
    }

    public function stored_procedure_output_results(): mixed
    {
        return $this->outputResults();
    }

    public function outputResults(): mixed
    {
        if (! $this->is_execute_called) {
            $this->logger()->error('Attempted to retrieve output params before execution');
            throw new InvalidCallOrderException('You must call execute() before get_output_params().');
        }

        $this->logger()->debug('Returning output parameters', [
            'output_results' => $this->output_results,
        ]);

        if (empty($this->output_results)) {
            return null;
        }

        $row = $this->output_results[0] ?? [];

        if (count($row) === 1) {
            return reset($row);
        }

        return (object) $row;
    }

    private function autoReset(): void
    {
        $preserved_result = $this->result;
        $preserved_output_results = $this->output_results;

        $this->procedure_name = null;
        $this->params = null;
        $this->values = [];
        $this->connection = null;
        $this->schema = null;
        $this->use_transaction = false;
        $this->should_validate = false;
        $this->output_params = [];

        $this->is_sp_name_initialized = false;
        $this->is_sp_params_initialized = false;
        $this->is_sp_values_initialized = false;
        $this->is_execute_called = false;

        $this->result = $preserved_result;
        $this->output_results = $preserved_output_results;
    }

    public function reset(): self
    {
        $this->autoReset();
        $this->result = null;
        $this->output_results = [];

        return $this;
    }

    private function ensureProcedureNamed(): void
    {
        if (! $this->is_sp_name_initialized) {
            throw new InvalidCallOrderException('You must call stored_procedure() first.');
        }
    }

    private function resolveConnection(): Connection
    {
        if ($this->connection === null || $this->connection === '') {
            return $this->db::connection();
        }

        return $this->db::connection($this->connection);
    }

    private function resolveSchema(): ?string
    {
        if ($this->schema !== null) {
            return $this->schema;
        }

        if ($this->procedure_name !== null && str_contains($this->procedure_name, '.')) {
            return explode('.', $this->procedure_name, 2)[0];
        }

        $connection = $this->resolveConnection();

        return match ($connection->getDriverName()) {
            'sqlsrv' => $this->config('default_schema', 'dbo'),
            'mysql', 'mariadb' => $connection->getDatabaseName(),
            default => null,
        };
    }

    private function qualifiedProcedureName(): string
    {
        if ($this->procedure_name === null) {
            return '';
        }

        if (str_contains($this->procedure_name, '.')) {
            return $this->procedure_name;
        }

        $schema = $this->resolveSchema();

        return $schema ? "{$schema}.{$this->procedure_name}" : $this->procedure_name;
    }

    private function resolveDefaultOutputType(): string
    {
        try {
            return $this->driver_manager
                ->driverFor($this->resolveConnection())
                ->defaultOutputType();
        } catch (Throwable) {
            return 'BIT';
        }
    }

    private function runExistenceCheck(Connection $connection): void
    {
        $introspector = $this->driver_manager->introspectorFor($connection);
        $qualified = $this->qualifiedProcedureName();

        if (! $introspector->exists($qualified)) {
            $this->logger()->error('Stored procedure not found before execute', [
                'procedure' => $qualified,
                'connection' => $this->connection ?? 'default',
            ]);

            throw new StoredProcedureNotFoundException(
                "Stored procedure [{$qualified}] was not found on the database."
            );
        }
    }

    private function runValidation(Connection $connection): void
    {
        $introspector = $this->driver_manager->introspectorFor($connection);

        $this->validator->validate(
            $introspector,
            $this->qualifiedProcedureName(),
            $this->params,
            $this->values,
            $this->output_params,
        );
    }

    private function shouldCheckExists(): bool
    {
        return $this->configBool('check_exists_before_execute', true);
    }

    private function configBool(string $key, bool $default): bool
    {
        return filter_var($this->config($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    private function wrapExecutionException(Throwable $throwable, string $procedure): Throwable
    {
        if ($throwable instanceof StoredProcedureNotFoundException) {
            return $throwable;
        }

        $message = $throwable->getMessage();

        $is_missing_procedure = preg_match('/could not find stored procedure/i', $message) === 1
            || preg_match('/procedure .* does not exist/i', $message) === 1
            || preg_match('/unknown procedure/i', $message) === 1
            || preg_match('/\b2812\b/', $message) === 1
            || preg_match('/\b1305\b/', $message) === 1;

        if ($is_missing_procedure) {
            return new StoredProcedureNotFoundException(
                "Stored procedure [{$procedure}] was not found on the database.",
                0,
                $throwable
            );
        }

        return $throwable;
    }

    private function config(string $key, mixed $default = null): mixed
    {
        if (function_exists('config')) {
            return config("storedproc.{$key}", $default);
        }

        return $default;
    }
}
